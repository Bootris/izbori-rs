<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ProtocolStatus;
use App\Enums\UserRole;
use App\Models\Election;
use App\Models\PollingStation;
use App\Models\Protocol;
use App\Models\User;
use App\Services\Protocols\ProtocolService;
use Database\Seeders\DemoElectionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class ProtocolWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private Election $election;

    private PollingStation $station;

    private ProtocolService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoElectionSeeder::class);
        $this->election = Election::firstOrFail();
        $this->station = PollingStation::doesntHave('protocols')->firstOrFail();
        $this->service = app(ProtocolService::class);
    }

    private function votes(int $valid): array
    {
        $lists = $this->election->lists()->pluck('id');
        $votes = $lists->mapWithKeys(fn ($id) => [$id => 0])->all();
        $votes[$lists->first()] = $valid;

        return $votes;
    }

    private function numbers(array $overrides = []): array
    {
        return $overrides + [
            'registered_voters' => 1000, 'ballots_received' => 1020, 'ballots_unused' => 420,
            'voters_voted' => 600, 'ballots_in_box' => 600, 'ballots_valid' => 590, 'ballots_invalid' => 10,
        ];
    }

    public function test_consistent_protocol_is_entered_then_verified_and_edited_back_to_entered(): void
    {
        $operator = User::factory()->create(['role' => UserRole::Operator]);
        $verifier = User::factory()->create(['role' => UserRole::Verifier]);

        $protocol = $this->service->save(
            new Protocol(['election_id' => $this->election->id, 'polling_station_id' => $this->station->id, 'round' => 1]),
            $this->numbers(), $this->votes(590), $operator,
        );

        $this->assertSame(ProtocolStatus::Entered, $protocol->status);
        $this->assertSame(0, $protocol->deviation);
        $this->assertSame($operator->id, $protocol->entered_by);
        $this->assertSame('RS', $protocol->unit->code);
        $this->assertSame('created', $protocol->revisions()->first()->action);

        $this->service->verify($protocol, $verifier);
        $this->assertSame(ProtocolStatus::Verified, $protocol->fresh()->status);

        $edited = $this->service->save($protocol->fresh(), $this->numbers(['ballots_valid' => 589, 'ballots_invalid' => 11]), $this->votes(589), $verifier);
        $this->assertSame(ProtocolStatus::Entered, $edited->status);
        $this->assertNull($edited->verified_at);
        $this->assertSame(2, $edited->revision);
        $this->assertSame(['ballots_valid' => [590, 589], 'ballots_invalid' => [10, 11], 'items' => [$this->votes(590), $this->votes(589)]], $edited->revisions()->first()->changes);
    }

    public function test_inconsistent_protocol_is_flagged_and_cannot_be_verified(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $protocol = $this->service->save(
            new Protocol(['election_id' => $this->election->id, 'polling_station_id' => $this->station->id, 'round' => 1]),
            $this->numbers(['ballots_in_box' => 597, 'ballots_valid' => 587]), $this->votes(587), $admin,
        );

        $this->assertSame(ProtocolStatus::Flagged, $protocol->status);
        $this->assertSame(-3, $protocol->deviation);
        $this->assertArrayHasKey('K4', $protocol->validation_errors);

        $this->expectException(RuntimeException::class);
        $this->service->verify($protocol, $admin);
    }

    public function test_operator_cannot_verify(): void
    {
        $operator = User::factory()->create(['role' => UserRole::Operator]);
        $protocol = $this->service->save(
            new Protocol(['election_id' => $this->election->id, 'polling_station_id' => $this->station->id, 'round' => 1]),
            $this->numbers(), $this->votes(590), $operator,
        );

        $this->expectException(RuntimeException::class);
        $this->service->verify($protocol, $operator);
    }
}
