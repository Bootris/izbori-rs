<?php

declare(strict_types=1);

namespace App\Services\Results;

use App\Enums\ProtocolStatus;
use App\Models\Election;
use App\Models\ElectionUnit;
use App\Models\Municipality;
use App\Models\PollingStation;
use App\Models\Protocol;
use App\Models\ProtocolItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Sums verified protocols at every level. Everything returned carries
 * `processed` (share of stations verified) — a number without it misleads.
 */
final class ResultsAggregator
{
    /** @return array<string, mixed> */
    public function forUnit(ElectionUnit $unit, int $round): array
    {
        $municipalityIds = $unit->municipalities()->pluck('municipalities.id');

        $stations = PollingStation::query()
            ->where('election_id', $unit->election_id)
            ->whereIn('municipality_id', $municipalityIds);

        $protocols = Protocol::query()
            ->where('election_unit_id', $unit->id)
            ->where('round', $round);

        return $this->aggregate($stations, $protocols);
    }

    /** @return array<string, mixed> */
    public function forMunicipality(Election $election, Municipality $municipality, int $round): array
    {
        $stations = PollingStation::query()
            ->where('election_id', $election->id)
            ->where('municipality_id', $municipality->id);

        $protocols = Protocol::query()
            ->where('election_id', $election->id)
            ->where('round', $round)
            ->whereIn('polling_station_id', (clone $stations)->select('id'));

        return $this->aggregate($stations, $protocols);
    }

    /** @return array<string, mixed> */
    public function forElection(Election $election, int $round): array
    {
        $stations = PollingStation::query()->where('election_id', $election->id);
        $protocols = Protocol::query()->where('election_id', $election->id)->where('round', $round);

        return $this->aggregate($stations, $protocols);
    }

    /**
     * @param Builder<PollingStation> $stations
     * @param Builder<Protocol> $protocols
     * @return array<string, mixed>
     */
    private function aggregate(Builder $stations, Builder $protocols): array
    {
        $stationCount = (clone $stations)->count();
        $registeredAll = (int) (clone $stations)->sum('registered_voters');

        $byStatus = (clone $protocols)
            ->selectRaw('status, count(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');

        $verified = (clone $protocols)->verified();

        $totals = (clone $verified)->selectRaw(
            'count(*) as protocols,
             coalesce(sum(registered_voters),0) as registered_voters,
             coalesce(sum(voters_voted),0) as voters_voted,
             coalesce(sum(ballots_in_box),0) as ballots_in_box,
             coalesce(sum(ballots_valid),0) as ballots_valid,
             coalesce(sum(ballots_invalid),0) as ballots_invalid'
        )->first();

        $votesByList = ProtocolItem::query()
            ->whereIn('protocol_id', (clone $verified)->select('id'))
            ->selectRaw('electoral_list_id, sum(votes) as votes')
            ->groupBy('electoral_list_id')
            ->pluck('votes', 'electoral_list_id')
            ->map(fn ($v) => (int) $v);

        $valid = (int) $totals->ballots_valid;
        $voted = (int) $totals->voters_voted;
        $registered = (int) $totals->registered_voters;

        return [
            'stations_total' => $stationCount,
            'stations_verified' => (int) $totals->protocols,
            'stations_entered' => (int) ($byStatus[ProtocolStatus::Entered->value] ?? 0),
            'stations_flagged' => (int) ($byStatus[ProtocolStatus::Flagged->value] ?? 0),
            'processed' => $stationCount > 0 ? round((int) $totals->protocols / $stationCount * 100, 2) : 0.0,
            'registered_voters_all' => $registeredAll,
            'registered_voters' => $registered,
            'voters_voted' => $voted,
            'turnout_pct' => $registered > 0 ? round($voted / $registered * 100, 2) : 0.0,
            'ballots_in_box' => (int) $totals->ballots_in_box,
            'ballots_valid' => $valid,
            'ballots_invalid' => (int) $totals->ballots_invalid,
            'invalid_pct' => $voted > 0 ? round((int) $totals->ballots_invalid / $voted * 100, 2) : 0.0,
            'votes_by_list' => $votesByList->all(),
        ];
    }

    /**
     * Per-list rows (votes, share of valid votes) in ballot order.
     *
     * @param Collection<int, \App\Models\ElectoralList> $lists
     * @param array<int, int> $votesByList
     * @return array<int, array<string, mixed>>
     */
    public function listRows(Collection $lists, array $votesByList, int $validVotes): array
    {
        return $lists->map(function ($list) use ($votesByList, $validVotes) {
            $votes = $votesByList[$list->id] ?? 0;

            return [
                'list_id' => $list->id,
                'number' => $list->number,
                'name' => $list->name,
                'short_name' => $list->short_name,
                'holder_name' => $list->holder_name,
                'is_minority' => $list->is_minority,
                'color' => $list->color ?? $list->submitter?->color,
                'submitter_id' => $list->submitter_id,
                'votes' => $votes,
                'votes_pct' => $validVotes > 0 ? round($votes / $validVotes * 100, 2) : 0.0,
            ];
        })->values()->all();
    }
}
