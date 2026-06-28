<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\EdiCategory;
use App\Models\EdiEntry;
use App\Models\EdiRound;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Testy veřejného REST API /api/vysledky/*.
 */
class ApiVysledkyTest extends TestCase
{
    use RefreshDatabase;

    private function round(): EdiRound
    {
        return EdiRound::create([
            'name' => '2024 duben',
            'starts_at' => '2024-04-20 08:00:00',
            'closes_at' => '2024-04-28 23:59:00',
            'note' => '',
        ]);
    }

    private function category(): EdiCategory
    {
        return EdiCategory::create([
            'name' => '2m SSB',
            'band' => 'SSB',
            'section' => 'SO',
            'variant' => 'domestic',
        ]);
    }

    // ── /api/kola ────────────────────────────────────────────────────────

    public function test_kola_returns_200_with_data_key(): void
    {
        $this->round();

        $this->getJson('/api/kola')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'nazev', 'starts_at', 'stav']]]);
    }

    public function test_kola_returns_empty_list_when_no_rounds(): void
    {
        $this->getJson('/api/kola')
            ->assertOk()
            ->assertJson(['data' => []]);
    }

    public function test_api_routes_have_rate_limit_headers(): void
    {
        // throttle:api (60/min) přidá hlavičky o limitu – ověřuje, že limiter běží.
        $this->getJson('/api/kola')
            ->assertOk()
            ->assertHeader('X-RateLimit-Limit', 60);
    }

    // ── /api/vysledky/{kolo} ─────────────────────────────────────────────

    public function test_vysledky_kolo_returns_kolo_and_data(): void
    {
        $kolo = $this->round();
        $kat = $this->category();
        EdiEntry::create([
            'round_id' => $kolo->id, 'category_id' => $kat->id,
            'callsign' => 'OK1XY', 'locator' => 'JN79FX', 'approved' => true,
            'points' => 500, 'qso_count' => 10, 'multiplier' => 5, 'qso_points' => 100,
            'edi_head_id' => 1, 'qrp' => false, 'rank' => 1,
        ]);

        $this->getJson("/api/vysledky/{$kolo->id}")
            ->assertOk()
            ->assertJsonStructure([
                'kolo' => ['id', 'nazev', 'starts_at'],
                'data' => [['poradi', 'znacka', 'body', 'pocet', 'multiplier', 'kategorie_id', 'edi']],
            ])
            ->assertJsonPath('data.0.znacka', 'OK1XY')
            ->assertJsonPath('data.0.body', 500);
    }

    public function test_vysledky_kolo_only_returns_approved(): void
    {
        $kolo = $this->round();
        $kat = $this->category();

        EdiEntry::create([
            'round_id' => $kolo->id, 'category_id' => $kat->id,
            'callsign' => 'OK1SCHVALENO', 'approved' => true,
            'points' => 100, 'qso_count' => 5, 'multiplier' => 2, 'qso_points' => 50,
        ]);
        EdiEntry::create([
            'round_id' => $kolo->id, 'category_id' => $kat->id,
            'callsign' => 'OK1CEKA', 'approved' => false,
            'points' => 200, 'qso_count' => 8, 'multiplier' => 4, 'qso_points' => 80,
        ]);

        $this->getJson("/api/vysledky/{$kolo->id}")
            ->assertOk()
            ->assertJsonFragment(['znacka' => 'OK1SCHVALENO'])
            ->assertJsonMissing(['znacka' => 'OK1CEKA']);
    }

    public function test_vysledky_kolo_returns_404_for_nonexistent(): void
    {
        $this->getJson('/api/vysledky/999')
            ->assertNotFound();
    }

    // ── /api/vysledky/rocni/{rok} ────────────────────────────────────────

    public function test_vysledky_rocni_returns_rok_and_data(): void
    {
        Cache::flush();

        $this->getJson('/api/vysledky/rocni/2024')
            ->assertOk()
            ->assertJsonStructure(['rok', 'data'])
            ->assertJsonPath('rok', 2024);
    }

    public function test_vysledky_rocni_rejects_invalid_rok(): void
    {
        $this->getJson('/api/vysledky/rocni/abc')
            ->assertNotFound();
    }

    // ── JSON Content-Type ─────────────────────────────────────────────────

    public function test_api_returns_json_content_type(): void
    {
        $this->getJson('/api/kola')
            ->assertHeader('Content-Type', 'application/json');
    }

    // ── /api/docs ─────────────────────────────────────────────────────────

    public function test_api_docs_returns_swagger_ui(): void
    {
        $this->get('/api/docs')
            ->assertOk()
            ->assertSee('swagger-ui');
    }

    public function test_api_docs_spec_returns_yaml(): void
    {
        $this->get('/api/docs/spec')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/yaml');
    }
}
