<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ElectionStatus;
use App\Enums\SnapshotSource;
use App\Enums\UserRole;
use App\Models\Election;
use App\Models\PollingStation;
use App\Models\Protocol;
use App\Models\User;
use App\Services\Incidents\IncidentService;
use App\Services\Protocols\ProtocolService;
use App\Services\Snapshots\PublishBlockedException;
use App\Services\Snapshots\SnapshotPublisher;
use Database\Seeders\DemoElectionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

/**
 * Results reach the public site only while counting or final AND after the
 * polls closed; a final election takes no more entries.
 */
class PublishGuardTest extends TestCase
{
    use RefreshDatabase;

    private Election $election;

    private SnapshotPublisher $publisher;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('snapshots');
        $this->seed(DemoElectionSeeder::class);
        $this->election = Election::firstOrFail();
        $this->publisher = app(SnapshotPublisher::class);
    }

    public function test_results_are_blocked_while_the_registry_is_still_the_status(): void
    {
        $this->election->update(['status' => ElectionStatus::Registry]);

        $this->publisher->publish($this->election, SnapshotSource::Registry);
        $this->publisher->publish($this->election, SnapshotSource::Turnout);

        try {
            $this->publisher->publish($this->election, SnapshotSource::Results);
            $this->fail('results must not be published before counting');
        } catch (PublishBlockedException $e) {
            $this->assertStringContainsString('Registar objavljen', $e->getMessage());
        }

        $config = json_decode(Storage::disk('snapshots')->get("{$this->election->slug}/config.json"), true);
        $this->assertNotNull($config['registry']);
        $this->assertNull($config['results']);
        $this->assertSame(0, $this->election->snapshots()->where('source', SnapshotSource::Results)->count(), 'no results row, no results folder');
    }

    public function test_results_are_blocked_before_the_polls_close_even_in_counting(): void
    {
        $this->election->update(['status' => ElectionStatus::Counting, 'election_date' => now(config('izbori.polls.timezone'))->addDays(3)->toDateString()]);

        $this->assertFalse($this->election->resultsPublishable());
        $this->expectException(PublishBlockedException::class);
        $this->publisher->publish($this->election, SnapshotSource::Results);
    }

    public function test_results_publish_once_counting_started_after_the_polls_closed(): void
    {
        $this->election->update(['status' => ElectionStatus::Counting, 'election_date' => now(config('izbori.polls.timezone'))->subDay()->toDateString()]);

        $this->assertTrue($this->election->resultsPublishable());
        $snapshot = $this->publisher->publish($this->election, SnapshotSource::Results);
        $this->assertSame(SnapshotSource::Results, $snapshot->source);
    }

    public function test_the_console_command_refuses_results_and_still_publishes_the_registry(): void
    {
        $this->election->update(['status' => ElectionStatus::Registry]);

        $this->artisan('izbori:publish', ['election' => $this->election->slug, '--source' => ['registry', 'results']])
            ->expectsOutputToContain('Objava rezultata je blokirana')
            ->assertFailed();

        $this->assertTrue(Storage::disk('snapshots')->exists("{$this->election->slug}/config.json"));
    }

    public function test_moving_the_status_back_withdraws_published_results_from_the_site(): void
    {
        $this->publisher->publish($this->election, SnapshotSource::Registry);
        $results = $this->publisher->publish($this->election, SnapshotSource::Results);
        $disk = Storage::disk('snapshots');
        $this->assertSame($results->version, json_decode($disk->get("{$this->election->slug}/config.json"), true)['results']);

        $this->election->update(['status' => ElectionStatus::Registry]);

        $config = json_decode($disk->get("{$this->election->slug}/config.json"), true);
        $this->assertNull($config['results'], 'the pointer is withdrawn');
        $this->assertTrue($disk->exists("{$results->path}/manifest.json"), 'the immutable version stays on disk');
        $index = json_decode($disk->get('index.json'), true);
        $this->assertNull($index['elections'][0]['sources']['results']);
        $this->assertSame('registry', $index['elections'][0]['status']);
    }

    public function test_a_final_election_is_locked_for_entries(): void
    {
        $this->election->update(['status' => ElectionStatus::Final]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $station = PollingStation::doesntHave('protocols')->firstOrFail();
        $entered = Protocol::where('status', 'entered')->firstOrFail();

        $this->assertFalse($admin->can('update', $entered));
        $this->assertFalse($admin->can('verify', $entered));
        $this->actingAs($admin)->get("/admin/protocols/{$entered->id}/edit")->assertForbidden();

        try {
            app(ProtocolService::class)->save(
                new Protocol(['election_id' => $this->election->id, 'polling_station_id' => $station->id, 'round' => 1]),
                ['registered_voters' => 10, 'ballots_received' => 10, 'ballots_unused' => 5, 'voters_voted' => 5, 'ballots_in_box' => 5, 'ballots_valid' => 5, 'ballots_invalid' => 0],
                [], $admin,
            );
            $this->fail('a final election must refuse new protocols');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Konačni rezultati', $e->getMessage());
        }

        $this->expectException(AuthorizationException::class);
        app(IncidentService::class)->report([
            'polling_station_id' => $station->id, 'category' => 'facility', 'severity' => 'low', 'description' => 'Prijava posle zaključavanja izbora.',
        ], $admin);
    }
}
