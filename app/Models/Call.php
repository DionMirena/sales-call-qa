<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Call extends Model
{
    use HasFactory;

    protected $fillable = [
        'agent_id', 'template_id', 'uploaded_by',
        'customer_name', 'policy_type',
        'original_path', 'original_filename', 'audio_path',
        'duration_seconds', 'status', 'error_message',
        'agent_speaker', 'score_percent', 'outcome',
    ];

    protected function casts(): array
    {
        return [
            'duration_seconds' => 'integer',
            'agent_speaker'    => 'integer',
            'score_percent'    => 'float',
        ];
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    // Include deleted (soft-deleted) templates: a call keeps the template it was scored with.
    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class)->withTrashed();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function segments(): HasMany
    {
        return $this->hasMany(TranscriptSegment::class)->orderBy('start_ms');
    }

    public function criterionResults(): HasMany
    {
        return $this->hasMany(CriterionResult::class);
    }

    public function isProcessing(): bool
    {
        return in_array($this->status, ['queued', 'normalising', 'transcribing', 'scoring']);
    }
}
