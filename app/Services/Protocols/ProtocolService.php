<?php

declare(strict_types=1);

namespace App\Services\Protocols;

use App\Enums\ProtocolStatus;
use App\Models\ElectionUnit;
use App\Models\Protocol;
use App\Models\ProtocolRevision;
use App\Models\User;
use App\Services\Validation\ProtocolValidator;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The only way a protocol is written. Resolves the election unit, runs the
 * control sums, keeps the audit trail and makes sure an edited protocol has
 * to be verified again before it counts.
 */
final class ProtocolService
{
    public function __construct(private readonly ProtocolValidator $validator) {}

    /**
     * @param array<string, mixed> $attributes  protocol columns (see Protocol::$fillable)
     * @param array<int, int>      $itemVotes   electoral_list_id => votes
     */
    public function save(Protocol $protocol, array $attributes, array $itemVotes, ?User $actor): Protocol
    {
        return DB::transaction(function () use ($protocol, $attributes, $itemVotes, $actor) {
            $isNew = ! $protocol->exists;
            $before = $isNew ? [] : $protocol->only(Protocol::COUNT_FIELDS) + ['items' => $protocol->items()->pluck('votes', 'electoral_list_id')->all()];

            $protocol->fill($attributes);
            $protocol->election_unit_id = $this->resolveUnitId($protocol);

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
        if (! $actor->canVerify()) {
            throw new RuntimeException('Korisnik nema pravo verifikacije.');
        }

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

    public function annul(Protocol $protocol, User $actor, string $reason): Protocol
    {
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

    private function resolveUnitId(Protocol $protocol): int
    {
        $station = $protocol->pollingStation()->firstOrFail();

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
