<?php

namespace App\Services;

use App\Models\Call;

interface ScoringService
{
    /**
     * Score a call against its template and store criterion_results.
     * Updates call->score_percent and call->outcome when done.
     */
    public function score(Call $call): void;
}
