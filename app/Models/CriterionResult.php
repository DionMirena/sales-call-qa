<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CriterionResult extends Model
{
    protected $fillable = [
        'call_id', 'criterion_id',
        'criterion_name', 'criterion_description', 'criterion_type',
        'criterion_weight', 'criterion_is_critical',
        'ai_result', 'ai_score', 'confidence', 'reasoning',
        'evidence', 'verification_status', 'needs_review',
    ];

    protected function casts(): array
    {
        return [
            'evidence'             => 'array',
            'criterion_is_critical'=> 'boolean',
            'criterion_weight'     => 'integer',
            'ai_score'             => 'float',
            'confidence'           => 'float',
            'needs_review'         => 'boolean',
        ];
    }

    public function call(): BelongsTo
    {
        return $this->belongsTo(Call::class);
    }

    public function criterion(): BelongsTo
    {
        return $this->belongsTo(Criterion::class);
    }

    public function overrides(): HasMany
    {
        return $this->hasMany(Override::class)->orderBy('created_at');
    }

    public function latestOverride(): ?Override
    {
        return $this->overrides()->latest('created_at')->first();
    }

    public function effectiveResult(): string
    {
        return $this->latestOverride()?->new_result ?? $this->ai_result;
    }

    public function effectiveScore(): ?float
    {
        $override = $this->latestOverride();
        return $override ? $override->new_score : $this->ai_score;
    }
}
