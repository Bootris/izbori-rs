<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AllocationMethod;
use App\Enums\ElectionStatus;
use App\Enums\ElectionType;
use App\Enums\SnapshotSource;
use App\Services\Snapshots\SnapshotPublisher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

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

    protected static function booted(): void
    {
        // Moving an election back before "counting" (a mis-click, a re-run) must
        // also take the results off the public site: the pointer is withdrawn,
        // the immutable versions stay on disk.
        static::updated(function (Election $election) {
            if ($election->wasChanged('status') && ! $election->status->publishesResults()) {
                app(SnapshotPublisher::class)->withdraw($election, SnapshotSource::Results);
            }
        });
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

    /** When the polls close on election day (ZINP čl. 84, 20:00 local time). */
    public function pollsCloseAt(): Carbon
    {
        return Carbon::parse(
            $this->election_date->toDateString().' '.config('izbori.polls.close'),
            config('izbori.polls.timezone'),
        );
    }

    /**
     * Results go public only while counting or final AND after the polls closed.
     * Both must hold: a status set too early, or a date typed wrong, is caught
     * before anything reaches config.json.
     */
    public function resultsPublishable(): bool
    {
        return $this->status->publishesResults() && now()->greaterThanOrEqualTo($this->pollsCloseAt());
    }

    /** Why results cannot be published right now, or null when they can. */
    public function resultsBlockedReason(): ?string
    {
        if (! $this->status->publishesResults()) {
            return sprintf('Status izbora je „%s" — rezultati se objavljuju tek u statusu „%s".',
                $this->status->getLabel(), ElectionStatus::Counting->getLabel());
        }
        if (now()->lessThan($this->pollsCloseAt())) {
            return sprintf('Biračka mesta se zatvaraju %s — rezultati se ne objavljuju pre toga.',
                $this->pollsCloseAt()->format('d.m.Y. \u H:i'));
        }

        return null;
    }

    /** A final election is closed: no protocol, turnout or report changes. */
    public function isLocked(): bool
    {
        return $this->status === ElectionStatus::Final;
    }

    public function acceptsEntries(): bool
    {
        return $this->status->acceptsEntries();
    }
}
