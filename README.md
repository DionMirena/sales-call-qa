# Sales Call QA Agent

A Laravel tool for QA managers to evaluate life insurance sales calls against custom criteria templates. Managers create templates, upload call recordings, and the system transcribes and AI-scores each call — with per-criterion evidence, confidence scores, and a human override workflow.

## Feature overview

| Area | What it does |
|---|---|
| **Templates** | Create/edit/duplicate templates with weighted criteria (pass/fail, yes/no/na, 1–5 scale, extract) |
| **Upload** | Upload MP3/WAV/M4A/AAC/OGG/FLAC/WebM up to 1 GB |
| **Processing** | ffmpeg normalise → Deepgram transcribe → Claude score, all in a queue job |
| **Results page** | Audio player with transcript sync, evidence linking, speaker swap |
| **Overrides** | Inline per-criterion overrides with append-only audit trail; score recalculates immediately |
| **Dashboard** | Today's stats, needs-review queue |
| **Calls list** | Filterable by agent / template / outcome / status / date range, paginated |
| **Agent stats** | Avg score, pass rate, top-failed criteria (override-adjusted), 8-week trend bars |
| **Delete / Re-score** | Delete removes audio + all records; re-score clears results and re-runs |
| **Artisan** | `qa:eval` evaluates AI accuracy against a labelled golden set; `qa:stats` shows DB aggregate stats |
| **Demo mode** | `QA_FAKE_AI=true` runs the full UI without any API keys |

---

## Requirements

- PHP 8.3+ with `pdo_sqlite`, `pdo_mysql`, `fileinfo`, `mbstring`, `curl`, `zip`
- Composer 2.x
- Node.js 18+ and npm
- ffmpeg (any recent build)
- MySQL 8.x (local)

---

## Setup

```bash
git clone <repo-url> sales-call-qa
cd sales-call-qa
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate
php artisan db:seed
npm install && npm run build
php artisan serve
```

Open http://localhost:8000 and log in (see **Seeded logins** below).

### Fill in `.env`

```
DB_PASSWORD=          # your MySQL root password (blank if none)
DEEPGRAM_API_KEY=     # from console.deepgram.com
ANTHROPIC_API_KEY=    # from console.anthropic.com

# Optional — defaults shown
ANTHROPIC_MODEL=claude-sonnet-4-6
QA_LANGUAGE=en
QA_FAKE_AI=false
```

### ffmpeg path (Windows)

If ffmpeg is not on your system `PATH`, add the full path to `.env` using forward slashes:

```
FFMPEG_PATH=C:/path/to/ffmpeg.exe
FFPROBE_PATH=C:/path/to/ffprobe.exe
```

Backslashes in `.env` values are treated as escape sequences and will break the dotenv parser.

---

## Demo mode (no API keys)

Set `QA_FAKE_AI=true` in `.env`.  
The app uses fixture transcription and fake scoring — the full UI is clickable, including overrides and the results page.

---

## Seeded logins

| Role | Email | Password |
|---|---|---|
| Admin | admin@example.com | password |
| Manager | manager@example.com | password |

**Agents** (data records, not login accounts): Sarah Mitchell, James O'Brien, Emma Clarke, Daniel Hughes, Priya Sharma.

---

## Running the queue

Call processing (normalise → transcribe → score) runs as a queued job.

```bash
# Single worker
php artisan queue:work --timeout=600

# Everything together (server + queue + Vite hot-reload)
composer run dev
```

---

## Running tests

```bash
php artisan test
# or directly
./vendor/bin/phpunit
```

Tests use an in-memory SQLite database — no MySQL required. 141 tests, ~15 seconds.

---

## Artisan commands

### `php artisan qa:eval`

Evaluates AI scoring accuracy against a hand-labelled golden set.

```bash
# Use the default golden set (tests/golden/golden_set.json)
php artisan qa:eval

# Different file
php artisan qa:eval --golden=tests/golden/my_set.json

# Override template name
php artisan qa:eval --template="Standard Insurance QA"
```

Exits `0` if overall accuracy ≥ 80%, `1` otherwise — usable in CI.  
See `docs/golden_set.md` for the file format.

### `php artisan qa:stats`

Shows aggregate stats from the live database.

```bash
php artisan qa:stats            # last 30 days
php artisan qa:stats --days=0   # all time
php artisan qa:stats --template="Standard Insurance QA"
```

Outputs: total calls, avg score, pass/fail breakdown, per-template table, top-10 failed criteria.

---

## Upload limits

For calls longer than a few minutes, ensure your PHP and web-server limits are generous:

```ini
; php.ini
upload_max_filesize = 1G
post_max_size = 1G
```

---

## Project structure

```
app/
  Console/Commands/   qa:eval, qa:stats
  Http/Controllers/   Call, Template, Override, Agent, Dashboard
  Jobs/               ProcessCallJob
  Models/             Call, Agent, Template, Criterion, CriterionResult, Override, TranscriptSegment
  Policies/           CallPolicy, TemplatePolicy, OverridePolicy
  Services/           TranscriptionService, ScoringService (+ Fake/Claude/Deepgram impls)
  Support/            AudioNormaliser, EvidenceVerifier, ScoreCalculator
docs/
  golden_set.md       Golden set format spec
  ai_notes.md         Log of AI scoring mistakes
tests/
  Feature/            HTTP + job integration tests
  Unit/               EvidenceVerifier, ScoreCalculator unit tests
  golden/             golden_set.json (labelled eval cases)
```
