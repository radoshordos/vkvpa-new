<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Prispevek;
use App\Models\VkvpaData;
use App\Models\VkvpaKategorie;
use App\Models\VkvpaKola;
use App\Support\VkvpaSettings;
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

        $this->get('/hlaseni')
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

    // ------------------------------------------------------------------
    // CSP – inline skripty přes nonce, ne 'unsafe-inline'

    public function test_csp_script_src_uses_nonce_instead_of_unsafe_inline(): void
    {
        $response = $this->get('/')->assertOk();

        $csp = (string) $response->headers->get('Content-Security-Policy');
        $scriptSrc = $this->cspDirective($csp, 'script-src');
        $this->assertStringNotContainsString("'unsafe-inline'", $scriptSrc);

        // Stejný nonce musí nést inline skripty stránky, jinak je prohlížeč zablokuje.
        $response->assertSee('nonce="'.$this->cspNonceOf($scriptSrc).'"', false);
    }

    public function test_csp_nonce_differs_per_request(): void
    {
        $first = (string) $this->get('/')->headers->get('Content-Security-Policy');
        $second = (string) $this->get('/')->headers->get('Content-Security-Policy');

        $this->assertNotSame(
            $this->cspNonceOf($this->cspDirective($first, 'script-src')),
            $this->cspNonceOf($this->cspDirective($second, 'script-src')),
        );
    }

    /** Hodnota dané direktivy z CSP hlavičky (selže-li parsování, selže test). */
    private function cspDirective(string $csp, string $name): string
    {
        preg_match('/'.preg_quote($name, '/').' ([^;]+)/', $csp, $m);
        $value = $m[1] ?? '';
        $this->assertNotSame('', $value, "CSP neobsahuje direktivu {$name}: {$csp}");

        return $value;
    }

    /** Nonce z hodnoty script-src direktivy (selže-li parsování, selže test). */
    private function cspNonceOf(string $scriptSrc): string
    {
        preg_match("/'nonce-([^']+)'/", $scriptSrc, $m);
        $nonce = $m[1] ?? '';
        $this->assertNotSame('', $nonce, "script-src neobsahuje nonce: {$scriptSrc}");

        return $nonce;
    }

    // ------------------------------------------------------------------
    // Obrázek e-mailu – jen adresy z allowlistu (patička)

    public function test_mail_image_renders_allowlisted_address(): void
    {
        $this->get(route('mail.image', ['text' => base64_encode(VkvpaSettings::contactMail())]))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
    }

    public function test_mail_image_rejects_arbitrary_text(): void
    {
        $this->get(route('mail.image', ['text' => base64_encode('volejte 900 123 456')]))
            ->assertNotFound();
    }

    // ------------------------------------------------------------------
    // Přepínač jazyka – návrat jen na interní cestu (žádný open redirect)

    public function test_lang_switch_does_not_redirect_to_foreign_host(): void
    {
        $this->withHeader('referer', 'https://evil.example/phishing')
            ->get(route('lang.switch', 'en'))
            ->assertRedirect('/phishing'); // jen cesta na vlastním hostu, ne evil.example

        $this->assertSame('en', session('locale'));
    }

    public function test_lang_switch_returns_to_internal_page(): void
    {
        $this->withHeader('referer', url('/vysledky'))
            ->get(route('lang.switch', 'cs'))
            ->assertRedirect('/vysledky');
    }
}
