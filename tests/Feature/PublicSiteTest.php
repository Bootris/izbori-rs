<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicSiteTest extends TestCase
{
    use RefreshDatabase;

    public function test_spa_shell_is_served_for_public_routes(): void
    {
        $this->withoutVite();

        foreach (['/', '/parlament-2026/skupstina', '/neki/duboki/put'] as $url) {
            $this->get($url)->assertOk()->assertSee('id="root"', false)->assertSee('__IZBORI__', false);
        }
    }

    public function test_spa_shell_is_stateless_and_cacheable(): void
    {
        $this->withoutVite();

        $response = $this->get('/parlament-2026/skupstina')->assertOk();

        // No session or CSRF cookie for an anonymous visitor: nothing to write per request.
        $this->assertSame([], $response->headers->getCookies(), 'the SPA shell must not start a session');
        $this->assertStringContainsString('public', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('max-age=60', (string) $response->headers->get('Cache-Control'));
    }

    public function test_reserved_paths_are_not_swallowed_by_the_spa(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
        $this->get('/up')->assertOk();
        $this->get('/data/index.json')->assertNotFound();
    }
}
