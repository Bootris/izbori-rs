<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Persisted output of the allocator for one unit + round (recomputed on every publish). */
class Allocation extends Model
{
    protected $fillable = ['election_id', 'election_unit_id', 'round', 'computed_at', 'total_voted', 'valid_votes', 'threshold_votes', 'result'];

    protected function casts(): array
    {
        return ['computed_at' => 'datetime', 'result' => 'array'];
    }

    public function election(): BelongsTo
    {
        return $this->belongsTo(Election::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(ElectionUnit::class, 'election_unit_id');
    }

    public function seats(): HasMany
    {
        return $this->hasMany(AllocationSeat::class)->orderBy('seat_no');
    }
}
