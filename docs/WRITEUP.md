# Sales Call QA Agent — Write-up

I built a tool where a manager creates a QA template, uploads a sales call, and gets every criterion scored with the exact sentence from the call as evidence; clicking it plays that moment. A manager can override any score with a reason, and every change is kept. Laravel 13, a background queue (ffmpeg → Deepgram → scoring), and over 200 automated tests.

**Assumptions.** No recordings or criteria were provided, so I wrote 13 criteria based on common life-insurance sales rules and 18 call scripts (good calls and specific failures, 55 seconds to 48 minutes), turned into audio with text-to-speech. Two are American live-transfer calls, modelled on an agency using a platform like INSURVAS. Because I wrote the scripts, I know the right answer for every call. English only, calls up to 50 minutes.

## 1. How I designed templates so any criteria can be checked

A criterion is **data, not code**: a plain-English description of what good looks like, optional fail conditions, a type (pass/fail, yes/no/N-A, a 1–5 scale, or "extract" to record a fact like the premium), a weight, a "critical" flag, and a hint about where in the call to look. The scoring prompt and its JSON schema are built from these rows, so a new criterion needs no code change. A failed critical criterion (e.g. the recording notice) fails the whole call; N/A answers are left out of the score. Each result keeps a copy of its criterion, so editing a template never changes past results.

## 2. Where my tool gets it wrong, and how I'd catch it

- **My test audio is too clean.** Real calls have noise, accents and overlapping speech, so transcripts and speaker labels will be worse. A manager can swap agent and customer with one click.
- **The free scorer** (used without an Anthropic key) only knows phrasings it has seen. It missed American wording until I added it, and it cannot judge tone, so tone is always flagged for a human.
- **AI can invent evidence**, so every Claude quote is checked against the transcript; weak evidence lowers the confidence.
- **The pass mark can hide problems:** three calls that skip a non-critical step still pass at over 80%.
- **How I catch it:** uncertain or critical-fail results are flagged for review. `qa:eval` compares the scores with my own answers for 5 real transcripts: **52 of 55 (95%)**. It passed an agent who gave only his first name, and counted "regardless of your health history" as a health question. False passes are counted separately, because they let bad sales through. `qa:stats` shows how often managers change each criterion, which points to criteria that need rewording.

## 3. What I'd build next with another month

- Test on **real recordings** and improve transcription and speaker detection with them.
- Run **Claude scoring** on the golden set and compare it with the rule-based scorer.
- **Hide personal data** (card numbers, dates of birth) in transcripts.
- **Calibration:** several reviewers score the same call to measure agreement.
- Use the scores in the business: more live transfers to agents who pass compliance, and alerts on auto-fails.

## 4. Where AI helped me, and where it got things wrong

AI helped most with boilerplate, tests and the interface, so I could focus on scoring and the review flow. It also made mistakes; I logged all 22 in `docs/ai_notes.md`. The biggest: Deepgram needed `Token` instead of `Bearer` authentication, so real transcription never worked at first; the audio route had the wrong return type, so no call would play; it deleted my real database during a setup check; it wrote a 14-day cooling-off period for UK life insurance, which is actually 30 days; and the golden set did not match the template, so `qa:eval` measured nothing. I caught them by listening to calls next to their transcripts, reading logs, installing from a fresh clone, and checking facts against the source. My lesson: AI is fast, but every result needs checking against real data.
