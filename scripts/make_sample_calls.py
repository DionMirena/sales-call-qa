"""
Generate 4 synthetic test calls as mono 16 kHz MP3 files in samples/.
Uses edge-tts for TTS and ffmpeg for mixing.

Usage:
    python scripts/make_sample_calls.py

Requirements:
    pip install edge-tts
    ffmpeg in PATH (or set FFMPEG env var to full path)
"""

import asyncio
import os
import subprocess
import sys
from pathlib import Path

AGENT_VOICE = "en-GB-SoniaNeural"
CUSTOMER_VOICE = "en-GB-RyanNeural"
FFMPEG = os.environ.get("FFMPEG", "ffmpeg")

SCRIPTS_DIR = Path(__file__).parent.parent / "docs" / "sample_scripts"
SAMPLES_DIR = Path(__file__).parent.parent / "samples"

CALLS = [
    {
        "name": "good_call",
        "script": "call_good.txt",
        "description": "Complete compliant call — should pass all criteria",
    },
    {
        "name": "no_disclosure",
        "script": "call_no_disclosure.txt",
        "description": "Missing call-recording disclosure — critical fail",
    },
    {
        "name": "pressure_tactics",
        "script": "call_pressure_tactics.txt",
        "description": "Guaranteed approval + pressure tactics — two critical fails",
    },
    {
        "name": "long_call",
        "script": "call_long.txt",
        "description": "Long (~15 min) compliant call with full needs discovery",
    },
    # ---- 10 new varied calls ----
    {
        "name": "compliant_full",
        "script": "call_compliant_full.txt",
        "description": "Emma Clarke — full discovery, all criteria met — pass",
    },
    {
        "name": "objection_handling",
        "script": "call_objection_handling.txt",
        "description": "James O'Brien — sceptical customer, objections handled — pass",
    },
    {
        "name": "near_perfect",
        "script": "call_near_perfect.txt",
        "description": "Sarah Mitchell — near-perfect call, detailed discovery — pass",
    },
    {
        "name": "no_recording_notice",
        "script": "call_no_recording_notice.txt",
        "description": "Priya Sharma — missing call-recording disclosure — auto-fail",
    },
    {
        "name": "guaranteed_approval_2",
        "script": "call_guaranteed_approval_2.txt",
        "description": "Daniel Hughes — guaranteed-approval claims — auto-fail",
    },
    {
        "name": "pressure_subtle",
        "script": "call_pressure_subtle.txt",
        "description": "Emma Clarke — subtle 'enhanced rate this week' pressure — auto-fail",
    },
    {
        "name": "no_cooling_off",
        "script": "call_no_cooling_off.txt",
        "description": "James O'Brien — never explicitly states 14-day cooling-off period — fail",
    },
    {
        "name": "no_next_steps",
        "script": "call_no_next_steps.txt",
        "description": "Sarah Mitchell — vague handoff, no clear next steps — fail",
    },
    {
        "name": "incomplete_discovery",
        "script": "call_incomplete_discovery.txt",
        "description": "Priya Sharma — skips dependants, existing cover, budget — fail",
    },
    {
        "name": "thorough_discovery",
        "script": "call_thorough_discovery.txt",
        "description": "Daniel Hughes — exceptionally thorough needs discovery — pass",
    },
]


def parse_script(text: str) -> list[dict]:
    """Return list of {speaker, line} dicts."""
    lines = []
    for raw in text.strip().splitlines():
        raw = raw.strip()
        if not raw:
            continue
        if raw.startswith("AGENT:"):
            lines.append({"speaker": "AGENT", "text": raw[6:].strip()})
        elif raw.startswith("CUSTOMER:"):
            lines.append({"speaker": "CUSTOMER", "text": raw[9:].strip()})
    return lines


async def tts_line(text: str, voice: str, out_path: str) -> None:
    import edge_tts
    communicate = edge_tts.Communicate(text, voice)
    await communicate.save(out_path)


async def generate_call(call: dict, tmp_dir: Path) -> Path:
    script_path = SCRIPTS_DIR / call["script"]
    lines = parse_script(script_path.read_text(encoding="utf-8"))

    print(f"  Generating {len(lines)} lines for '{call['name']}'...")

    # Generate each line as a separate MP3
    segment_files = []
    for i, line in enumerate(lines):
        voice = AGENT_VOICE if line["speaker"] == "AGENT" else CUSTOMER_VOICE
        seg_path = str(tmp_dir / f"{call['name']}_{i:04d}.mp3")
        await tts_line(line["text"], voice, seg_path)
        segment_files.append(seg_path)
        if (i + 1) % 5 == 0:
            print(f"    {i + 1}/{len(lines)} segments done")

    # Concatenate with ffmpeg
    list_file = tmp_dir / f"{call['name']}_list.txt"
    list_file.write_text(
        "\n".join(f"file '{f}'" for f in segment_files), encoding="utf-8"
    )

    out_path = SAMPLES_DIR / f"{call['name']}.mp3"
    cmd = [
        FFMPEG, "-y",
        "-f", "concat", "-safe", "0",
        "-i", str(list_file),
        "-ac", "1",          # mono
        "-ar", "16000",      # 16 kHz
        "-b:a", "64k",
        str(out_path),
    ]
    result = subprocess.run(cmd, capture_output=True, text=True)
    if result.returncode != 0:
        print(f"  ERROR: ffmpeg failed\n{result.stderr}")
        sys.exit(1)

    # Clean up temp segments
    for f in segment_files:
        Path(f).unlink(missing_ok=True)
    list_file.unlink(missing_ok=True)

    size_kb = out_path.stat().st_size // 1024
    print(f"  -> {out_path.name} ({size_kb} KB)")
    return out_path


def probe_duration(path: Path) -> str:
    """Return human-readable duration using ffprobe."""
    ffprobe = os.environ.get("FFPROBE", FFMPEG.replace("ffmpeg.exe", "ffprobe.exe").replace("/ffmpeg", "/ffprobe"))
    cmd = [
        ffprobe, "-v", "error",
        "-show_entries", "format=duration",
        "-of", "default=noprint_wrappers=1:nokey=1",
        str(path),
    ]
    result = subprocess.run(cmd, capture_output=True, text=True)
    try:
        secs = float(result.stdout.strip())
        m, s = divmod(int(secs), 60)
        return f"{m}m {s}s"
    except Exception:
        return "unknown"


async def main() -> None:
    import tempfile

    SAMPLES_DIR.mkdir(exist_ok=True)
    tmp_dir = Path(tempfile.mkdtemp())

    print(f"Output directory: {SAMPLES_DIR.resolve()}\n")

    # Optional: only regenerate the calls named on the command line, e.g. `good_call long_call`.
    wanted = set(sys.argv[1:])
    calls = [c for c in CALLS if not wanted or c["name"] in wanted]

    for call in calls:
        print(f"[{call['name']}] {call['description']}")
        out = await generate_call(call, tmp_dir)
        duration = probe_duration(out)
        print(f"  Duration: {duration}\n")

    # Print summary table
    print("\n=== Generated samples ===")
    print(f"{'File':<30} {'Size':>8} {'Duration':>10}  Description")
    print("-" * 80)
    for call in calls:
        p = SAMPLES_DIR / f"{call['name']}.mp3"
        if p.exists():
            size = f"{p.stat().st_size // 1024} KB"
            dur = probe_duration(p)
            print(f"{p.name:<30} {size:>8} {dur:>10}  {call['description']}")


if __name__ == "__main__":
    asyncio.run(main())
