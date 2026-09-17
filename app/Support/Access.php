<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\ElectionStatus;
use App\Models\Election;
use App\Models\PollingStation;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Territorial scoping for the admin. Reading: an admin sees everything, everyone
 * else their municipality (nothing if unassigned). Writing: a verifier or operator
 * writes its whole municipality, a controller only the stations assigned to it.
 * The policies in App\Policies and the services enforce the same rules on the
 * server; these helpers keep the forms and lists consistent with them.
 */
final class Access
{
    public static function user(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }

    public static function isAdmin(): bool
    {
        return self::user()?->isAdmin() ?? false;
    }

    /** Restrict a query with a `municipality_id` column to the read scope. */
    public static function scopeMunicipality(Builder $query, string $column = 'municipality_id'): Builder
    {
        $user = self::user();
        if ($user === null || $user->isAdmin()) {
            return $query;
        }

        return $user->municipality_id
            ? $query->where($column, $user->municipality_id)
            : $query->whereRaw('1 = 0');
    }

    /** Restrict a query through a `pollingStation` relation to the read scope. */
    public static function scopeThroughStation(Builder $query): Builder
    {
        $user = self::user();
        if ($user === null || $user->isAdmin()) {
            return $query;
        }

        return $user->municipality_id
            ? $query->whereHas('pollingStation', fn (Builder $q) => $q->where('municipality_id', $user->municipality_id))
            : $query->whereRaw('1 = 0');
    }

    /** Restrict a polling-station query to the stations the user may write. */
    public static function scopeWritableStations(Builder $query): Builder
    {
        $user = self::user();
        if ($user === null) {
            return $query->whereRaw('1 = 0');
        }
        if ($user->isController()) {
            return $query->whereIn('polling_stations.id', $user->assignedStationIds() ?: [0]);
        }

        return self::scopeMunicipality($query);
    }

    public static function canWriteStation(PollingStation $station): bool
    {
        return self::user()?->canWriteStation($station) ?? false;
    }

    /**
     * Elections offered in the entry forms: never a draft or a closed one, for a
     * controller only those its stations belong to. Newest first, so the one
     * being counted tonight is the default.
     */
    public static function electionsOpenForEntry(): Builder
    {
        $user = self::user();
        $query = Election::query()
            ->whereIn('status', [ElectionStatus::Registry, ElectionStatus::Voting, ElectionStatus::Counting])
            ->orderByDesc('election_date');

        if ($user?->isController()) {
            $query->whereHas('pollingStations', fn (Builder $q) => $q->whereIn('polling_stations.id', $user->assignedStationIds() ?: [0]));
        }

        return $query;
    }

    /** The one station a controller is assigned to, so the form can preselect it. */
    public static function singleWritableStation(?int $electionId = null): ?PollingStation
    {
        $user = self::user();
        if ($user === null || ! $user->isController()) {
            return null;
        }

        $stations = PollingStation::query()->whereIn('id', $user->assignedStationIds() ?: [0])
            ->when($electionId, fn (Builder $q) => $q->where('election_id', $electionId))
            ->get();

        return $stations->count() === 1 ? $stations->first() : null;
    }

    /** The record's station without tripping the lazy-loading guard (lists eager-load it, services may not). */
    public static function stationOf(Model $record): ?PollingStation
    {
        if (! $record->relationLoaded('pollingStation')) {
            $record->load('pollingStation');
        }

        return $record->getRelation('pollingStation');
    }

    public static function electionOf(Model $record): Election
    {
        if (! $record->relationLoaded('election')) {
            $record->load('election');
        }

        return $record->getRelation('election');
    }
}
