# Golden Set Format

The golden set is a JSON file used by `php artisan qa:eval` to measure AI scoring accuracy.
Each case contains a self-contained transcript and human-labelled expected results for every
criterion.

## File location

`tests/golden/golden_set.json`

## What is in it

Five sample calls, using their **real Deepgram transcripts** (agent normalised to speaker 0), labelled by hand from each call's script. The labels are deliberately strict: they record what the criterion asks for, not what the scorer happens to output.

| Case | Call | What it tests |
|---|---|---|
| gs_001 | `good_call.mp3` (#15) | US live transfer, fully compliant |
| gs_002 | `no_disclosure.mp3` (#17) | US live transfer: no recording notice, first name only, no discovery, no free-look, vague close |
| gs_003 | `no_recording_notice.mp3` (#4) | UK call, good except the missing recording notice |
| gs_004 | `guaranteed_approval_2.mp3` (#5) | UK call: guaranteed-approval claims, almost no discovery |
| gs_005 | `pressure_subtle.mp3` (#6) | UK call: complete discovery but subtle urgency |

The 11 pass/fail and yes/no criteria are labelled for every case (55 labels). Tone (scale 1–5) and the premium extract are left out: tone is subjective, and the extract is informational.

## Current result (rule-based scorer)

`php artisan qa:eval` → **52/55 correct (95%)**, 2 false passes, 1 false fail:

- gs_002, Agent Identification — expected **fail** (first name only), scored pass. The scorer accepts "This is James" as an introduction.
- gs_004, Age and Health — expected **fail** (health never asked), scored pass. The agent says "regardless of your *health* history" while dismissing it; the scorer only sees the word.
- gs_004, Cooling-off — expected **pass** ("a standard thirty day cancellation window"), scored fail. No refund is mentioned, which the scorer's pattern requires.

These are kept as honest examples of the scorer's limits rather than tuned away; tuning a scorer to its own test set would make the number meaningless.

A test (`QaEvalTest::test_golden_set_matches_the_seeded_template`) checks that every labelled criterion exists in the seeded template, so the set cannot silently drift from the template again.

## Schema

```json
{
  "version": 2,
  "template_name": "<name of the QA template in the database>",
  "description": "<human-readable description of this set>",
  "cases": [
    {
      "id": "<unique string, e.g. gs_001>",
      "description": "<what makes this call interesting>",
      "segments": [
        {
          "speaker": 0,
          "start_ms": 1000,
          "end_ms": 8000,
          "text": "<verbatim transcript text for this turn>"
        }
      ],
      "expected": [
        {
          "criterion_name": "<must match the criterion name on the template exactly>",
          "result": "<pass|fail|yes|no|na|1|2|3|4|5|<extracted text>>",
          "notes": "<optional: human reasoning for this label>"
        }
      ]
    }
  ]
}
```

## Field notes

| Field | Required | Description |
|---|---|---|
| `version` | yes | `2` (real transcripts, labels for the current template) |
| `template_name` | yes | Must match a template name in the live database |
| `cases[].id` | yes | Unique within the file; used in output tables |
| `cases[].segments[].speaker` | yes | `0` = agent, `1` = customer (same convention as calls) |
| `cases[].expected[].criterion_name` | yes | Must exactly match the criterion name on the template |
| `cases[].expected[].result` | yes | Valid values depend on criterion type |
| `cases[].expected[].notes` | no | Useful for audit trail — explains why this label was chosen |

## Criterion types and valid `result` values

| Type | Valid values |
|---|---|
| `pass_fail` | `pass`, `fail` |
| `yes_no_na` | `yes`, `no`, `na` |
| `scale_1_5` | `1`, `2`, `3`, `4`, `5` |
| `extract` | any string (exact expected extraction) |

## Running the eval

```bash
# Against the default golden set, using the template name embedded in the file
php artisan qa:eval

# Specify a different golden set file
php artisan qa:eval --golden=tests/golden/my_set.json

# Override the template name
php artisan qa:eval --template="Standard Life Insurance Sales Call"

# Demo mode (fake AI — useful for testing the command itself)
QA_FAKE_AI=true php artisan qa:eval
```

## Accuracy target

The `qa:eval` command exits with code `0` if overall accuracy is ≥ 80%, and `1` otherwise.
This makes it usable in CI pipelines.

## Adding new cases

1. Choose a real call transcript (or write a synthetic one).
2. Label every criterion manually — be specific in `notes` about where in the transcript
   the evidence is (or isn't).
3. Add the case to the JSON file.
4. Run `qa:eval` to see how the AI performs against your labels.
5. If the AI gets it wrong, document the failure in `docs/ai_notes.md`.
