# Golden Set Format

The golden set is a JSON file used by `php artisan qa:eval` to measure AI scoring accuracy.
Each case contains a self-contained transcript and human-labelled expected results for every
criterion.

## File location

`tests/golden/golden_set.json`

## Schema

```json
{
  "version": 1,
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
| `version` | yes | Always `1` for now |
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
php artisan qa:eval --template="Standard Insurance QA"

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
