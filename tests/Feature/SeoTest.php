<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SEO základ (dávka A): canonical, per-page meta, PNG og-image, H1,
 * robots.txt a sitemap.xml.
 */
class SeoTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_has_canonical_h1_and_png_og_image(): void
    {
        $html = (string) $this->get('/')->assertOk()->content();

        $this->assertStringContainsString('<link rel="canonical"', $html);
        $this->assertStringContainsString('<h1', $html);
        $this->assertStringContainsString('og-image.png', $html);
        $this->assertStringNotContainsString('og:image" content="'.url('icon.svg').'"', $html);
    }

    public function test_pages_have_distinct_meta_descriptions(): void
    {
        $home = (string) $this->get('/')->assertOk()->content();
        $hlaseni = (string) $this->get('/hlaseni')->assertOk()->content();

        $extract = static function (string $html): ?string {
            preg_match('/<meta name="description" content="([^"]*)"/', $html, $m);

            return $m[1] ?? null;
        };

        $this->assertNotNull($extract($home));
        $this->assertNotNull($extract($hlaseni));
        $this->assertNotSame($extract($home), $extract($hlaseni), 'Stránky mají mít odlišný meta popis.');
    }

    public function test_og_image_asset_exists(): void
    {
        $this->assertFileExists(public_path('og-image.png'));
    }

    public function test_robots_txt_disallows_admin_and_links_sitemap(): void
    {
        $res = $this->get('/robots.txt')->assertOk();
        $res->assertHeader('Content-Type', 'text/plain; charset=UTF-8');

        $body = (string) $res->content();
        $this->assertStringContainsString('Disallow: /admin', $body);
        $this->assertStringContainsString('Disallow: /adminer', $body);
        $this->assertStringContainsString('Sitemap: '.url('/sitemap.xml'), $body);
    }

    public function test_sitemap_xml_is_valid_and_lists_home(): void
    {
        $res = $this->get('/sitemap.xml')->assertOk();
        $res->assertHeader('Content-Type', 'application/xml; charset=UTF-8');

        $body = (string) $res->content();
        $this->assertStringContainsString('<urlset', $body);
        $this->assertStringContainsString('<loc>'.url('/').'</loc>', $body);

        // Musí být validní XML.
        $this->assertInstanceOf(\SimpleXMLElement::class, simplexml_load_string($body));
    }
}
