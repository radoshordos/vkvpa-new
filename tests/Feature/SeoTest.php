<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Edihead;
use App\Models\EdiRound;
use App\Services\Edi\EdiImportService;
use App\Services\Edi\EdiParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SEO základ: canonical, per-page meta, PNG og-image, H1, robots.txt,
 * sitemap.xml. Dávka B: hezké URL /vysledky/{kolo} jako kanonická adresa,
 * per-page meta popisy, strukturovaná data (JSON-LD) a rozšířená sitemapa.
 */
class SeoTest extends TestCase
{
    use RefreshDatabase;

    /** Uzavřené kolo (po uzávěrce) – jeho výsledky/deníky jsou veřejné. */
    private function uzavreneKolo(): EdiRound
    {
        return EdiRound::create([
            'starts_at' => '2026-03-15',
            'closes_at' => now()->subDay()->toDateTimeString(),
            'name' => '03/2026',
            'note' => '',
            'evaluated_at' => now()->toDateTimeString(),
        ]);
    }

    private function importSample(): Edihead
    {
        $edi = (string) file_get_contents(__DIR__.'/../fixtures/sample.edi');

        return new EdiImportService()->import(new EdiParser()->parse($edi));
    }

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

    public function test_results_round_uses_pretty_url_as_canonical(): void
    {
        $kolo = $this->uzavreneKolo();

        $canonical = route('vysledkova_listina', ['kolo' => $kolo->id]);

        // route() generuje hezké URL (segment v cestě, ne ?kolo=).
        $this->assertStringContainsString('/vysledky/'.$kolo->id, $canonical);
        $this->assertStringNotContainsString('?kolo=', $canonical);

        // Stránka konkrétního kola se prohlašuje za kanonickou sama.
        $html = (string) $this->get($canonical)->assertOk()->content();
        $this->assertStringContainsString('<link rel="canonical" href="'.$canonical.'"', $html);

        // Bezparametrické /vysledky (poslední uzavřené kolo) směruje canonical
        // na hezké URL konkrétního kola – zabrání kanibalizaci mezi koly.
        $bare = (string) $this->get('/vysledky')->assertOk()->content();
        $this->assertStringContainsString('<link rel="canonical" href="'.$canonical.'"', $bare);
    }

    public function test_public_pages_have_distinct_meta_descriptions(): void
    {
        $kolo = $this->uzavreneKolo();

        $extract = function (string $url): string {
            $html = (string) $this->get($url)->assertOk()->content();
            preg_match('/<meta name="description" content="([^"]*)"/', $html, $m);

            return $m[1] ?? '';
        };

        $descriptions = [
            $extract('/'),
            $extract(route('hlaseni.index')),
            $extract(route('vysledkova_listina', ['kolo' => $kolo->id])),
            $extract(route('rocni_vysledky')),
            $extract(route('pribezne_vysledky')),
        ];

        foreach ($descriptions as $d) {
            $this->assertNotSame('', $d, 'Každá veřejná stránka má mít meta popis.');
        }

        $this->assertSame(
            count($descriptions),
            count(array_unique($descriptions)),
            'Veřejné stránky mají mít navzájem odlišné meta popisy.',
        );
    }

    public function test_layout_emits_website_and_organization_jsonld(): void
    {
        $html = (string) $this->get('/')->assertOk()->content();

        $this->assertStringContainsString('application/ld+json', $html);
        $this->assertStringContainsString('"@type":"WebSite"', $html);
        $this->assertStringContainsString('"@type":"Organization"', $html);
    }

    public function test_round_pages_emit_event_jsonld(): void
    {
        $kolo = $this->uzavreneKolo();

        $html = (string) $this->get(route('vysledkova_listina', ['kolo' => $kolo->id]))
            ->assertOk()->content();

        $this->assertStringContainsString('"@type":"Event"', $html);
        $this->assertStringContainsString('"name":"03/2026"', $html);
    }

    public function test_sitemap_lists_round_results_and_log_pages(): void
    {
        $kolo = $this->uzavreneKolo();
        $head = $this->importSample();
        $head->update(['round_id' => $kolo->id]);

        $body = (string) $this->get('/sitemap.xml')->assertOk()->content();

        $this->assertStringContainsString('<loc>'.route('vysledkova_listina', ['kolo' => $kolo->id]).'</loc>', $body);
        $this->assertStringContainsString('<loc>'.route('edi.vizualizace', $head->id).'</loc>', $body);
        $this->assertStringContainsString('<loc>'.route('edi.porovnani', $head->id).'</loc>', $body);
    }
}
