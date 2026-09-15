<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AllocationMethod;
use App\Enums\ElectionStatus;
use App\Enums\ElectionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Election extends Model
{
    protected $fillable = [
        'slug', 'name', 'type', 'election_date', 'round', 'rounds', 'allocation',
        'seats', 'threshold_pct', 'minority_coef', 'status', 'description',
    ];

    protected function casts(): array
    {
        return [
            'type' => ElectionType::class,
            'allocation' => AllocationMethod::class,
            'status' => ElectionStatus::class,
            'election_date' => 'date',
            'threshold_pct' => 'float',
            'minority_coef' => 'float',
        ];
    }

    public function units(): HasMany
    {
        return $this->hasMany(ElectionUnit::class)->orderBy('code');
    }

    public function pollingStations(): HasMany
    {
        return $this->hasMany(PollingStation::class);
    }

    public function submitters(): HasMany
    {
        return $this->hasMany(Submitter::class);
    }

    public function lists(): HasMany
    {
        return $this->hasMany(ElectoralList::class)->orderBy('number');
    }

    public function protocols(): HasMany
    {
        return $this->hasMany(Protocol::class);
    }

    public function turnoutSnapshots(): HasMany
    {
        return $this->hasMany(TurnoutSnapshot::class);
    }

    public function deadlines(): HasMany
    {
        return $this->hasMany(Deadline::class)->orderBy('date')->orderBy('sort_order');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(Allocation::class);
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(Snapshot::class);
    }

    public function isProportional(): bool
    {
        return $this->allocation === AllocationMethod::DHondt;
    }
}
