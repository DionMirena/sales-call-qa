<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Template extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['name', 'description', 'pass_threshold', 'created_by'];

    protected function casts(): array
    {
        return [
            'pass_threshold' => 'integer',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function criteria(): HasMany
    {
        return $this->hasMany(Criterion::class)->orderBy('position');
    }

    public function calls(): HasMany
    {
        return $this->hasMany(Call::class);
    }
}
