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

<!-- Add new entries below as they occur. -->
