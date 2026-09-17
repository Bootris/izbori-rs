<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ProtocolStatus;
use App\Enums\UserRole;
use App\Models\Election;
use App\Models\Municipality;
use App\Models\Protocol;
use App\Models\User;
use Database\Seeders\DemoElectionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoElectionSeeder::class);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::Admin]);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_admin_can_open_every_page(): void
    {
        $admin = $this->admin();
        $election = Election::firstOrFail();
        $protocol = Protocol::where('status', ProtocolStatus::Entered)->firstOrFail();
        $list = $election->lists()->firstOrFail();

        foreach ([
            '/admin',
            '/admin/elections',
            '/admin/elections/create',
            "/admin/elections/{$election->id}/edit",
            '/admin/districts',
            '/admin/municipalities',
            '/admin/polling-stations',
            '/admin/submitters',
            '/admin/electoral-lists',
            '/admin/electoral-lists/create',
            "/admin/electoral-lists/{$list->id}/edit",
            '/admin/protocols',
            '/admin/protocols/create',
            "/admin/protocols/{$protocol->id}",
            "/admin/protocols/{$protocol->id}/edit",
            '/admin/turnout-snapshots',
            '/admin/incidents',
            '/admin/incidents/create',
            '/admin/incidents/'.\App\Models\Incident::query()->value('id'),
            '/admin/snapshots',
            '/admin/users',
            '/admin/manage-settings',
        ] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }

        // A verified protocol has no edit page — it goes back "na ispravku" first (ProtocolPolicy::update).
        $verified = Protocol::where('status', ProtocolStatus::Verified)->firstOrFail();
        $this->actingAs($admin)->get("/admin/protocols/{$verified->id}")->assertOk();
        $this->actingAs($admin)->get("/admin/protocols/{$verified->id}/edit")->assertForbidden();
    }

    public function test_operator_only_sees_own_municipality_and_no_registry(): void
    {
        $municipality = Municipality::where('name', 'Niš – Medijana')->firstOrFail();
        $operator = User::factory()->create(['role' => UserRole::Operator, 'municipality_id' => $municipality->id]);
        $foreign = Protocol::whereHas('pollingStation', fn ($q) => $q->where('municipality_id', '!=', $municipality->id))->firstOrFail();
        $own = Protocol::whereHas('pollingStation', fn ($q) => $q->where('municipality_id', $municipality->id))->firstOrFail();

        $this->actingAs($operator)->get('/admin/protocols')->assertOk()->assertSee($own->pollingStation->name);
        $this->actingAs($operator)->get("/admin/protocols/{$own->id}")->assertOk();
        $this->actingAs($operator)->get("/admin/protocols/{$foreign->id}")->assertNotFound();

        foreach (['/admin/elections', '/admin/users', '/admin/snapshots', '/admin/manage-settings', '/admin/electoral-lists'] as $url) {
            $this->actingAs($operator)->get($url)->assertForbidden();
        }
    }

    public function test_verifier_without_municipality_sees_no_protocols(): void
    {
        $verifier = User::factory()->create(['role' => UserRole::Verifier, 'municipality_id' => null]);
        $protocol = Protocol::firstOrFail();

        $this->actingAs($verifier)->get('/admin/protocols')->assertOk()->assertDontSee($protocol->pollingStation->name);
        $this->actingAs($verifier)->get("/admin/protocols/{$protocol->id}")->assertNotFound();
    }
}
