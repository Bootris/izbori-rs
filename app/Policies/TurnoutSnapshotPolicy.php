<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\TurnoutSnapshot;
use App\Models\User;
use App\Support\Access;

/**
 * A turnout figure is written by whoever may write its station; a row for the
 * whole municipality (no station) only by the municipality roles. Nothing
 * changes once the election is final.
 */
final class TurnoutSnapshotPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, TurnoutSnapshot $row): bool
    {
        return $user->canReadMunicipality($row->municipality_id);
    }

    public function create(User $user): bool
    {
        return $user->canWriteSomething();
    }

    public function update(User $user, TurnoutSnapshot $row): bool
    {
        return Access::electionOf($row)->acceptsEntries() && $this->writes($user, $row);
    }

    public function delete(User $user, TurnoutSnapshot $row): bool
    {
        return $this->update($user, $row);
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    private function writes(User $user, TurnoutSnapshot $row): bool
    {
        $station = Access::stationOf($row);
        if ($station !== null) {
            return $user->canWriteStation($station);
        }

        return $user->isAdmin() || ($user->role->writesWholeMunicipality() && $user->canReadMunicipality($row->municipality_id));
    }
}
