# Sales Call QA Agent

A Laravel tool for QA managers to evaluate life insurance sales calls against custom criteria templates. Managers create templates, upload call recordings, and the system transcribes and AI-scores each call — with per-criterion evidence, confidence scores, and a human override workflow.

## Feature overview

| Area | What it does |
|---|---|
| **Templates** | Create/edit/duplicate templates with weighted criteria (pass/fail, yes/no/na, 1–5 scale, extract) |
| **Upload** | Upload MP3/WAV/M4A/AAC/OGG/FLAC/WebM up to 1 GB |
| **Processing** | ffmpeg normalise → Deepgram transcribe → Claude score, all in a queue job |
| **Results page** | Audio player with playback speed, transcript sync + line highlight, evidence linking, speaker swap, download |
| **Overrides** | Inline per-criterion overrides with a required reason and an append-only audit trail; "Mark AI correct" records a confirmation; score recalculates immediately |
| **Dashboard** | Today's stats, auto-fail count, needs-review queue (oldest first), outcome and trend charts |
| **Calls list** | Call number and file name, length, filters (agent / template / outcome / status / dates / score range / needs review) and sorting (newest, call number, longest/shortest) |
| **Stats** | Chart.js page: outcomes, calls and average score per agent, most-failed criteria, score trend |
| **Agent stats** | Avg score, pass rate, top-failed criteria (override-adjusted), 8-week trend bars |
| **Delete / Re-score** | Delete removes audio + all records; re-score keeps the transcript, clears the results and scores again (optionally against another template) |
| **Artisan** | `qa:eval` measures scoring accuracy against a hand-labelled golden set; `qa:stats` shows totals and the override rate per criterion |
| **Demo mode** | `QA_FAKE_AI=true` runs the full UI without any API keys |

---

## Requirements

| Requirement | Version |
|---|---|
| PHP | 8.3+ with `pdo_sqlite`, `fileinfo`, `mbstring`, `curl`, `zip` |
| Composer | 2.x |
| Node.js | 20+ |
| npm | bundled with Node |
| SQLite | bundled with PHP (`pdo_sqlite`); MySQL 8.x also supported |
| ffmpeg + ffprobe | any recent build (required for audio normalisation) |
| Python 3 + edge-tts | only needed to **generate** sample call audio (not for running the app) |

Install edge-tts:
```bash
pip install edge-tts
```

---

## Setup

```bash
git clone https://github.com/DionMirena/sales-call-qa.git
cd sales-call-qa
composer install
npm install && npm run build
cp .env.example .env
php artisan key:generate
php -r "file_exists('database/database.sqlite') || touch('database/database.sqlite');"
```

The last line creates the empty SQLite database file (it works on Windows, macOS and Linux).

### Configure `.env`

The default `.env.example` uses **SQLite** — no database server required. Open `.env` and set:

```dotenv
# API keys (see section below)
DEEPGRAM_API_KEY=
ANTHROPIC_API_KEY=

# ffmpeg — only needed if ffmpeg is not on your system PATH
FFMPEG_PATH=ffmpeg
FFPROBE_PATH=ffprobe
```

The `DB_CONNECTION=sqlite` line is already set and uses `database/database.sqlite`. To use **MySQL** instead, replace the `DB_CONNECTION=sqlite` block with:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=sales_call_qa
DB_USERNAME=root
DB_PASSWORD=
```

Then run migrations and seed demo data:

```bash
php artisan migrate --seed
```

---

## API keys

### Deepgram (required for real call transcription)

1. Create a free account at [console.deepgram.com](https://console.deepgram.com) — free credit is included.
2. Generate an API key and set `DEEPGRAM_API_KEY=` in `.env`.

### Anthropic Claude (optional — for AI scoring)

Set `ANTHROPIC_API_KEY=` in `.env` (a key starting `sk-ant-`). Without it, the app uses a free rule-based scorer: it checks the real transcript for each criterion, quotes the matching line as evidence, and labels every result "Rule-based check, no AI". Tone is always flagged for human review.

### No keys at all (demo mode)

```dotenv
QA_FAKE_AI=true
```

The app uses fixture transcription and fake scoring: any uploaded audio file gets the same sample transcript, and every criterion passes. The full UI is clickable, including overrides and the results page.

---

## Running the app

Two processes must run simultaneously:

```bash
# Terminal 1 — web server
php artisan serve

# Terminal 2 — queue worker (handles call processing)
php artisan queue:work --timeout=900
```

Open [http://localhost:8000](http://localhost:8000) and log in (see **Demo logins** below).

---

## Sample data

No call recordings or QA criteria were provided. All 18 sample calls are synthetic:

- Scripts written from scratch, covering good calls, specific compliance failures, and durations from 1 to 50 minutes
- Audio generated with Microsoft edge-tts, so the correct outcome for each call is known and can be measured with `php artisan qa:eval`
- 16 calls are UK-style (British voices, £); calls #15 and #17 are US live-transfer calls (American voices, $, a licensed agent from a fictional agency), modelled on a US agency using a platform such as INSURVAS
- The sample criteria (`docs/example_criteria.md`) are based on common life-insurance sales compliance, including the cancellation (cooling-off) period: 30 days for UK life insurance under FCA ICOBS 7.1, or the US free-look period. A QA manager can replace them in the template builder

**Limitation:** synthetic audio is cleaner than real calls — no background noise, accents, or overlapping speech.

### Regenerating sample audio

Audio is not in the repository (too large, and privacy-sensitive in a real deployment). Requires edge-tts and ffmpeg:

```bash
pip install edge-tts
python scripts/make_sample_calls.py          # generates samples/ folder
php artisan db:seed --class=SampleCallSeeder # loads into DB and queues processing
```

The queue worker must be running to transcribe and score them.

---

## Demo logins

| Role | Email | Password |
|---|---|---|
| Admin | admin@example.com | password |
| Manager | manager@example.com | password |

**Agents** (data records, not login accounts): Sarah Mitchell, James O'Brien, Emma Clarke, Daniel Hughes, Priya Sharma.

---

## Running tests

```bash
php artisan test
```

Tests use an in-memory SQLite database — no MySQL required. 200+ tests, about 15–40 seconds. No real API is called: Deepgram and Anthropic are mocked with `Http::fake()`.

---

## ffmpeg on Windows

If ffmpeg is not on your system `PATH` (common with WinGet installs), set the full paths in `.env` using forward slashes — backslashes are treated as escape sequences:

```dotenv
FFMPEG_PATH=C:/Users/you/AppData/Local/Microsoft/WinGet/.../ffmpeg.exe
FFPROBE_PATH=C:/Users/you/AppData/Local/Microsoft/WinGet/.../ffprobe.exe
```

---

## Artisan commands

### `php artisan qa:eval`

Evaluates AI scoring accuracy against a hand-labelled golden set.

```bash
php artisan qa:eval                              # default golden set
php artisan qa:eval --golden=tests/golden/my_set.json
php artisan qa:eval --template="Standard Life Insurance Sales Call"
```

Exits `0` if overall accuracy ≥ 80%, `1` otherwise — usable in CI.
See `docs/golden_set.md` for the file format.

### `php artisan qa:stats`

Shows aggregate stats from the live database: totals, pass/fail/auto-fail counts, pass rate per template, the most-failed criteria, and the override rate per criterion (how often managers changed the result — a high rate means the criterion's wording needs fixing).

```bash
php artisan qa:stats            # last 30 days
php artisan qa:stats --days=0   # all time
php artisan qa:stats --template="Standard Life Insurance Sales Call"
```

---

## Sharing a public link (optional)

To let someone try the app running on your machine, expose port 8000 with a tunnel such as [ngrok](https://ngrok.com):

```bash
ngrok http 8000
```

The app already trusts proxy headers (`bootstrap/app.php`), so styles and links work over the tunnel's https address. Change the demo passwords first: anyone with the link can reach the login page.

---

## Upload limits

For calls longer than a few minutes, ensure your PHP and web-server limits allow large uploads:

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
  Http/Controllers/   Call, Template, Override, Agent, Dashboard, Stats
  Jobs/               ProcessCallJob
  Models/             Call, Agent, Template, Criterion, CriterionResult, Override, TranscriptSegment
  Policies/           CallPolicy, TemplatePolicy, OverridePolicy
  Services/           TranscriptionService, ScoringService (+ Fake/Claude/Deepgram/Keyword impls)
  Support/            AudioNormaliser, EvidenceVerifier, ScoreCalculator
docs/
  golden_set.md       Golden set format spec
  ai_notes.md         Log of AI mistakes and how they were caught
  ARCHITECTURE.md     Components, data flow, and how to make common changes
  WRITEUP.md          One-page write-up
  sample_scripts/     Call transcripts used to generate sample audio
tests/
  Feature/            HTTP + job integration tests
  Unit/               EvidenceVerifier, ScoreCalculator, keyword scorer, speaker guess
  golden/             golden_set.json (labelled eval cases)
scripts/
  make_sample_calls.py   Generate sample MP3s with edge-tts + ffmpeg
```
