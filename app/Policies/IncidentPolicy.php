<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Incident;
use App\Models\User;
use App\Support\Access;

/**
 * Anyone who may write a station may report from it; the report itself is
 * never edited afterwards (IncidentService), so there is no update. Triage
 * (review, close) is for verifiers and admins of that municipality, the
 * public flag for admins only.
 */
final class IncidentPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Incident $incident): bool
    {
        return $user->canReadMunicipality($incident->municipality_id);
    }

    public function create(User $user): bool
    {
        return $user->canWriteSomething();
    }

    public function update(User $user, Incident $incident): bool
    {
        return false;
    }

    public function delete(User $user, Incident $incident): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function triage(User $user, Incident $incident): bool
    {
        return $user->canVerify()
            && Access::electionOf($incident)->acceptsEntries()
            && $user->canReadMunicipality($incident->municipality_id);
    }

    public function publish(User $user, Incident $incident): bool
    {
        return $user->isAdmin();
    }
}
