<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TranscriptSegment extends Model
{
    public $timestamps = false;

    protected $fillable = ['call_id', 'code', 'speaker', 'start_ms', 'end_ms', 'text'];

    protected function casts(): array
    {
        return [
            'speaker'  => 'integer',
            'start_ms' => 'integer',
            'end_ms'   => 'integer',
        ];
    }

    public function call(): BelongsTo
    {
        return $this->belongsTo(Call::class);
    }
}
