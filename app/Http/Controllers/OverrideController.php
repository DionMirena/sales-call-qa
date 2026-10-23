<?php

namespace App\Http\Controllers;

use App\Models\CriterionResult;
use App\Support\ScoreCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OverrideController extends Controller
{
    public function store(Request $request, CriterionResult $criterionResult): RedirectResponse
    {
        $this->authorize('create', $criterionResult);

        $bag = "cr_{$criterionResult->id}";

        $request->validateWithBag($bag, [
            'new_result' => ['required', 'string', 'max:255'],
            'reason'     => ['required', 'string', 'min:10', 'max:1000'],
        ]);

        $type      = $criterionResult->criterion_type;
        $newResult = $request->new_result;

        $allowed = match ($type) {
            'pass_fail' => ['pass', 'fail'],
            'yes_no_na' => ['yes', 'no', 'na'],
            'scale_1_5' => ['1', '2', '3', '4', '5'],
            default     => null,
        };

        if ($allowed !== null && ! in_array($newResult, $allowed, true)) {
            return back()->withErrors(
                ['new_result' => 'Invalid result value for this criterion type.'],
                $bag
            );
        }

        $criterionResult->overrides()->create([
            'user_id'    => $request->user()->id,
            'old_result' => $criterionResult->effectiveResult(),
            'old_score'  => $criterionResult->effectiveScore(),
            'new_result' => $newResult,
            'new_score'  => $this->normaliseScore($type, $newResult),
            'reason'     => $request->reason,
        ]);

        // Mark criterion as reviewed
        $criterionResult->update(['needs_review' => false]);

        // Recalculate call score using fresh results with overrides
        $criterionResult->load('call.template');
        $call    = $criterionResult->call;
        $results = $call->criterionResults()->with('overrides')->get();

        [$scorePercent, $outcome] = ScoreCalculator::calculate($call->template, $results);
        $call->update(['score_percent' => $scorePercent, 'outcome' => $outcome]);

        return redirect()->route('calls.show', $call)
            ->with('success', 'Override saved — score recalculated.');
    }

    private function normaliseScore(string $type, string $result): ?float
    {
        return match ($type) {
            'scale_1_5' => ((int) $result - 1) / 4,
            'pass_fail' => $result === 'pass' ? 1.0 : 0.0,
            'yes_no_na' => match ($result) {
                'yes'   => 1.0,
                'no'    => 0.0,
                default => null,
            },
            default => null,
        };
    }
}
