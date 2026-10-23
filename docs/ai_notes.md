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

<!-- Add new entries below as they occur. -->
