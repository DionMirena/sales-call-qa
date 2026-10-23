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

## AI notes
- Log every AI mistake in `docs/ai_notes.md` (wrong API params, bugs, bad assumptions).
- Be honest and specific — this feeds the write-up.

## Checkpoints
- Stop at each CHECKPOINT in the build plan and wait for "continue".

## Step summaries
- After every step: 3–5 lines on what was done and which files matter.

## Stack
- Laravel (latest stable), MySQL (local, DB: `sales_call_qa`), Blade + Alpine.js + Tailwind, Laravel Breeze.
- Tests use in-memory SQLite (`DB_CONNECTION=sqlite`, `:memory:`).
- Queue driver: database. Job timeout 600 s, tries 2.
- Services: `TranscriptionService` / `ScoringService` interfaces, fake implementations behind `QA_FAKE_AI=true`.

## Environment variables (add to .env, never commit values)
- `DEEPGRAM_API_KEY=`
- `ANTHROPIC_API_KEY=`
- `ANTHROPIC_MODEL=`
- `QA_LANGUAGE=en`
- `QA_FAKE_AI=false`

## Privacy
- Audio on private disk; served only via authenticated route.
- Never log transcript text.
- Send call data only to Deepgram and Anthropic.
- "Delete call" removes audio, transcript and results.
