<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Override extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'criterion_result_id', 'user_id',
        'old_result', 'old_score',
        'new_result', 'new_score',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'old_score'  => 'float',
            'new_score'  => 'float',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Override $override) {
            $override->created_at = now();
        });
    }

    public function criterionResult(): BelongsTo
    {
        return $this->belongsTo(CriterionResult::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
