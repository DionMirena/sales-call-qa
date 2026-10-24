# Sales Call QA Agent — Architecture Reference

## Component overview

```
Browser (Blade + Alpine.js + Tailwind)
    │
    ▼
Laravel HTTP layer (routes/web.php)
    │
    ├─ TemplateController   — CRUD + duplicate
    ├─ CallController       — upload, index (filters + sort), show, audio, status, retry,
    │                         rescore, swap speaker, destroy
    ├─ OverrideController   — store (validates + recalculates score)
    ├─ AgentController      — index, show (stats + trend)
    ├─ DashboardController  — today's KPIs + needs-review queue
    └─ StatsController      — chart data
    │
    ▼
ProcessCallJob (queued, timeout=900, tries=2)
    │
    ├─ AudioNormaliser          → ffmpeg: mono 16kHz MP3
    ├─ TranscriptionService     → DeepgramTranscriptionService
    │                              (FakeTranscriptionService in demo/test)
    └─ ScoringService           → ClaudeScoringService (when an sk-ant- key is set)
                                   or KeywordScoringService (free, rule-based)
                                   (FakeScoringService in demo/test)
```

---

## Request / data flow

### Upload
1. `POST /calls` — validates MIME, size, agent, template
2. File stored to the `private` disk (`storage/app/audio/calls/originals/`)
3. `Call` record created (`status=queued`)
4. `ProcessCallJob::dispatch($call->id)` pushed to queue

### Processing (job)
1. `AudioNormaliser::normalise(originalPath)` → writes the normalised MP3 to `calls/{id}/audio.mp3` on the private disk
2. `TranscriptionService::transcribe(normalised)` → array of `{speaker, start_ms, end_ms, text}`
3. `TranscriptSegment` rows bulk-inserted (code = "S1", "S2", ...); `agent_speaker` guessed (company name, then "my name is", then "This is <Name>", else the first speaker)
4. `ScoringService::score(Call)` → stores `CriterionResult` rows, updates `score_percent` + `outcome`
5. `Call::status` transitions: `queued → normalising → transcribing → scoring → done` (or `failed`)

### Scoring (Claude)
1. Build system prompt + user message (criteria + formatted transcript)
2. Call Anthropic `/messages` with `tool_use` (`score_call` tool schema)
3. Parse `tool_use` block from response
4. Validate: every criterion_id present exactly once; result value matches criterion type; segment codes exist
5. On validation failure: retry once with error list prepended; if still invalid → `Call::status=failed`
6. `EvidenceVerifier::verify()` on each evidence item
7. `ScoreCalculator::calculate(template, criterionResults)` → `[score_percent, outcome]`
8. Store results + update call

### Override
1. `POST /criterion-results/{criterionResult}/overrides` — named error bag scoped to `cr_{$id}`
2. `Override` row appended (immutable — no `updated_at`)
3. `CriterionResult::latestOverride()` uses `reorder()->latest('id')->first()` to read the new override
4. `ScoreCalculator::calculate()` re-run on the call's full `criterionResults()->with('overrides')` collection
5. `Call::score_percent` + `Call::outcome` updated

---

## Database schema

### users
| Column | Type |
|--------|------|
| id | bigint PK |
| name | varchar |
| email | varchar unique |
| role | enum('admin','manager') |
| password | varchar |
| timestamps | |

### agents
| Column | Type |
|--------|------|
| id | bigint PK |
| name | varchar |
| team | varchar nullable |
| timestamps | |

### templates
| Column | Type |
|--------|------|
| id | bigint PK |
| name | varchar |
| description | text nullable |
| pass_threshold | tinyint unsigned default 80 |
| created_by | FK users |
| timestamps | |
| deleted_at | softDeletes |

### criteria
| Column | Type |
|--------|------|
| id | bigint PK |
| template_id | FK templates |
| position | smallint unsigned |
| name | varchar |
| description | text |
| fail_conditions | text nullable |
| type | enum('pass_fail','scale_1_5','yes_no_na','extract') |
| weight | tinyint unsigned default 1 |
| is_critical | boolean default false |
| phase_hint | enum('opening','discovery','pitch','objections','close','anywhere') |
| timestamps | |

### calls
| Column | Type |
|--------|------|
| id | bigint PK |
| agent_id | FK agents |
| template_id | FK templates |
| uploaded_by | FK users |
| customer_name | varchar nullable |
| policy_type | varchar nullable |
| original_path | varchar |
| original_filename | varchar |
| audio_path | varchar nullable |
| duration_seconds | int unsigned nullable |
| status | enum('queued','normalising','transcribing','scoring','done','failed') |
| error_message | text nullable |
| agent_speaker | tinyint unsigned default 0 |
| score_percent | decimal(5,2) nullable |
| outcome | enum('pass','fail','auto_fail') nullable |
| timestamps | |

### transcript_segments
| Column | Type |
|--------|------|
| id | bigint PK |
| call_id | FK calls (cascade delete) |
| code | varchar(10) — "S1", "S2" ... |
| speaker | tinyint unsigned |
| start_ms | int unsigned |
| end_ms | int unsigned |
| text | text |

Index: `(call_id, code)` unique. No timestamps (insert-only, high volume).

### criterion_results
| Column | Type |
|--------|------|
| id | bigint PK |
| call_id | FK calls (cascade delete) |
| criterion_id | FK criteria nullable (null if criterion deleted) |
| criterion_name | varchar — snapshot at scoring time |
| criterion_description | text |
| criterion_type | enum (same values as criteria.type) |
| criterion_weight | tinyint unsigned |
| criterion_is_critical | boolean |
| ai_result | varchar(20) — "pass","fail","yes","no","na","1"–"5", or extracted text |
| ai_score | decimal(4,3) nullable |
| confidence | decimal(4,3) |
| reasoning | text |
| evidence | JSON — [{segment_code, quote, verified}] |
| verification_status | enum('verified','partial','unverified') |
| needs_review | boolean default false |
| timestamps | |

### overrides
| Column | Type |
|--------|------|
| id | bigint PK |
| criterion_result_id | FK criterion_results |
| user_id | FK users |
| old_result | varchar |
| old_score | decimal(4,3) nullable |
| new_result | varchar |
| new_score | decimal(4,3) nullable |
| reason | text NOT NULL |
| created_at | timestamp (no updated_at — immutable log) |

---

## Service interfaces

```php
interface TranscriptionService
{
    // Returns array of segments: [{speaker, start_ms, end_ms, text}]
    public function transcribe(string $audioPath): array;
}

interface ScoringService
{
    // Stores CriterionResult rows and updates call.score_percent + call.outcome
    public function score(Call $call): void;
}
```

Bindings in `AppServiceProvider::register()`:

```php
if (config('qa.fake_ai')) {
    $this->app->bind(TranscriptionService::class, FakeTranscriptionService::class);
    $this->app->bind(ScoringService::class, FakeScoringService::class);
} else {
    $this->app->bind(TranscriptionService::class, DeepgramTranscriptionService::class);
    // Without an Anthropic key, score the real transcript with the free keyword scorer.
    $this->app->bind(ScoringService::class, str_starts_with((string) config('qa.anthropic_key'), 'sk-ant-')
        ? ClaudeScoringService::class
        : KeywordScoringService::class);
}
```

Tests that need a specific binding call `$this->app->bind(ScoringService::class, FakeScoringService::class)` after `Config::set('qa.fake_ai', true)` — AppServiceProvider binds at boot time before config overrides take effect in the test.

---

## Scoring algorithm

```
effectiveResult(cr)  = latestOverride.new_result ?? cr.ai_result
effectiveScore(cr)   = latestOverride.new_score  ?? cr.ai_score

eligible = criterionResults where effectiveResult ∉ {'na', extracted text}

denominator = sum(weight) for cr in eligible

score_percent = sum(effectiveScore × weight for cr in eligible) / denominator × 100

outcome =
  'auto_fail'  if any cr where is_critical AND effectiveResult ∈ {'fail', 'no'}
  'pass'       if score_percent >= template.pass_threshold
  'fail'       otherwise
```

`extract`-type criteria are excluded from numeric scoring. `na` results shrink the denominator so the score reflects only applicable criteria.

---

## Evidence verification algorithm

For each `{segment_code, quote}` evidence item:

1. Fetch `TranscriptSegment` by `call_id` + `code`. If missing → `unverified`.
2. Normalise: lowercase, strip punctuation (preserve apostrophes), collapse whitespace.
3. **Exact substring**: `normalised(quote) ∈ normalised(segment.text)` → `verified`
4. **Cross-segment window**: concatenate previous + current + next segment text, repeat substring check → `verified` (handles quotes that span a speaker turn)
5. **Fuzzy token overlap**: `score = |intersection(quote_tokens, segment_tokens)| / |quote_tokens|`
   - ≥ 0.85 → `verified`
   - 0.60–0.84 → `partial`
   - < 0.60 → `unverified`

Per-criterion status = worst item status (`unverified` > `partial` > `verified`).

Confidence adjustments:
- `unverified` → `confidence × 0.5`
- `partial` → `confidence × 0.75`

`needs_review = true` when: `confidence < 0.60` after adjustment, or `verification_status != 'verified'`, or `is_critical AND effectiveResult ∈ {'fail', 'no'}`.

---

## Key model relationships

```
User         hasMany Call (uploaded_by), hasMany Override (user_id)
Agent        hasMany Call
Template     hasMany Criterion, hasMany Call
Call         belongsTo Agent, Template, User
             hasMany TranscriptSegment (cascade delete)
             hasMany CriterionResult   (cascade delete)
CriterionResult belongsTo Call, Criterion (nullable)
             hasMany Override  (orderBy id ASC)
Override     belongsTo CriterionResult, User
```

`CriterionResult::latestOverride()` uses `reorder()->latest('id')->first()` to override the relationship's ASC scope with DESC, returning the most-recently appended override without an N+1 when the collection is already loaded.

---

## Audio security

Audio files live on the `private` disk (`storage/app/audio/`), which is not web-accessible. Served only through `GET /calls/{call}/audio` — an authenticated, policy-gated route that streams with HTTP range-request support (enabling seek in the browser audio player); `?download=1` downloads it instead. The route requires login + `CallPolicy::view()`.

---

## Config

`config/qa.php` exposes:

| Key | Env variable | Default |
|-----|-------------|---------|
| `qa.fake_ai` | `QA_FAKE_AI` | `false` |
| `qa.language` | `QA_LANGUAGE` | `en` |
| `qa.model` | `ANTHROPIC_MODEL` | `claude-sonnet-4-6` |
| `qa.deepgram_key` | `DEEPGRAM_API_KEY` | — |
| `qa.anthropic_key` | `ANTHROPIC_API_KEY` | — (a key starting `sk-ant-` switches scoring to Claude) |
| `qa.company_name` | `QA_COMPANY_NAME` | `INSURVAS` |
| `qa.scoring.needs_review_confidence_threshold` | — | `0.6` |
| `qa.scoring.fuzzy_verified_threshold` / `fuzzy_partial_threshold` | — | `0.85` / `0.60` |

---

## Test isolation

- `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:` set in `phpunit.xml` — no MySQL for tests
- `RefreshDatabase` trait on every feature test class
- `QUEUE_CONNECTION=sync` in `phpunit.xml` — jobs run inline; tests that need to assert dispatch use `Queue::fake()`
- `Storage::fake('private')` in upload/delete tests
- `Http::fake()` in scoring feature tests — Anthropic responses mocked; no API calls

---

## How to make common changes

Run `php artisan test` after each change.

### Change the pass threshold
- For one template: edit the template in the UI (Templates → Edit → Pass threshold). Calls are re-judged when re-scored.
- The default for new templates: `resources/views/templates/_form.blade.php` (`old('pass_threshold', ... ?? 80)`) and the migration default in `2025_01_01_000020_create_templates_table.php`.

### Change the "needs review" confidence threshold
- `config/qa.php` → `scoring.needs_review_confidence_threshold` (default `0.6`). Used by `ClaudeScoringService` and `KeywordScoringService`.

### Change the AI model
- `.env` → `ANTHROPIC_MODEL=...` (read as `config('qa.model')` in `ClaudeScoringService::callAnthropic()`).

### Add a language
- `.env` → `QA_LANGUAGE=es` (passed to Deepgram as the `language` query parameter). The UI text stays English. The rule-based scorer only knows English phrasings; Claude scoring works in other languages.

### Add a new criterion type (e.g. `number_range`)
1. A new migration that adds the value to the `type` enum on `criteria` and the `criterion_type` enum on `criterion_results`.
2. `TemplateController` validation: `criteria.*.type` `in:` list in both `store()` and `update()`.
3. Builder dropdown: `resources/views/templates/_criteria_builder.blade.php`.
4. Scoring value: `ScoreCalculator::resultToValue()`.
5. Allowed AI results: `$allowedResults` in `ClaudeScoringService::validate()` and `normaliseScore()`.
6. Free scorer: the `match` on `$criterion->type` in `KeywordScoringService::score()`.
7. Overrides: the allowed values in `OverrideController::store()` / `normaliseScore()` and the override `<select>` in `resources/views/calls/show.blade.php`.
8. A `ScoreCalculatorTest` case for the new type.

### Add a sort option to the calls list
- `CallController::index()` → add `'key' => ['column', 'asc|desc']` to `$sorts`, then add the option to the Sort dropdown in `resources/views/calls/index.blade.php`. Unknown keys fall back to newest first, so only whitelisted columns can be sorted.

### Teach the free scorer a new phrasing
- `KeywordScoringService::CONCEPTS` → extend the regex for that concept (e.g. `'purpose'`), and add a case to `tests/Unit/KeywordScoringServiceTest.php`.

### Change the company name
- `.env` → `QA_COMPANY_NAME=...`. Used for the logo text, the agent-speaker guess and the Deepgram keyword boost.
