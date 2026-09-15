<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PollingStation extends Model
{
    protected $fillable = [
        'election_id', 'municipality_id', 'number', 'name', 'address', 'registered_voters',
        'accessible', 'is_diaspora', 'country', 'lat', 'lng',
    ];

    protected function casts(): array
    {
        return [
            'accessible' => 'boolean',
            'is_diaspora' => 'boolean',
            'lat' => 'float',
            'lng' => 'float',
        ];
    }

    public function election(): BelongsTo
    {
        return $this->belongsTo(Election::class);
    }

    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }

    public function protocols(): HasMany
    {
        return $this->hasMany(Protocol::class);
    }

    /** Stable public identifier used in snapshot files and SPA routes. */
    public function publicId(): string
    {
        return "{$this->municipality->district->code}-{$this->municipality->code}-{$this->number}";
    }
}
