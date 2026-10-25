# AI Notes – Mistakes and How They Were Caught

This file records every mistake made by the AI assistant during this project: wrong API parameters, bugs, bad assumptions. It is a required section of the write-up, so entries are honest and specific.

---

## Phase S – Setup

### Mistake 1: `probe_duration` used string replace on full executable path
- **Date:** Phase S (setup)
- **What happened:** The `probe_duration` function in `scripts/make_sample_calls.py` derived the ffprobe path by calling `FFMPEG.replace("ffmpeg", "ffprobe")` on the full path string. The full path contained `ffmpeg` as part of the Windows package directory name (`Gyan.FFmpeg_Microsoft.Winget...`), so the replace hit the wrong occurrence and produced an invalid path, causing a `FileNotFoundError`.
- **How it was caught:** Runtime error during first execution of `make_sample_calls.py`. The traceback showed `_execute_child` failing with WinError 2 (file not found) when trying to run ffprobe.
- **Fix:** Changed to use a separate `FFPROBE` environment variable and fell back to replacing only `ffmpeg.exe` → `ffprobe.exe` at the filename level, not anywhere in the full path.
- **Lesson:** Never derive sibling binary paths by naive string replacement on a full path that may contain the binary name in directory components too.

---

## Phase 1 – Skeleton

### Mistake 2: Assumed Node.js 18 would work with Vite 6 / Laravel 13
- **What happened:** The project was installed successfully, but `npm run build` failed with `SyntaxError: The requested module 'node:util' does not provide an export named 'styleText'`. Node.js 18.8.0 was installed on the machine; `styleText` was added to Node.js `util` in v20.12.0.
- **How it was caught:** `npm run build` error during Phase 1 after noticing 7 test failures all caused by a missing Vite manifest.
- **Fix:** Installed Node.js 24 LTS via winget. Also added `$this->withoutVite()` to `tests/TestCase.php` so tests never require a build artefact, making the test suite faster and independent of front-end compilation.
- **Lesson:** Check Node.js version compatibility with the framework's front-end toolchain. Laravel 13 requires Node 20+. Always add `withoutVite()` to base TestCase on any Breeze/Vite project.

## Phase 2 – Templates

### Mistake 3: `authorize()` not available — Laravel 13 removed it from base Controller
- **What happened:** `TemplateController::authorize()` threw `Call to undefined method`. Laravel 13 removed `AuthorizesRequests` from the base `Controller` class; controllers must opt in.
- **How it was caught:** 6 test failures on the first test run, all with the same 500 traceback pointing to `TemplateController.php`.
- **Fix:** Added `use AuthorizesRequests;` to `app/Http/Controllers/Controller.php`.
- **Lesson:** When upgrading or starting fresh on Laravel 11+, always check whether `AuthorizesRequests` and `ValidatesRequests` have been removed from the base controller. Add them explicitly if needed.

---

## Week 2 – Real transcription, demo data and sharing

### Mistake 4: Audio route declared the wrong return type, so no call could be played
- **What happened:** `CallController::audio()` was typed `: Response` but returns `response()->file()`, which is a `BinaryFileResponse`. PHP threw a `TypeError`, so the player showed `0:00` and never loaded.
- **How it was caught:** I reported the player would not play; the TypeError was in `storage/logs/laravel.log`. No test had ever requested the audio route.
- **Fix:** Return type changed to `BinaryFileResponse`; tests added for streaming and for downloading the file.
- **Lesson:** Every route needs at least one test that actually calls it, not only its auth check.

### Mistake 5: Deepgram authentication used the wrong scheme
- **What happened:** `Http::withToken($key)` sends `Authorization: Bearer <key>`. Deepgram API keys must be sent as `Token <key>`, so every request failed with 401 "Invalid credentials". Real transcription had never worked.
- **How it was caught:** A valid key still returned 401. Calling the API directly with curl and `Token <key>` returned 200, which proved the code was wrong, not the key.
- **Fix:** `Http::withToken($key, 'Token')`, plus a test that asserts the header.
- **Lesson:** Check the provider's exact auth scheme; a mocked test cannot catch a wrong header unless it asserts it.

### Mistake 6: Deepgram options were silently dropped
- **What happened:** Diarization, punctuation and language were passed as the `post()` data array while the body was the raw audio. Laravel ignores that array when a raw body is set, so the options never reached Deepgram.
- **How it was caught:** Reading the request code while fixing Mistake 5.
- **Fix:** Moved the options to `withQueryParameters()`; the test asserts they appear in the URL.
- **Lesson:** When the body is a file, every option must go in the query string.

### Mistake 7: Demo calls were processed in fake mode, and I misread the cause
- **What happened:** The first processed calls had been run through the fake transcription service during an end-to-end test, so every call showed the same canned transcript with made-up timings. The 8:47 call showed a 2:43 transcript. When diagnosing it, I first claimed `QA_FAKE_AI=true` was set in `.env`; it was actually `false`.
- **How it was caught:** I heard that the audio did not match the transcript. Two different calls had identical transcripts with round-number timings. Re-checking `.env` showed my claim was wrong.
- **Fix:** Re-processed every call with real Deepgram transcription; corrected the wrong statement.
- **Lesson:** Demo data must come from the real pipeline. Verify a setting before stating it as the cause.

### Mistake 8: A regex without the Unicode flag left calls stuck at "scoring"
- **What happened:** The premium-extraction regex in `KeywordScoringService` had no `/u` flag, so it cut the two-byte `£` sign in half. The invalid UTF-8 string was rejected by the database and the job stopped mid-scoring.
- **How it was caught:** A unit test built from a real transcript returned `'�36'` instead of `'£36 a month'`.
- **Fix:** Added `/u` to the money regex, and read into the next line for quotes split across two segments.
- **Lesson:** Any regex that may meet currency symbols or accents needs `/u`.

### Mistake 9: The first rule-based scorer was too naive
- **What happened:** The first version of `KeywordScoringService` matched single keywords. It failed all three opening checks on a perfect call, flagged "There's no rush to decide today" as pressure (the negation check looked only one word back), and missed "currently have any life insurance", "160,000" and "twelve years".
- **How it was caught:** Comparing each result with what was actually said in the transcript, line by line.
- **Fix:** Phrase patterns for common call steps, a wider negation window, and a test for each missed phrasing, using a real transcript as test data.
- **Lesson:** Check a scorer against the real call, not against whether it runs.

### Mistake 10: Call length was rounded up
- **What happened:** Duration used `ceil()` on the last utterance, so the page said 02:34 while the browser player said 2:33.
- **How it was caught:** Comparing the page with the player.
- **Fix:** Use Deepgram's audio duration rounded down, like the player.

### Mistake 11: The results page polled four times as often as intended
- **What happened:** Alpine components had `x-init="init()"` although Alpine already calls `init()` automatically, and there was a second hidden status poller. A processing call was polled four times every three seconds.
- **How it was caught:** Reading the view while adding the speed control.
- **Fix:** Removed the duplicate `x-init` calls and the hidden poller.

### Mistake 12: Speaker detection matched any "this is …"
- **What happened:** The improved agent-speaker guess treated any line containing "this is" as an introduction, so "this is great" counted, and a customer answering "Hello, this is Tom" would be labelled the agent.
- **How it was caught:** Code review of the change.
- **Fix:** Check signals strongest first (company name, then "my name is", then "This is <Name>"), with tests for both cases.

### Mistake 13: A setup check wiped the real database
- **What happened:** While confirming that a fresh setup works, the AI ran `migrate:fresh --seed` against my real MySQL database. All 18 processed calls, with their transcripts and scores, were deleted.
- **How it was caught:** The calls list was empty; the users and template had all been re-created at the same minute.
- **Fix:** The audio files were still on disk. `SampleCallSeeder` was extended to cover all 18 samples, and every call was re-processed.
- **Lesson:** Never run destructive commands on the real database; test setup instructions against a separate database.

### Mistake 14: A sample call was mislabelled
- **What happened:** An end-to-end test created the 8:47 long call with the file name `good_call.mp3` and the wrong agent (Sarah instead of Emma).
- **How it was caught:** While re-processing all calls, the file stored on disk did not match the displayed name.
- **Fix:** Corrected the file name and the agent.

### Mistake 15: The scorer only understood British wording
- **What happened:** After two calls were rewritten as US live-transfer calls, the scorer missed "transferred over to me about …" as the reason for the call, and "whole life" (it only knew "whole of life") as a policy term.
- **How it was caught:** Reading the results of call #17 against its script.
- **Fix:** Added both phrasings with tests. Re-scored every call; only #17 changed.

### Mistake 16: The page had no styling behind ngrok
- **What happened:** Behind ngrok's https tunnel, Laravel built CSS and JS URLs as `http://localhost` because it did not trust the proxy headers. Browsers blocked them.
- **How it was caught:** Opening the public link showed an unstyled page.
- **Fix:** `$middleware->trustProxies(at: '*')` in `bootstrap/app.php`, with `TrustedProxyTest`.
- **Lesson:** Any app shared through a tunnel or load balancer must trust the forwarded headers.

### Mistake 17: A study guide described a feature that did not exist yet
- **What happened:** While writing my interview study guide, the AI described `qa:stats` as showing the override rate per criterion. The command did not do that, even though the original plan required it.
- **How it was caught:** Checking every claim in the guide against the code before finishing it.
- **Fix:** Corrected the guide, then built the feature with a test.
- **Lesson:** Verify documentation against the code; plans and reality drift apart.

### Mistake 18: The template stated the wrong cooling-off period
- **What happened:** The cancellation criterion and 13 call scripts said life insurance has a 14-day cooling-off period. Under FCA ICOBS 7.1, life insurance (a pure protection contract) has 30 days; 14 days applies to other insurance.
- **How it was caught:** A full review of the project before submission; the rule was checked against the FCA Handbook.
- **Fix:** The criterion now describes the cancellation period without a hard-coded number (30 days in the UK, the free-look period in the US). The 13 scripts say thirty days; their audio was regenerated and the calls re-processed. Scores were unchanged.
- **Lesson:** Domain facts in sample data need checking against the source, not just code.

### Mistake 19: The golden set did not match the template, so qa:eval measured nothing
- **What happened:** `tests/golden/golden_set.json` used a template name and criterion names ("Recording Disclosure", "Clear Close"…) that did not exist in the seeded template. `qa:eval` reported "Template not found", and with the right template name it scored 0/12 because every criterion was "not scored". Its tests passed only because they created their own matching template.
- **How it was caught:** Running `qa:eval` on the real app during the review.
- **Fix:** Rebuilt the golden set from five real call transcripts with 55 hand-written labels, and added a test that every labelled criterion exists in the seeded template. Result: 52/55 (95%).
- **Lesson:** Tests that build their own fixtures can hide a mismatch with the real data; test against the real seed too.

### Mistake 20: A fresh clone could not be installed
- **What happened:** The `bootstrap/cache` folder was never committed, so `composer install` on a fresh clone failed. The README also said the SQLite file is created automatically, which is only true for brand-new projects.
- **How it was caught:** Cloning the GitHub repository into an empty folder and following the README step by step.
- **Fix:** Committed `bootstrap/cache/.gitignore` (as Laravel's skeleton does) and added the SQLite file step to the README.
- **Lesson:** Test the setup from a clean clone, exactly as a reviewer would.

### Mistake 21: Agent pages showed managers other people's calls
- **What happened:** The calls list, dashboard and stats only show a manager their own calls, but `AgentController` was never filtered. A manager could see the scores, outcomes and failed criteria of every call for an agent, plus links to calls they were not allowed to open.
- **How it was caught:** Checking every page against the "managers only see their own calls" rule during the final review; the manager account had uploaded 0 calls but its agent pages showed 18.
- **Fix:** The same admin-or-uploader filter as the other pages, applied to the agent list counts, the agent stats and the recent-calls list, with a test for both roles.
- **Lesson:** A permission rule has to be checked on every page that reads the data, not only where the data is created.

### Mistake 22: ARCHITECTURE.md described routes and paths that do not exist
- **What happened:** It gave the wrong override URL (`/overrides/{id}`), audio URL (`/audio/{call}`) and storage folder (`storage/app/private`), a 600 s job timeout instead of 900 s, unique agent names, and scoring by Claude only.
- **How it was caught:** Reading the document line by line against the routes, migrations and config.
- **Fix:** Corrected every line.

### Mistake 23: Deleting a template crashed every page that showed its calls
- **What happened:** Templates use soft deletes so history is kept, but `Call::template()` did not include soft-deleted templates. After the template used by all 18 calls was deleted, the dashboard returned a 500 error ("Attempt to read property name on null"), and overrides and re-scoring on those calls would also have failed.
- **How it was caught:** The dashboard crashed while I was using the app through ngrok.
- **Fix:** `Call::template()` now uses `withTrashed()`, so a call keeps the template it was scored with. A test deletes a template and then loads the dashboard, calls list, call page and agent page, and saves an override.
- **Lesson:** When a model is soft-deleted, every relationship that points to it must decide whether it should still see it.

<!-- Add new entries below as they occur. -->
