<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\SnapshotSource;
use App\Models\Election;
use App\Models\Snapshot;
use App\Services\Snapshots\SnapshotPublisher;
use Database\Seeders\DemoElectionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SnapshotPublishTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('snapshots');
        $this->seed(DemoElectionSeeder::class);
    }

    public function test_publishing_all_sources_writes_files_pointer_index_and_hash_chain(): void
    {
        $election = Election::firstOrFail();
        $publisher = app(SnapshotPublisher::class);
        $disk = Storage::disk('snapshots');

        $registry = $publisher->publish($election, SnapshotSource::Registry);
        $turnout = $publisher->publish($election, SnapshotSource::Turnout);
        $results = $publisher->publish($election, SnapshotSource::Results);

        $config = json_decode($disk->get("{$election->slug}/config.json"), true);
        $this->assertSame($registry->version, $config['registry']);
        $this->assertSame($turnout->version, $config['turnout']);
        $this->assertSame($results->version, $config['results']);

        $index = json_decode($disk->get('index.json'), true);
        $this->assertSame($election->slug, $index['default']);
        $this->assertSame($results->version, $index['elections'][0]['sources']['results']);

        foreach (['election.json', 'codebooks.json', 'districts.json', 'municipalities.json', 'units.json', 'submitters.json', 'lists.json', 'deadlines.json', 'manifest.json'] as $file) {
            $this->assertTrue($disk->exists("{$registry->path}/{$file}"), "missing {$file}");
        }
        foreach (['results-summary.json', 'results-unit-RS.json', 'composition.json', 'winners.json', 'close-races.json', 'flagged.json', 'manifest.json', '20/protocols-20-2001.json', '20/results-20-2001.json'] as $file) {
            $this->assertTrue($disk->exists("{$results->path}/{$file}"), "missing {$file}");
        }

        $districtTurnout = json_decode($disk->get("{$turnout->path}/turnout-districts.json"), true)['list'];
        foreach ($districtTurnout as $row) {
            $this->assertIsString($row['district_code'], 'district codes must stay strings (PHP int-casts numeric array keys)');
        }

        $summary = json_decode($disk->get("{$results->path}/results-summary.json"), true);
        $this->assertSame('results', $summary['meta']['source']);
        $this->assertGreaterThan(0, $summary['meta']['processed']);
        $unit = $summary['data']['units'][0];
        $this->assertSame(250, array_sum(array_column($unit['lists'], 'seats')));
        $this->assertSame($unit['ballots_valid'], array_sum(array_column($unit['lists'], 'votes')));

        $composition = json_decode($disk->get("{$results->path}/composition.json"), true)['data'];
        $this->assertSame(250, $composition['seats_allocated']);
        $this->assertCount(250, $composition['seats']);
        $this->assertNotNull($composition['seats'][0]['candidate']);

        $manifest = json_decode($disk->get("{$results->path}/manifest.json"), true);
        $this->assertSame($results->hash, $manifest['hash']);
        $this->assertNull($manifest['previous_hash']);
        $this->assertSame(hash('sha256', $disk->get("{$results->path}/composition.json")), $manifest['files']['composition.json']);

        // Second results publish chains to the first and moves the pointer.
        $again = $publisher->publish($election, SnapshotSource::Results);
        $this->assertSame($results->hash, $again->previous_hash);
        $this->assertNotSame($results->version, $again->version);
        $this->assertTrue($disk->exists("{$results->path}/results-summary.json"), 'old version must stay immutable/available');
        $this->assertSame($again->version, json_decode($disk->get("{$election->slug}/config.json"), true)['results']);
        $this->assertSame(4, Snapshot::count());
    }

    public function test_publish_command_works(): void
    {
        $this->artisan('izbori:publish', ['election' => DemoElectionSeeder::SLUG, '--source' => ['registry']])
            ->assertSuccessful();

        $this->assertTrue(Storage::disk('snapshots')->exists(DemoElectionSeeder::SLUG.'/config.json'));
    }
}
