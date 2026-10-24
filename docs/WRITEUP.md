# Sales Call QA Agent — Write-up

Managers build QA templates, upload a call, and get every criterion scored against the transcript, with the exact supporting line as clickable evidence and a human override with an audit trail. Laravel 13, a queue job (ffmpeg → Deepgram → scoring), 200+ tests.

**Assumptions.** No recordings or criteria were provided ("it's in your hands"), so I wrote 13 criteria from common life-insurance sales compliance and 18 call scripts (good calls, specific failures, 1–48 minutes), turned into audio with text-to-speech. Two are US live-transfer calls, modelled on a US agency using a platform like INSURVAS. Because I wrote the scripts, I know the right answer for every call. English only; calls up to 50 minutes; mono audio.

## 1. How templates are designed so any criteria can be checked

- A criterion is **data, not code**: a plain-English description of what good looks like, optional fail conditions, a type (pass/fail, yes/no/N-A, 1–5 scale, or "extract" to record a fact such as the premium), a weight, a critical flag, and a soft hint about where in the call to look.
- The scoring prompt and its JSON schema are **built from the template's rows**, so a new criterion needs no code change.
- **Critical** criteria auto-fail the call whatever the percentage; **N/A** is left out of the score.
- Each result **snapshots** the criterion, so editing a template never changes past results.

## 2. Where it gets it wrong, and how I'd catch it

- **Synthetic audio is too clean.** Real calls have noise, accents and people talking over each other, so transcripts and speaker labels will be worse. A manager can swap the speakers with one click.
- **The free rule-based scorer** (used when there is no Anthropic key) only knows phrasings it has seen. It missed US wording ("transferred over to me about…", "whole life") until I added it. Tone cannot be judged by rules, so it is always flagged.
- **AI scoring can invent evidence.** Every quote is checked against the transcript (exact, neighbouring lines, then word overlap); weak evidence lowers confidence.
- **How I catch it:** low-confidence, unverified or critical-fail results are flagged for review; `qa:eval` compares scores with hand-labelled answers and counts false passes (the dangerous kind) separately from false fails; `qa:stats` shows how often managers change each criterion, which points to criteria that need rewording.
- **Measured:** on a golden set of 5 real transcripts (55 labels) the rule-based scorer gets **52/55 (95%)**: it passed an agent who gave only his first name, counted "regardless of your health history" as a health question, and missed a cooling-off mention with no word "refund".

## 3. What I'd build next with another month

- Test on **real recordings** and tune transcription and speaker detection on them.
- Run **Claude scoring** on the golden set and compare it with the rule-based scorer.
- **Redact** card numbers and dates of birth from transcripts; add data-retention rules.
- **Calibration**: several reviewers score the same call to measure agreement.
- Feed agent scores into **lead routing and coaching** (e.g. more live transfers to agents who pass compliance), alerts on auto-fails, and exports for compliance reports.

## 4. Where AI helped, and where it got things wrong

- **Helped:** boilerplate, tests, the UI, and turning my plan into working code quickly.
- **Got wrong** (all in `docs/ai_notes.md`): Deepgram needed `Token` not `Bearer` auth, and its options were silently dropped; the audio route had the wrong return type, so nothing played; demo calls had been processed in fake mode; a regex without the Unicode flag broke on the £ sign; it wiped my real database during a setup check; it wrote the UK cooling-off period as 14 days when life insurance has 30; the app had no styling behind ngrok.
- **How I caught them:** listening to calls against their transcripts, reading logs, testing a fresh clone, and checking every claim against the code. Lesson: AI is fast, but every result needs checking against real data.
