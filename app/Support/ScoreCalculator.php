<?php

namespace App\Support;

use App\Models\CriterionResult;
use App\Models\Template;
use Illuminate\Support\Collection;

class ScoreCalculator
{
    /**
     * Calculate score_percent and outcome for a call.
     *
     * @param  Collection<CriterionResult>  $results
     * @return array{0: float|null, 1: string}  [score_percent, outcome]
     */
    public static function calculate(Template $template, Collection $results): array
    {
        $autoFail      = false;
        $weightedSum   = 0.0;
        $totalWeight   = 0;

        foreach ($results as $result) {
            $effective = $result->effectiveResult();
            $type      = $result->criterion_type;
            $weight    = $result->criterion_weight;
            $critical  = $result->criterion_is_critical;

            // extract is informational only — never scored
            if ($type === 'extract') {
                continue;
            }

            // N/A responses are excluded from both numerator and denominator
            if ($effective === 'na') {
                continue;
            }

            $value = self::resultToValue($type, $effective);

            // Check auto-fail: critical criterion failed
            if ($critical && in_array($effective, ['fail', 'no'], true)) {
                $autoFail = true;
            }

            $weightedSum += $weight * $value;
            $totalWeight += $weight;
        }

        if ($totalWeight === 0) {
            return [null, 'pass'];
        }

        $scorePercent = ($weightedSum / $totalWeight) * 100;

        if ($autoFail) {
            return [round($scorePercent, 2), 'auto_fail'];
        }

        $outcome = $scorePercent >= $template->pass_threshold ? 'pass' : 'fail';

        return [round($scorePercent, 2), $outcome];
    }

    private static function resultToValue(string $type, string $result): float
    {
        return match ($type) {
            'pass_fail' => $result === 'pass' ? 1.0 : 0.0,
            'yes_no_na' => $result === 'yes'  ? 1.0 : 0.0,
            'scale_1_5' => ((int) $result - 1) / 4,
            default     => 0.0,
        };
    }
}
