<?php

declare(strict_types=1);

namespace App\Services\Protocols;

use App\Enums\ProtocolStatus;
use App\Models\Election;
use App\Models\ElectionUnit;
use App\Models\PollingStation;
use App\Models\Protocol;
use App\Models\ProtocolRevision;
use App\Models\User;
use App\Services\Validation\ProtocolValidator;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The only way a protocol is written. Resolves the election unit, runs the
 * control sums, keeps the audit trail and makes sure an edited protocol has
 * to be verified again before it counts. With an actor given it also enforces
 * the write scope (a controller only its own station), the election lock and
 * the rule that a verified protocol first goes back for correction.
 */
final class ProtocolService
{
    public function __construct(private readonly ProtocolValidator $validator) {}

    /**
     * @param array<string, mixed> $attributes  protocol columns (see Protocol::$fillable)
     * @param array<int, int>      $itemVotes   electoral_list_id => votes
     *
     * @throws AuthorizationException the actor may not write this station
     * @throws RuntimeException       election closed, protocol still verified, station outside every unit
     */
    public function save(Protocol $protocol, array $attributes, array $itemVotes, ?User $actor): Protocol
    {
        return DB::transaction(function () use ($protocol, $attributes, $itemVotes, $actor) {
            $isNew = ! $protocol->exists;
            $protocol->fill(array_intersect_key($attributes, array_flip(['election_id', 'polling_station_id', 'round'])));
            $station = PollingStation::query()->findOrFail($protocol->polling_station_id);
            $this->assertWritable($protocol, $station, $actor);
            if (! $isNew && $protocol->status === ProtocolStatus::Verified) {
                throw new RuntimeException('Zapisnik je verifikovan — prvo ga vratite na ispravku (sa razlogom), pa izmenite.');
            }
            $before = $isNew ? [] : $protocol->only(Protocol::COUNT_FIELDS) + ['items' => $protocol->items()->pluck('votes', 'electoral_list_id')->all()];

            $protocol->fill($attributes);
            $protocol->election_unit_id = $this->resolveUnitId($protocol, $station);

            if ($isNew) {
                $protocol->entered_by = $actor?->id;
            }

            $itemVotes = array_map('intval', $itemVotes);
            $result = $this->validator->validate($protocol, $itemVotes);

            $protocol->deviation = $result->deviation;
            $protocol->validation_errors = $result->errors ?: null;

            if ($protocol->status !== ProtocolStatus::Annulled) {
                $protocol->status = $result->passes() ? ProtocolStatus::Entered : ProtocolStatus::Flagged;
                $protocol->verified_by = null;
                $protocol->verified_at = null;
            }

            if (! $isNew && $protocol->isDirty()) {
                $protocol->revision++;
            }

            $protocol->save();
            $this->syncItems($protocol, $itemVotes);

            $after = $protocol->only(Protocol::COUNT_FIELDS) + ['items' => $itemVotes];
            $this->record($protocol, $actor, $isNew ? 'created' : 'updated', $this->diff($before, $after));

            return $protocol->refresh();
        });
    }

    public function verify(Protocol $protocol, User $actor): Protocol
    {
        $this->assertCanTriage($protocol, $actor);

        $itemVotes = $protocol->items()->pluck('votes', 'electoral_list_id')->map(fn ($v) => (int) $v)->all();
        $result = $this->validator->validate($protocol, $itemVotes);

        if (! $result->passes()) {
            throw new RuntimeException('Zapisnik ne prolazi kontrolne sume: '.implode(' ', $result->errors));
        }

        $protocol->forceFill([
            'status' => ProtocolStatus::Verified,
            'verified_by' => $actor->id,
            'verified_at' => now(),
        ])->save();

        $this->record($protocol, $actor, 'verified', []);

        return $protocol;
    }

    /**
     * Take a verified protocol out of the aggregates again so it can be corrected.
     * The reason is part of the audit trail — this is the only door to editing
     * after verification.
     */
    public function returnForCorrection(Protocol $protocol, User $actor, string $reason): Protocol
    {
        $this->assertCanTriage($protocol, $actor);
        if ($protocol->status !== ProtocolStatus::Verified) {
            throw new RuntimeException('Samo verifikovan zapisnik se vraća na ispravku.');
        }
        $reason = trim($reason);
        if ($reason === '') {
            throw new RuntimeException('Razlog vraćanja na ispravku je obavezan.');
        }

        $protocol->forceFill([
            'status' => ProtocolStatus::Entered,
            'verified_by' => null,
            'verified_at' => null,
            'notes' => trim(($protocol->notes ?? '')."\nVraćen na ispravku: {$reason}"),
        ])->save();

        $this->record($protocol, $actor, 'unverified', ['reason' => [null, $reason]]);

        return $protocol;
    }

    public function annul(Protocol $protocol, User $actor, string $reason): Protocol
    {
        $this->assertCanTriage($protocol, $actor);
        $protocol->forceFill([
            'status' => ProtocolStatus::Annulled,
            'verified_by' => null,
            'verified_at' => null,
            'notes' => trim(($protocol->notes ?? '')."\nPoništen: {$reason}"),
        ])->save();

        $this->record($protocol, $actor, 'annulled', ['reason' => [null, $reason]]);

        return $protocol;
    }

    /** Re-run the control sums on a stored protocol without changing any number. */
    public function revalidate(Protocol $protocol, ?User $actor): Protocol
    {
        $itemVotes = $protocol->items()->pluck('votes', 'electoral_list_id')->map(fn ($v) => (int) $v)->all();

        return $this->save($protocol, [], $itemVotes, $actor);
    }

    /** Write scope and election lock, for everything that changes numbers. Seeders and console pass no actor. */
    private function assertWritable(Protocol $protocol, PollingStation $station, ?User $actor): void
    {
        if ($actor === null) {
            return;
        }
        $election = Election::query()->findOrFail($protocol->election_id);
        if (! $election->acceptsEntries()) {
            throw new RuntimeException("Izbori „{$election->name}\" su u statusu „{$election->status->getLabel()}\" — unos i izmene nisu dozvoljeni.");
        }
        if (! $actor->canWriteStation($station)) {
            throw new AuthorizationException("Nemate pravo unosa za biračko mesto {$station->number}.");
        }
    }

    /** Verify / return / annul: a verifier of that municipality (or an admin) while the election is open. */
    private function assertCanTriage(Protocol $protocol, User $actor): void
    {
        if (! $actor->canVerify()) {
            throw new RuntimeException('Korisnik nema pravo verifikacije.');
        }
        $election = Election::query()->findOrFail($protocol->election_id);
        if (! $election->acceptsEntries()) {
            throw new RuntimeException("Izbori „{$election->name}\" su u statusu „{$election->status->getLabel()}\" — zapisnici su zaključani.");
        }
        $station = PollingStation::query()->findOrFail($protocol->polling_station_id);
        if (! $actor->canReadMunicipality($station->municipality_id)) {
            throw new AuthorizationException("Biračko mesto {$station->number} nije u vašoj opštini.");
        }
    }

    private function resolveUnitId(Protocol $protocol, PollingStation $station): int
    {
        $unit = ElectionUnit::query()
            ->where('election_id', $protocol->election_id)
            ->whereHas('municipalities', fn ($q) => $q->where('municipalities.id', $station->municipality_id))
            ->first();

        if ($unit === null) {
            throw new RuntimeException("Opština biračkog mesta {$station->number} nije dodeljena nijednoj izbornoj jedinici.");
        }

        return $unit->id;
    }

    /** @param array<int, int> $itemVotes */
    private function syncItems(Protocol $protocol, array $itemVotes): void
    {
        foreach ($itemVotes as $listId => $votes) {
            $protocol->items()->updateOrCreate(['electoral_list_id' => $listId], ['votes' => $votes]);
        }
        $protocol->items()->whereNotIn('electoral_list_id', array_keys($itemVotes))->delete();
    }

    /**
     * @param array<string, mixed> $before
     * @param array<string, mixed> $after
     * @return array<string, array{0:mixed,1:mixed}>
     */
    private function diff(array $before, array $after): array
    {
        $changes = [];
        foreach ($after as $key => $value) {
            if (($before[$key] ?? null) != $value) {
                $changes[$key] = [$before[$key] ?? null, $value];
            }
        }

        return $changes;
    }

    /** @param array<string, mixed> $changes */
    private function record(Protocol $protocol, ?User $actor, string $action, array $changes): void
    {
        ProtocolRevision::create([
            'protocol_id' => $protocol->id,
            'user_id' => $actor?->id,
            'action' => $action,
            'changes' => $changes ?: null,
            'created_at' => now(),
        ]);
    }
}
