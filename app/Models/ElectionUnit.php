<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The territory one allocation is computed for. Parliamentary: one unit for the
 * whole country. Local: one unit per municipality. Orthogonal to the
 * district → municipality hierarchy, joined through election_unit_municipality.
 */
class ElectionUnit extends Model
{
    protected $fillable = ['election_id', 'code', 'name', 'seats'];

    public function election(): BelongsTo
    {
        return $this->belongsTo(Election::class);
    }

    public function municipalities(): BelongsToMany
    {
        return $this->belongsToMany(Municipality::class)->orderBy('sort_order');
    }

    public function lists(): HasMany
    {
        return $this->hasMany(ElectoralList::class)->orderBy('number');
    }

    public function protocols(): HasMany
    {
        return $this->hasMany(Protocol::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(Allocation::class);
    }

    /** Seats to distribute: unit override, else the election-wide number. */
    public function effectiveSeats(): int
    {
        return (int) ($this->seats ?? $this->election->seats ?? 0);
    }
}
