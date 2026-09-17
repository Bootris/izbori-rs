<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\IncidentCategory;
use App\Enums\IncidentSeverity;
use App\Enums\ProtocolStatus;
use App\Enums\UserRole;
use App\Filament\Resources\Incidents\Pages\CreateIncident;
use App\Filament\Resources\Protocols\Pages\CreateProtocol;
use App\Filament\Resources\TurnoutSnapshots\TurnoutSnapshotResource;
use App\Models\Election;
use App\Models\Incident;
use App\Models\Municipality;
use App\Models\PollingStation;
use App\Models\Protocol;
use App\Models\TurnoutSnapshot;
use App\Models\User;
use App\Services\Incidents\IncidentService;
use App\Services\Protocols\ProtocolService;
use Database\Seeders\DemoElectionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Kontrolor biračkog mesta: writes only the station(s) assigned to it, reads
 * the rest of its municipality, never anything else. Every rule is checked on
 * the server (policy + service), not only in the form.
 */
class ControllerAccessTest extends TestCase
{
    use RefreshDatabase;

    private Municipality $municipality;

    private User $controller;

    private PollingStation $own;

    private PollingStation $neighbour;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoElectionSeeder::class);

        $this->municipality = Municipality::where('name', 'Niš – Medijana')->firstOrFail();
        $this->controller = User::where('email', 'kontrolor.nis@example.com')->firstOrFail();
        $this->own = $this->controller->pollingStations()->firstOrFail();
        $this->neighbour = PollingStation::where('municipality_id', $this->municipality->id)->where('id', '!=', $this->own->id)->firstOrFail();
    }

    private function numbers(int $registered): array
    {
        $voted = (int) ($registered * 0.6);

        return [
            'registered_voters' => $registered, 'ballots_received' => $registered + 20, 'ballots_unused' => $registered + 20 - $voted,
            'voters_voted' => $voted, 'ballots_in_box' => $voted, 'ballots_valid' => $voted - 5, 'ballots_invalid' => 5,
        ];
    }

    private function votes(Election $election, int $valid): array
    {
        $lists = $election->lists()->pluck('id');
        $votes = $lists->mapWithKeys(fn ($id) => [$id => 0])->all();
        $votes[$lists->first()] = $valid;

        return $votes;
    }

    public function test_demo_controller_is_assigned_exactly_one_station_of_its_municipality(): void
    {
        $this->assertSame(UserRole::Controller, $this->controller->role);
        $this->assertSame($this->municipality->id, $this->controller->municipality_id);
        $this->assertCount(1, $this->controller->assignedStationIds());
        $this->assertSame($this->municipality->id, $this->own->municipality_id);
    }

    public function test_reads_whole_municipality_but_nothing_outside_it(): void
    {
        $ownMunicipality = Protocol::whereHas('pollingStation', fn ($q) => $q->where('municipality_id', $this->municipality->id))->firstOrFail();
        $foreign = Protocol::whereHas('pollingStation', fn ($q) => $q->where('municipality_id', '!=', $this->municipality->id))->firstOrFail();

        $this->actingAs($this->controller)->get('/admin/protocols')->assertOk()->assertSee($ownMunicipality->pollingStation->name);
        $this->actingAs($this->controller)->get("/admin/protocols/{$ownMunicipality->id}")->assertOk();
        $this->actingAs($this->controller)->get("/admin/protocols/{$foreign->id}")->assertNotFound();
        $this->actingAs($this->controller)->get("/admin/protocols/{$foreign->id}/edit")->assertNotFound();

        foreach (['/admin/elections', '/admin/users', '/admin/snapshots', '/admin/manage-settings', '/admin/electoral-lists', '/admin/submitters'] as $url) {
            $this->actingAs($this->controller)->get($url)->assertForbidden();
        }
        foreach (['/admin/turnout-snapshots', '/admin/incidents', '/admin/incidents/create', '/admin/protocols/create', '/admin/polling-stations'] as $url) {
            $this->actingAs($this->controller)->get($url)->assertOk();
        }
    }

    public function test_neighbouring_station_of_own_municipality_is_read_only(): void
    {
        $protocol = Protocol::where('polling_station_id', $this->neighbour->id)->where('status', '!=', ProtocolStatus::Verified)->first()
            ?? Protocol::whereHas('pollingStation', fn ($q) => $q->where('municipality_id', $this->municipality->id)->where('id', '!=', $this->own->id))
                ->where('status', '!=', ProtocolStatus::Verified)->firstOrFail();

        $this->assertFalse($this->controller->can('update', $protocol));
        $this->assertTrue($this->controller->can('view', $protocol));
        $this->actingAs($this->controller)->get("/admin/protocols/{$protocol->id}")->assertOk()
            ->assertDontSee("/admin/protocols/{$protocol->id}/edit");
        $this->actingAs($this->controller)->get("/admin/protocols/{$protocol->id}/edit")->assertForbidden();

        $this->expectException(AuthorizationException::class);
        app(ProtocolService::class)->save(
            new Protocol(['election_id' => $this->neighbour->election_id, 'polling_station_id' => $this->neighbour->id, 'round' => 2]),
            $this->numbers($this->neighbour->registered_voters), $this->votes($this->neighbour->election, 10), $this->controller,
        );
    }

    public function test_writes_its_own_station_but_cannot_verify(): void
    {
        $election = $this->own->election;
        $service = app(ProtocolService::class);
        Protocol::where('polling_station_id', $this->own->id)->delete();

        $protocol = $service->save(
            new Protocol(['election_id' => $election->id, 'polling_station_id' => $this->own->id, 'round' => 1]),
            $this->numbers($this->own->registered_voters), $this->votes($election, (int) ($this->own->registered_voters * 0.6) - 5), $this->controller,
        );

        $this->assertSame(ProtocolStatus::Entered, $protocol->status);
        $this->assertSame($this->controller->id, $protocol->entered_by);
        $this->assertTrue($this->controller->can('update', $protocol));
        $this->assertFalse($this->controller->can('verify', $protocol));
        $this->actingAs($this->controller)->get("/admin/protocols/{$protocol->id}")->assertOk()
            ->assertSee("/admin/protocols/{$protocol->id}/edit");
        $this->actingAs($this->controller)->get("/admin/protocols/{$protocol->id}/edit")->assertOk();

        $this->expectException(\RuntimeException::class);
        $service->verify($protocol, $this->controller);
    }

    public function test_form_rejects_a_tampered_station_id(): void
    {
        $election = $this->neighbour->election;

        Livewire::actingAs($this->controller)
            ->test(CreateProtocol::class)
            ->fillForm([
                'election_id' => $election->id,
                'polling_station_id' => $this->neighbour->id,
                'round' => 1,
                ...$this->numbers(1000),
                'votes' => $this->votes($election, 595),
            ])
            ->call('create')
            ->assertHasFormErrors(['polling_station_id']);

        $this->assertNull(Protocol::where('polling_station_id', $this->neighbour->id)->where('entered_by', $this->controller->id)->first());
    }

    public function test_reports_only_from_its_own_station(): void
    {
        $service = app(IncidentService::class);
        $report = fn (PollingStation $s) => $service->report([
            'polling_station_id' => $s->id,
            'category' => IncidentCategory::Facility->value,
            'severity' => IncidentSeverity::Low->value,
            'description' => 'Nestanak struje na biračkom mestu, glasanje nastavljeno uz agregat.',
        ], $this->controller);

        $incident = $report($this->own);
        $this->assertSame($this->controller->id, $incident->reported_by);

        $foreignIncident = Incident::where('municipality_id', '!=', $this->municipality->id)->firstOrFail();
        $this->assertFalse($this->controller->can('triage', $incident));
        $this->assertFalse($this->controller->can('view', $foreignIncident));

        Livewire::actingAs($this->controller)
            ->test(CreateIncident::class)
            ->fillForm([
                'election_id' => $this->neighbour->election_id,
                'polling_station_id' => $this->neighbour->id,
                'category' => IncidentCategory::Facility->value,
                'severity' => IncidentSeverity::Low->value,
                'occurred_at' => now()->subMinutes(3)->format('Y-m-d H:i:s'),
                'description' => 'Pokušaj prijave za susedno biračko mesto.',
            ])
            ->call('create')
            ->assertHasFormErrors(['polling_station_id']);

        $this->expectException(AuthorizationException::class);
        $report($this->neighbour);
    }

    public function test_turnout_is_one_figure_per_station_and_cutoff_and_only_for_own_station(): void
    {
        $this->actingAs($this->controller);
        $base = ['election_id' => $this->own->election_id, 'municipality_id' => $this->municipality->id, 'polling_station_id' => $this->own->id, 'cutoff' => '09:00'];

        $first = TurnoutSnapshotResource::record($base + ['voters_voted' => 120]);
        $second = TurnoutSnapshotResource::record($base + ['voters_voted' => 125]);

        $this->assertSame($first->id, $second->id, 'a repeated entry corrects the first instead of adding a second row');
        $this->assertSame(125, $second->fresh()->voters_voted);
        $this->assertSame(1, TurnoutSnapshot::where('polling_station_id', $this->own->id)->where('cutoff', '09:00')->count());
        $this->assertTrue($this->controller->can('update', $second));

        try {
            TurnoutSnapshotResource::record(['polling_station_id' => null] + $base + ['voters_voted' => 9999]);
            $this->fail('a controller must not enter a whole-municipality figure');
        } catch (AuthorizationException) {
        }

        $this->expectException(AuthorizationException::class);
        TurnoutSnapshotResource::record(['polling_station_id' => $this->neighbour->id] + $base + ['voters_voted' => 50]);
    }

    public function test_operator_and_verifier_still_write_their_whole_municipality(): void
    {
        $operator = User::where('email', 'operater.nis@example.com')->firstOrFail();
        $verifier = User::where('email', 'oik.nis@example.com')->firstOrFail();
        $foreign = PollingStation::where('municipality_id', '!=', $this->municipality->id)->firstOrFail();

        $this->assertTrue($operator->canWriteStation($this->own));
        $this->assertTrue($operator->canWriteStation($this->neighbour));
        $this->assertFalse($operator->canWriteStation($foreign));
        $this->assertTrue($verifier->canWriteStation($this->neighbour));
        $this->assertFalse($verifier->canWriteStation($foreign));

        $verified = Protocol::where('status', ProtocolStatus::Verified)
            ->whereHas('pollingStation', fn ($q) => $q->where('municipality_id', $this->municipality->id))->firstOrFail();
        $this->assertFalse($operator->can('update', $verified));
        $this->assertFalse($operator->can('unverify', $verified));
        $this->assertTrue($verifier->can('unverify', $verified));
        $this->actingAs($operator)->get("/admin/protocols/{$verified->id}/edit")->assertForbidden();
    }
}
