<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\IncidentCategory;
use App\Enums\IncidentSeverity;
use App\Enums\IncidentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Prijava problema sa biračkog mesta. Written once by whoever is on site,
 * then only its status, resolution and public flag change; `reported_at` is
 * the server clock at the moment of the report and is never edited.
 */
class Incident extends Model
{
    protected $fillable = [
        'election_id', 'polling_station_id', 'municipality_id', 'category', 'severity', 'status',
        'description', 'occurred_at', 'reported_at', 'reported_by', 'reviewed_by', 'reviewed_at',
        'resolved_by', 'resolved_at', 'resolution', 'is_public',
    ];

    /** Columns fixed at the moment of the report; triage touches everything else. */
    public const IMMUTABLE = ['reported_at', 'reported_by', 'description', 'polling_station_id', 'election_id', 'occurred_at'];

    protected static function booted(): void
    {
        static::updating(function (Incident $incident): void {
            foreach (self::IMMUTABLE as $column) {
                if ($incident->isDirty($column)) {
                    throw new \LogicException("Incident.{$column} is fixed at report time and cannot be changed.");
                }
            }
        });
    }

    protected function casts(): array
    {
        return [
            'category' => IncidentCategory::class,
            'severity' => IncidentSeverity::class,
            'status' => IncidentStatus::class,
            'occurred_at' => 'datetime',
            'reported_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'resolved_at' => 'datetime',
            'is_public' => 'boolean',
        ];
    }

    public function election(): BelongsTo
    {
        return $this->belongsTo(Election::class);
    }

    public function pollingStation(): BelongsTo
    {
        return $this->belongsTo(PollingStation::class);
    }

    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }

    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [IncidentStatus::Open, IncidentStatus::InReview]);
    }

    public function scopePublic(Builder $query): Builder
    {
        return $query->where('is_public', true);
    }

    public function isOpen(): bool
    {
        return ! $this->status->isClosed();
    }
}
