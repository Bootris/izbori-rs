<?php

declare(strict_types=1);

namespace App\Services\Incidents;

use App\Enums\IncidentStatus;
use App\Models\Election;
use App\Models\Incident;
use App\Models\PollingStation;
use App\Models\User;
use App\Notifications\IncidentReported;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Notification;

/**
 * The only way an incident is written. A report is stamped with the server
 * clock and pushed to everyone on the platform at once; afterwards only its
 * triage fields move (review, resolve, publish), never the report itself.
 */
final class IncidentService
{
    /**
     * @param array{polling_station_id:int|string, category:mixed, severity:mixed, description:string, occurred_at?:mixed} $data
     */
    public function report(array $data, ?User $reporter): Incident
    {
        $station = PollingStation::query()->findOrFail((int) $data['polling_station_id']);
        $this->assertCanReportFor($station, $reporter);

        $incident = Incident::create([
            'election_id' => $station->election_id,
            'polling_station_id' => $station->id,
            'municipality_id' => $station->municipality_id,
            'category' => $data['category'],
            'severity' => $data['severity'],
            'status' => IncidentStatus::Open,
            'description' => trim((string) $data['description']),
            'occurred_at' => $data['occurred_at'] ?? now(),
            'reported_at' => now(),
            'reported_by' => $reporter?->id,
            'is_public' => false,
        ]);

        $this->alarm($incident);

        return $incident;
    }

    public function review(Incident $incident, ?User $by): void
    {
        $incident->update([
            'status' => IncidentStatus::InReview,
            'reviewed_by' => $by?->id,
            'reviewed_at' => now(),
        ]);
    }

    public function close(Incident $incident, ?User $by, string $resolution, IncidentStatus $outcome = IncidentStatus::Resolved): void
    {
        if (! in_array($outcome, [IncidentStatus::Resolved, IncidentStatus::Dismissed], true)) {
            throw new \InvalidArgumentException('An incident closes as resolved or dismissed.');
        }

        $incident->update([
            'status' => $outcome,
            'resolved_by' => $by?->id,
            'resolved_at' => now(),
            'resolution' => trim($resolution),
        ]);
    }

    public function reopen(Incident $incident): void
    {
        $incident->update(['status' => IncidentStatus::Open, 'resolved_by' => null, 'resolved_at' => null]);
    }

    public function setPublic(Incident $incident, bool $public): void
    {
        $incident->update(['is_public' => $public]);
    }

    /**
     * A report comes only from a station the reporter may write: a controller
     * from its own station, a verifier or operator from its municipality. The
     * form already searches within that scope; this is the server-side check
     * that a hand-crafted request cannot skip. A closed election takes no reports.
     */
    private function assertCanReportFor(PollingStation $station, ?User $reporter): void
    {
        if ($reporter === null) {
            return;
        }
        $election = Election::query()->findOrFail($station->election_id);
        if (! $election->acceptsEntries()) {
            throw new AuthorizationException("Izbori „{$election->name}\" su u statusu „{$election->status->getLabel()}\" — prijave se više ne primaju.");
        }
        if (! $reporter->canWriteStation($station)) {
            throw new AuthorizationException("Nemate pravo prijave za biračko mesto {$station->number}.");
        }
    }

    /** Everyone with a panel account hears about it, except whoever typed it. */
    private function alarm(Incident $incident): void
    {
        $incident->load(['pollingStation', 'municipality']);

        $recipients = User::query()
            ->when($incident->reported_by, fn ($q) => $q->where('id', '!=', $incident->reported_by))
            ->get();

        Notification::send($recipients, new IncidentReported($incident));
    }
}
