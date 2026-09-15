<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProtocolStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Zapisnik biračkog odbora — the atomic unit every published number is built from. */
class Protocol extends Model
{
    protected $fillable = [
        'election_id', 'election_unit_id', 'polling_station_id', 'round', 'status',
        'registered_voters', 'ballots_received', 'ballots_unused', 'voters_voted',
        'ballots_in_box', 'ballots_valid', 'ballots_invalid', 'deviation', 'validation_errors',
        'recount_requested', 'notes', 'entered_by', 'verified_by', 'verified_at', 'revision',
    ];

    /** Numeric fields subject to the K1–K7 control sums. */
    public const COUNT_FIELDS = [
        'registered_voters', 'ballots_received', 'ballots_unused', 'voters_voted',
        'ballots_in_box', 'ballots_valid', 'ballots_invalid',
    ];

    protected function casts(): array
    {
        return [
            'status' => ProtocolStatus::class,
            'validation_errors' => 'array',
            'recount_requested' => 'boolean',
            'verified_at' => 'datetime',
        ];
    }

    public function election(): BelongsTo
    {
        return $this->belongsTo(Election::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(ElectionUnit::class, 'election_unit_id');
    }

    public function pollingStation(): BelongsTo
    {
        return $this->belongsTo(PollingStation::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProtocolItem::class);
    }

    public function scans(): HasMany
    {
        return $this->hasMany(ProtocolScan::class);
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(ProtocolRevision::class)->orderByDesc('created_at');
    }

    public function enteredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function scopeVerified(Builder $query): Builder
    {
        return $query->where('status', ProtocolStatus::Verified);
    }

    public function scopeFlagged(Builder $query): Builder
    {
        return $query->where('status', ProtocolStatus::Flagged);
    }

    public function isVerified(): bool
    {
        return $this->status === ProtocolStatus::Verified;
    }

    public function turnoutPct(): float
    {
        return $this->registered_voters > 0 ? round($this->voters_voted / $this->registered_voters * 100, 2) : 0.0;
    }
}
