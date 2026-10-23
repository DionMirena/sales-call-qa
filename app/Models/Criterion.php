<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Criterion extends Model
{
    protected $fillable = [
        'template_id', 'position', 'name', 'description',
        'fail_conditions', 'type', 'weight', 'is_critical', 'phase_hint',
    ];

    protected function casts(): array
    {
        return [
            'is_critical' => 'boolean',
            'weight'      => 'integer',
            'position'    => 'integer',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(CriterionResult::class);
    }

    public function isScored(): bool
    {
        return $this->type !== 'extract';
    }
}
