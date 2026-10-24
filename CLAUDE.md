# CLAUDE.md – Ground Rules

## Code quality
- Simple, conventional Laravel code only. No clever abstractions, no unnecessary packages, no premature generalisation.
- Every line must be explainable in a live 30-minute call.
- Default to no comments. Only add one when the WHY is non-obvious.

## Commits
- One feature per commit, clear messages (conventional commits: `feat:`, `fix:`, `test:`, `docs:`).
- Full history is reviewed — every commit must be meaningful.
- Run the test suite after every step. Do not move on with failing tests.

## Secrets and data
- Never commit `.env`, API keys, audio files, or transcripts.
- `samples/`, `storage/app/audio/`, and `.env` are in `.gitignore`.
- Never run `migrate:fresh`, `migrate:reset` or `db:wipe` against the real database. Test setup steps in a separate clone or database.

## AI notes
- Log every AI mistake in `docs/ai_notes.md` (wrong API params, bugs, bad assumptions).
- Be honest and specific — this feeds the write-up.

## Step summaries
- After every step: 3–5 lines on what was done and which files matter.

## Stack
- Laravel 13 (PHP 8.3), Blade + Alpine.js + Tailwind, Laravel Breeze.
- Database: SQLite by default (`.env.example`); MySQL locally (DB: `sales_call_qa`).
- Tests use in-memory SQLite (`DB_CONNECTION=sqlite`, `:memory:`).
- Queue driver: database. Job timeout 900 s, tries 2.
- Services: `TranscriptionService` / `ScoringService` interfaces. Real: Deepgram + Claude (or the free `KeywordScoringService` when no Anthropic key is set). Fake implementations behind `QA_FAKE_AI=true`.
- Do not install Laravel Boost or other tooling packages.
- On Windows/Laragon the default `php` is 8.1; use `C:\laragon\bin\php\php-8.3\php.exe`.

## Environment variables (add to .env, never commit values)
- `DEEPGRAM_API_KEY=`
- `ANTHROPIC_API_KEY=`
- `ANTHROPIC_MODEL=`
- `QA_LANGUAGE=en`
- `QA_COMPANY_NAME=INSURVAS`
- `QA_FAKE_AI=false`

## Privacy
- Audio on private disk; served only via authenticated route.
- Never log transcript text.
- Send call data only to Deepgram and Anthropic.
- "Delete call" removes audio, transcript and results.
