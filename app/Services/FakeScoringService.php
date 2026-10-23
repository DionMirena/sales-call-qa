<?php

namespace App\Services;

use App\Models\Call;
use App\Support\ScoreCalculator;

class FakeScoringService implements ScoringService
{
    public function score(Call $call): void
    {
        $call->load(['template.criteria', 'segments']);
        $segments = $call->segments->keyBy('code');

        $resultsByCode = [];

        foreach ($call->template->criteria as $criterion) {
            // Fake result based on criterion type
            [$result, $score] = match ($criterion->type) {
                'pass_fail' => ['pass', 1.0],
                'yes_no_na' => ['yes', 1.0],
                'scale_1_5' => ['4', 0.75],
                'extract'   => ['£34 per month', null],
            };

            // Pick first available segment as fake evidence
            $firstSegment = $segments->first();
            $evidence = $firstSegment ? [[
                'segment_code' => $firstSegment->code,
                'quote'        => substr($firstSegment->text, 0, 60),
                'verified'     => true,
            ]] : [];

            $resultsByCode[$criterion->id] = $call->criterionResults()->create([
                'criterion_id'          => $criterion->id,
                'criterion_name'        => $criterion->name,
                'criterion_description' => $criterion->description,
                'criterion_type'        => $criterion->type,
                'criterion_weight'      => $criterion->weight,
                'criterion_is_critical' => $criterion->is_critical,
                'ai_result'             => $result,
                'ai_score'              => $score,
                'confidence'            => 0.92,
                'reasoning'             => 'Fake: criterion passed in demo mode.',
                'evidence'              => $evidence,
                'verification_status'   => 'verified',
                'needs_review'          => false,
            ]);
        }

        [$scorePercent, $outcome] = ScoreCalculator::calculate($call->template, $call->criterionResults()->get());

        $call->update([
            'score_percent' => $scorePercent,
            'outcome'       => $outcome,
            'status'        => 'done',
        ]);
    }
}
