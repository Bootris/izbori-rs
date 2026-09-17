<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\ProtocolStatus;
use App\Models\Protocol;
use App\Models\User;
use App\Support\Access;

/**
 * Who may do what with a zapisnik. Reading follows the municipality, writing
 * the station (a controller only its own), verification the role; nothing
 * moves once the election is final or the protocol is verified — a verified
 * protocol first goes back "na ispravku" with a reason (unverify).
 */
final class ProtocolPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Protocol $protocol): bool
    {
        return $user->canReadMunicipality(Access::stationOf($protocol)->municipality_id);
    }

    public function create(User $user): bool
    {
        return $user->canWriteSomething();
    }

    public function update(User $user, Protocol $protocol): bool
    {
        return $protocol->status !== ProtocolStatus::Verified
            && Access::electionOf($protocol)->acceptsEntries()
            && $user->canWriteStation(Access::stationOf($protocol));
    }

    /** A protocol is never deleted, it is annulled with a reason. */
    public function delete(User $user, Protocol $protocol): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function verify(User $user, Protocol $protocol): bool
    {
        return $this->triage($user, $protocol) && $protocol->status === ProtocolStatus::Entered;
    }

    public function unverify(User $user, Protocol $protocol): bool
    {
        return $this->triage($user, $protocol) && $protocol->status === ProtocolStatus::Verified;
    }

    public function annul(User $user, Protocol $protocol): bool
    {
        return $this->triage($user, $protocol) && $protocol->status !== ProtocolStatus::Annulled;
    }

    public function revalidate(User $user, Protocol $protocol): bool
    {
        return $this->triage($user, $protocol) && $protocol->status === ProtocolStatus::Annulled;
    }

    private function triage(User $user, Protocol $protocol): bool
    {
        return $user->canVerify()
            && Access::electionOf($protocol)->acceptsEntries()
            && $user->canReadMunicipality(Access::stationOf($protocol)->municipality_id);
    }
}
