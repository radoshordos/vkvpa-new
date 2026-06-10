<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\VkvpaData;
use App\Models\VkvpaKategorie;
use App\Models\VkvpaKola;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Testy veřejného REST API /api/vysledky/*.
 */
class ApiVysledkyTest extends TestCase
{
    use RefreshDatabase;

    private function kolo(): VkvpaKola
    {
        return VkvpaKola::create([
            'nazev' => '2024 duben',
            'datum_konani' => '2024-04-20',
            'datum_uzaverky' => '2024-04-28 23:59:00',
            'aktivni' => false,
            'poznamka' => '',
        ]);
    }

    private function kategorie(): VkvpaKategorie
    {
        return VkvpaKategorie::create([
            'nazev' => '2m SSB',
            'zkratka' => 'SSB',
            'popis' => '',
            'dxid' => 1,
        ]);
    }

    // ── /api/kola ────────────────────────────────────────────────────────

    public function test_kola_returns_200_with_data_key(): void
    {
        $this->kolo();

        $this->getJson('/api/kola')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'nazev', 'datum_konani', 'aktivni']]]);
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
        $kolo = $this->kolo();
        $kat = $this->kategorie();
        VkvpaData::create([
            'id_kola' => $kolo->id, 'id_kategorie' => $kat->id,
            'znacka' => 'OK1XY', 'locator' => 'JN79FX', 'schvaleno' => true,
            'body' => 500, 'pocet' => 10, 'nasobice' => 5, 'bodu_za_qso' => 100,
            'edihead_id' => 1, 'qrp' => false, 'poradi' => 1,
        ]);

        $this->getJson("/api/vysledky/{$kolo->id}")
            ->assertOk()
            ->assertJsonStructure([
                'kolo' => ['id', 'nazev', 'datum_konani'],
                'data' => [['poradi', 'znacka', 'body', 'pocet', 'nasobice', 'kategorie_id', 'edi']],
            ])
            ->assertJsonPath('data.0.znacka', 'OK1XY')
            ->assertJsonPath('data.0.body', 500);
    }

    public function test_vysledky_kolo_only_returns_approved(): void
    {
        $kolo = $this->kolo();
        $kat = $this->kategorie();

        VkvpaData::create([
            'id_kola' => $kolo->id, 'id_kategorie' => $kat->id,
            'znacka' => 'OK1SCHVALENO', 'schvaleno' => true,
            'body' => 100, 'pocet' => 5, 'nasobice' => 2, 'bodu_za_qso' => 50,
        ]);
        VkvpaData::create([
            'id_kola' => $kolo->id, 'id_kategorie' => $kat->id,
            'znacka' => 'OK1CEKA', 'schvaleno' => false,
            'body' => 200, 'pocet' => 8, 'nasobice' => 4, 'bodu_za_qso' => 80,
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
