<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\IncidentCategory;
use App\Enums\IncidentSeverity;
use App\Enums\IncidentStatus;
use App\Enums\SnapshotSource;
use App\Enums\UserRole;
use App\Filament\Resources\Incidents\Pages\CreateIncident;
use App\Models\Election;
use App\Models\Incident;
use App\Models\Municipality;
use App\Models\PollingStation;
use App\Models\User;
use App\Notifications\IncidentReported;
use App\Services\Incidents\IncidentService;
use App\Services\Snapshots\SnapshotPublisher;
use Database\Seeders\DemoElectionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class IncidentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoElectionSeeder::class);
    }

    public function test_a_report_is_stamped_by_the_server_and_alarms_everyone_else(): void
    {
        $municipality = Municipality::where('name', 'Niš – Medijana')->firstOrFail();
        $operator = User::where('email', 'operater.nis@example.com')->firstOrFail();
        $station = PollingStation::where('municipality_id', $municipality->id)->firstOrFail();
        $others = User::where('id', '!=', $operator->id)->count();
        $this->assertGreaterThan(0, $others);

        $this->travelTo('2026-12-13 09:41:07');
        $incident = app(IncidentService::class)->report([
            'polling_station_id' => $station->id,
            'category' => IncidentCategory::Intimidation->value,
            'severity' => IncidentSeverity::High->value,
            'description' => 'Grupa ljudi ispred ulaza zaustavlja birače i traži da pokažu listić.',
            'occurred_at' => '2026-12-13 09:35:00',
        ], $operator);
        $this->travelBack();

        $this->assertSame('2026-12-13 09:41:07', $incident->reported_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-12-13 09:35:00', $incident->occurred_at->format('Y-m-d H:i:s'));
        $this->assertSame(IncidentStatus::Open, $incident->status);
        $this->assertSame($station->election_id, $incident->election_id);
        $this->assertSame($municipality->id, $incident->municipality_id);
        $this->assertSame($operator->id, $incident->reported_by);
        $this->assertFalse($incident->is_public, 'a fresh report is internal until an admin publishes it');

        // Every other account got the alarm in its bell, the reporter did not.
        $this->assertDatabaseCount('notifications', $others);
        $this->assertSame(0, $operator->notifications()->count());
        $notification = User::where('role', UserRole::Admin)->firstOrFail()->notifications()->first();
        $this->assertSame(IncidentReported::class, $notification->type);
        $this->assertStringContainsString("BM {$station->number}", $notification->data['title']);
        $this->assertStringContainsString('09:41:07', $notification->data['body']);
    }

    public function test_operator_cannot_report_for_a_station_outside_own_municipality(): void
    {
        $municipality = Municipality::where('name', 'Niš – Medijana')->firstOrFail();
        $operator = User::where('email', 'operater.nis@example.com')->firstOrFail();
        $foreign = PollingStation::where('municipality_id', '!=', $municipality->id)->firstOrFail();
        $before = Incident::count();

        $this->expectException(AuthorizationException::class);
        try {
            app(IncidentService::class)->report([
                'polling_station_id' => $foreign->id,
                'category' => IncidentCategory::Other->value,
                'severity' => IncidentSeverity::Low->value,
                'description' => 'Pokušaj prijave za tuđu opštinu.',
            ], $operator);
        } finally {
            $this->assertSame($before, Incident::count());
            $this->assertDatabaseCount('notifications', 0);
        }
    }

    public function test_the_report_itself_cannot_be_rewritten(): void
    {
        $incident = Incident::firstOrFail();

        $this->expectException(\LogicException::class);
        $incident->update(['description' => 'Prepravljen opis.']);
    }

    public function test_triage_moves_status_and_records_who_did_it(): void
    {
        $admin = User::where('role', UserRole::Admin)->firstOrFail();
        $incident = Incident::where('status', IncidentStatus::Open)->firstOrFail();
        $service = app(IncidentService::class);

        $service->review($incident, $admin);
        $this->assertSame(IncidentStatus::InReview, $incident->fresh()->status);
        $this->assertSame($admin->id, $incident->fresh()->reviewed_by);

        $service->close($incident, $admin, 'Policija obaveštena, birački odbor nastavio rad.');
        $fresh = $incident->fresh();
        $this->assertSame(IncidentStatus::Resolved, $fresh->status);
        $this->assertSame($admin->id, $fresh->resolved_by);
        $this->assertNotNull($fresh->resolved_at);
        $this->assertFalse($fresh->isOpen());
    }

    public function test_operator_sees_only_own_municipality_and_can_file_a_report(): void
    {
        $municipality = Municipality::where('name', 'Niš – Medijana')->firstOrFail();
        $operator = User::where('email', 'operater.nis@example.com')->firstOrFail();
        $own = Incident::where('municipality_id', $municipality->id)->firstOrFail();
        $foreign = Incident::where('municipality_id', '!=', $municipality->id)->firstOrFail();

        $this->actingAs($operator)->get('/admin/incidents')->assertOk()->assertSee($own->pollingStation->name);
        $this->actingAs($operator)->get('/admin/incidents/create')->assertOk();
        $this->actingAs($operator)->get("/admin/incidents/{$own->id}")->assertOk();
        $this->actingAs($operator)->get("/admin/incidents/{$foreign->id}")->assertNotFound();
    }

    public function test_the_admin_form_files_a_report_through_the_service(): void
    {
        $municipality = Municipality::where('name', 'Niš – Medijana')->firstOrFail();
        $operator = User::where('email', 'operater.nis@example.com')->firstOrFail();
        $station = PollingStation::where('municipality_id', $municipality->id)->firstOrFail();
        $before = Incident::count();

        Livewire::actingAs($operator)
            ->test(CreateIncident::class)
            ->fillForm([
                'election_id' => $station->election_id,
                'polling_station_id' => $station->id,
                'category' => IncidentCategory::Facility->value,
                'severity' => IncidentSeverity::Low->value,
                'occurred_at' => now()->subMinutes(3)->format('Y-m-d H:i:s'),
                'description' => 'Ulaz za osobe sa invaliditetom zaključan, otvoren posle poziva domaru.',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame($before + 1, Incident::count());
        $incident = Incident::latest('id')->firstOrFail();
        $this->assertSame($operator->id, $incident->reported_by);
        $this->assertSame($municipality->id, $incident->municipality_id);
        $this->assertNotNull($incident->reported_at);
        $this->assertGreaterThan(0, User::where('id', '!=', $operator->id)->first()->unreadNotifications()->count());
    }

    public function test_only_public_incidents_reach_the_snapshot(): void
    {
        Storage::fake('snapshots');
        $election = Election::firstOrFail();
        $disk = Storage::disk('snapshots');

        $snapshot = app(SnapshotPublisher::class)->publish($election, SnapshotSource::Incidents);

        $file = json_decode($disk->get("{$snapshot->path}/incidents.json"), true);
        $this->assertSame('incidents', $file['meta']['source']);
        $this->assertNull($file['meta']['processed']);
        $this->assertCount(Incident::public()->count(), $file['list']);
        $this->assertGreaterThan(0, count($file['list']));
        foreach ($file['list'] as $row) {
            $this->assertArrayNotHasKey('reported_by', $row, 'the reporter never leaves the admin');
            $this->assertMatchesRegularExpression('/^\d{2}-\d{4}-/', $row['station_id']);
            if ($row['status'] === 'in_review' || $row['status'] === 'open') {
                $this->assertNull($row['resolution']);
            }
        }
        $reported = array_column($file['list'], 'reported_at');
        $sorted = $reported;
        rsort($sorted);
        $this->assertSame($sorted, $reported, 'newest report first');

        $config = json_decode($disk->get("{$election->slug}/config.json"), true);
        $this->assertSame($snapshot->version, $config['incidents']);
        $this->assertNull($config['results']);
        $index = json_decode($disk->get('index.json'), true);
        $this->assertArrayHasKey('links', $index['site']);
    }
}
