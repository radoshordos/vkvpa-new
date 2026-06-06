<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Prispevek;
use App\Models\VkvpaData;
use App\Models\VkvpaKategorie;
use App\Models\VkvpaKola;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bezpečnostní testy: XSS escaping, security headers, přítomnost CSRF tokenů ve formulářích.
 */
class SecurityTest extends TestCase
{
    use RefreshDatabase;

    private function kolo(): VkvpaKola
    {
        return VkvpaKola::create([
            'datum_konani' => now()->subDay(),
            'datum_uzaverky' => now()->addDays(5),
            'nazev' => 'Testovací kolo',
            'aktivni' => true,
            'poznamka' => '',
        ]);
    }

    // ------------------------------------------------------------------
    // XSS – user-submitted content musí být v HTML escapováno

    public function test_xss_in_diskuse_text_is_escaped(): void
    {
        $kolo = $this->kolo();

        Prispevek::create([
            'kolo_id' => $kolo->id,
            'znacka' => 'OK1XSS',
            'text' => '<script>alert("xss")</script>',
            'ip' => '127.0.0.1',
        ]);

        $this->get(route('diskuse.show', $kolo->id))
            ->assertOk()
            ->assertDontSee('<script>alert("xss")</script>', false)
            ->assertSee('&lt;script&gt;', false);
    }

    public function test_xss_in_diskuse_callsign_is_escaped(): void
    {
        $kolo = $this->kolo();

        Prispevek::create([
            'kolo_id' => $kolo->id,
            'znacka' => '<img src=x onerror=alert(1)>',
            'text' => 'Normální text',
            'ip' => '127.0.0.1',
        ]);

        $this->get(route('diskuse.show', $kolo->id))
            ->assertOk()
            ->assertDontSee('<img src=x onerror=alert(1)>', false);
    }

    public function test_xss_in_vysledky_jmeno_is_escaped(): void
    {
        $kolo = $this->kolo();
        $kat = VkvpaKategorie::create(['nazev' => '144 MHz', 'popis' => '', 'zkratka' => 'A', 'dxid' => 0]);

        VkvpaData::create([
            'id_kola' => $kolo->id,
            'id_kategorie' => $kat->id,
            'znacka' => 'OK1TST',
            'locator' => 'JN99AJ',
            'mail' => 'test@example.com',
            'jmeno' => '<script>alert(1)</script>',
            'pocet' => 0,
            'nasobice' => 0,
            'body' => 0,
            'schvaleno' => true,
        ]);

        $this->get(route('vysledkova_listina', ['kolo' => $kolo->id]))
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_xss_in_vysledky_soapbox_is_escaped(): void
    {
        $kolo = $this->kolo();
        $kat = VkvpaKategorie::create(['nazev' => '144 MHz', 'popis' => '', 'zkratka' => 'A', 'dxid' => 0]);

        VkvpaData::create([
            'id_kola' => $kolo->id,
            'id_kategorie' => $kat->id,
            'znacka' => 'OK1TST',
            'locator' => 'JN99AJ',
            'mail' => 'test@example.com',
            'soapbox' => '"><script>alert(1)</script>',
            'pocet' => 0,
            'nasobice' => 0,
            'body' => 0,
            'schvaleno' => true,
        ]);

        $this->get(route('vysledkova_listina', ['kolo' => $kolo->id]))
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_xss_in_search_query_is_escaped(): void
    {
        $xss = '<script>alert("search")</script>';

        $this->get(route('vysledkova_listina', ['hledat' => $xss]))
            ->assertOk()
            ->assertDontSee($xss, false);
    }

    // ------------------------------------------------------------------
    // Security headers – SecurityHeaders middleware musí každou odpověď ozdobit

    public function test_security_headers_are_present_on_public_page(): void
    {
        $this->get('/')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
    }

    public function test_security_headers_are_present_on_login_page(): void
    {
        $this->get('/login')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    }

    // ------------------------------------------------------------------
    // CSRF – formuláře musí obsahovat _token hidden input

    public function test_login_form_contains_csrf_token(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('name="_token"', false);
    }

    public function test_hlaseni_form_contains_csrf_token(): void
    {
        $this->kolo(); // aktivní kolo zpřístupní formulář hlášení

        $this->get('/')
            ->assertOk()
            ->assertSee('name="_token"', false);
    }

    public function test_diskuse_form_contains_csrf_token(): void
    {
        $kolo = $this->kolo();

        $this->get(route('diskuse.show', $kolo->id))
            ->assertOk()
            ->assertSee('name="_token"', false);
    }
}
