<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PWA (manifest, service worker, ikona) a meta tagy pro sdílení (Open Graph).
 */
class PwaMetaTest extends TestCase
{
    use RefreshDatabase;

    public function test_layout_renders_open_graph_and_pwa_tags(): void
    {
        $html = (string) $this->get('/')->assertOk()->content();

        $this->assertStringContainsString('<meta property="og:title"', $html);
        $this->assertStringContainsString('<meta property="og:image"', $html);
        $this->assertStringContainsString('name="twitter:card" content="summary_large_image"', $html);
        $this->assertStringContainsString('name="theme-color" content="#4338ca"', $html);
        $this->assertStringContainsString('rel="manifest" href="/site.webmanifest"', $html);
        $this->assertStringContainsString("navigator.serviceWorker.register('/sw.js')", $html);
    }

    public function test_pwa_static_assets_exist(): void
    {
        $public = public_path();

        $this->assertFileExists($public.'/sw.js');
        $this->assertFileExists($public.'/icon.svg');
        $this->assertFileExists($public.'/site.webmanifest');
    }

    public function test_manifest_is_valid_json_with_required_fields(): void
    {
        $manifest = json_decode((string) file_get_contents(public_path('site.webmanifest')), true);

        $this->assertIsArray($manifest);
        $this->assertSame('VKV Provozní aktiv', $manifest['name'] ?? null);
        $this->assertSame('standalone', $manifest['display'] ?? null);
        $this->assertSame('#4338ca', $manifest['theme_color'] ?? null);
        $this->assertNotEmpty($manifest['icons'] ?? []);
    }
}
