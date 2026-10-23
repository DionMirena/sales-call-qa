# Sales Call QA Agent

A Laravel tool for QA managers to evaluate life insurance sales calls against custom templates. Managers create criteria templates, upload call recordings, and the system transcribes and scores each call using AI — with per-criterion evidence and a human override workflow.

## Requirements

- PHP 8.3+ (with `pdo_sqlite`, `pdo_mysql`, `fileinfo`, `mbstring`, `curl`, `zip`)
- Composer 2.x
- Node.js 18+ and npm
- ffmpeg (any recent version)
- MySQL 8.x (local)
- Git

## Setup (under 10 commands)

```bash
git clone <repo-url> sales-call-qa
cd sales-call-qa
cp .env.example .env
# Fill in DB_PASSWORD, DEEPGRAM_API_KEY, ANTHROPIC_API_KEY in .env
# (or set QA_FAKE_AI=true to skip API keys entirely)
composer install
php artisan key:generate
php artisan migrate
php artisan db:seed
npm install && npm run build
php artisan serve
```

Then open http://localhost:8000.

## Environment variables

| Variable | Required | Description |
|---|---|---|
| `DB_PASSWORD` | Yes | MySQL root password (leave blank if no password) |
| `DEEPGRAM_API_KEY` | Yes (or `QA_FAKE_AI=true`) | Pre-recorded transcription API |
| `ANTHROPIC_API_KEY` | Yes (or `QA_FAKE_AI=true`) | Scoring via Claude |
| `ANTHROPIC_MODEL` | No | Default: `claude-sonnet-4-6` |
| `QA_LANGUAGE` | No | Default: `en` (passed to Deepgram) |
| `QA_FAKE_AI` | No | Set `true` to use fixture data (no API keys needed) |

**File location:** `.env` in the project root. Never commit it — it is git-ignored.

## Demo mode (no API keys required)

Set `QA_FAKE_AI=true` in `.env` to use `FakeTranscriptionService` and `FakeScoringService`.
You can click through the full app — upload, transcribe, score, override — without any API credentials.

## Seeded logins

| Role | Email | Password |
|---|---|---|
| Admin | admin@example.com | password |
| Manager | manager@example.com | password |

**Agents** (data records, not login accounts): Sarah Mitchell, James O'Brien, Emma Clarke, Daniel Hughes, Priya Sharma.

## Running the queue

The call processing pipeline (normalise → transcribe → score) runs as a queued job.

```bash
php artisan queue:work --timeout=600
```

Or run everything (server + queue + Vite) in one command:

```bash
composer run dev
```

## Running the tests

```bash
php artisan test
# or
./vendor/bin/pest
```

Tests use an in-memory SQLite database — no MySQL required for CI.

## Evaluation commands

```bash
# Compare AI scores against your hand-labelled golden set
php artisan qa:eval

# Show per-criterion override rates (signals which criteria need rewording)
php artisan qa:stats
```

## Upload limits

For calls longer than a few minutes, ensure your PHP and web-server limits allow large uploads:

- `upload_max_filesize = 1G`
- `post_max_size = 1G`
- nginx: `client_max_body_size 1G`

Set in `php.ini` or a `.htaccess` / nginx server block.
