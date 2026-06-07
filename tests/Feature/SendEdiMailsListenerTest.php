<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Events\EdiImported;
use App\Listeners\SendEdiMailsListener;
use App\Mail\HlaseniPrijato;
use App\Mail\HlaseniProVyhodnocovatele;
use App\Models\VkvpaData;
use App\Models\VkvpaKategorie;
use App\Models\VkvpaKola;
use App\Models\VkvpaPrihlaseni;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Testy pro SendEdiMailsListener: kdy se posílají maily závodníkovi a vyhodnocovateli.
 */
class SendEdiMailsListenerTest extends TestCase
{
    use RefreshDatabase;

    private VkvpaData $data;

    private SendEdiMailsListener $listener;

    protected function setUp(): void
    {
        parent::setUp();

        $kolo = VkvpaKola::create([
            'datum_konani' => now()->subDay(),
            'datum_uzaverky' => now()->addDays(5),
            'nazev' => 'Testovací kolo',
            'aktivni' => true,
            'poznamka' => '',
        ]);
        $kat = VkvpaKategorie::create(['nazev' => '144 MHz', 'popis' => '', 'zkratka' => 'A', 'dxid' => 0]);

        $this->data = VkvpaData::create([
            'id_kola' => $kolo->id,
            'id_kategorie' => $kat->id,
            'znacka' => 'OK2KJT',
            'locator' => 'JN99AJ',
            'mail' => 'zavodnik@example.com',
            'pocet' => 10,
            'nasobice' => 5,
            'body' => 50,
            'schvaleno' => false,
        ]);

        $this->listener = new SendEdiMailsListener();

        Config::set('vkvpa.contact_mail', '');
    }

    private function dispatch(): void
    {
        $this->listener->handle(new EdiImported($this->data));
    }

    // ── Závodník ──────────────────────────────────────────────────────────────

    public function test_contestant_mail_queued_when_valid_email(): void
    {
        Mail::fake();

        $this->dispatch();

        Mail::assertQueued(HlaseniPrijato::class, fn (HlaseniPrijato $m) => $m->hasTo('zavodnik@example.com'));
    }

    public function test_contestant_mail_not_queued_when_email_empty(): void
    {
        Mail::fake();

        $this->data->mail = '';
        $this->dispatch();

        Mail::assertNotQueued(HlaseniPrijato::class);
    }

    public function test_contestant_mail_not_queued_when_email_invalid(): void
    {
        Mail::fake();

        $this->data->mail = 'tohle-neni-email';
        $this->dispatch();

        Mail::assertNotQueued(HlaseniPrijato::class);
    }

    public function test_contestant_mail_not_queued_when_email_missing_domain(): void
    {
        Mail::fake();

        $this->data->mail = 'ok2kjt@';
        $this->dispatch();

        Mail::assertNotQueued(HlaseniPrijato::class);
    }

    public function test_contestant_mail_subject_contains_round_name(): void
    {
        Mail::fake();

        $this->dispatch();

        Mail::assertQueued(
            HlaseniPrijato::class,
            fn (HlaseniPrijato $m) => str_contains($m->envelope()->subject, 'Testovací kolo'),
        );
    }

    // ── Vyhodnocovatel ───────────────────────────────────────────────────────

    public function test_judge_mail_queued_when_contact_mail_configured(): void
    {
        Mail::fake();

        Config::set('vkvpa.contact_mail', 'vyhodnocovatel@example.com');
        $this->data->mail = '';
        $this->dispatch();

        Mail::assertQueued(
            HlaseniProVyhodnocovatele::class,
            fn (HlaseniProVyhodnocovatele $m) => $m->hasTo('vyhodnocovatel@example.com'),
        );
    }

    public function test_judge_mail_not_queued_when_contact_mail_empty(): void
    {
        Mail::fake();

        $this->data->mail = '';
        $this->dispatch();

        Mail::assertNotQueued(HlaseniProVyhodnocovatele::class);
    }

    public function test_judge_mail_not_queued_when_contact_mail_invalid(): void
    {
        Mail::fake();

        Config::set('vkvpa.contact_mail', 'not-an-email');
        $this->data->mail = '';
        $this->dispatch();

        Mail::assertNotQueued(HlaseniProVyhodnocovatele::class);
    }

    public function test_judge_mail_subject_contains_call_sign_and_round(): void
    {
        Mail::fake();

        Config::set('vkvpa.contact_mail', 'vyhodnocovatel@example.com');
        $this->data->mail = '';
        $this->dispatch();

        Mail::assertQueued(HlaseniProVyhodnocovatele::class, function (HlaseniProVyhodnocovatele $m) {
            $subject = $m->envelope()->subject;

            return str_contains($subject, 'OK2KJT') && str_contains($subject, 'Testovací kolo');
        });
    }

    public function test_judge_mail_prevzit_url_contains_data_id(): void
    {
        Mail::fake();

        Config::set('vkvpa.contact_mail', 'vyhodnocovatel@example.com');
        $this->data->mail = '';
        $this->dispatch();

        Mail::assertQueued(
            HlaseniProVyhodnocovatele::class,
            fn (HlaseniProVyhodnocovatele $m) => str_contains($m->prevzitUrl, (string) $this->data->id),
        );
    }

    // ── Token v DB ────────────────────────────────────────────────────────────

    public function test_token_stored_in_db_when_judge_mail_sent(): void
    {
        Mail::fake();

        Config::set('vkvpa.contact_mail', 'vyhodnocovatel@example.com');
        $this->dispatch();

        $this->assertSame(1, VkvpaPrihlaseni::count());
    }

    public function test_token_is_hashed_sha256_in_db(): void
    {
        Config::set('vkvpa.contact_mail', 'vyhodnocovatel@example.com');
        $this->data->mail = '';

        $mailable = null;
        Mail::fake();
        $this->dispatch();

        Mail::assertQueued(HlaseniProVyhodnocovatele::class, function (HlaseniProVyhodnocovatele $m) use (&$mailable) {
            $mailable = $m;

            return true;
        });

        $this->assertNotNull($mailable);
        $stored = VkvpaPrihlaseni::first();
        $this->assertNotNull($stored);
        $this->assertSame(hash('sha256', $mailable->kod), $stored->kod);
    }

    public function test_no_token_stored_when_contact_mail_empty(): void
    {
        Mail::fake();

        $this->dispatch();

        $this->assertSame(0, VkvpaPrihlaseni::count());
    }

    // ── Kombinace ────────────────────────────────────────────────────────────

    public function test_both_mails_sent_when_both_addresses_valid(): void
    {
        Mail::fake();

        Config::set('vkvpa.contact_mail', 'vyhodnocovatel@example.com');
        $this->dispatch();

        Mail::assertQueued(HlaseniPrijato::class);
        Mail::assertQueued(HlaseniProVyhodnocovatele::class);
        $this->assertSame(1, VkvpaPrihlaseni::count());
    }

    public function test_no_mail_queued_when_both_addresses_absent(): void
    {
        Mail::fake();

        $this->data->mail = '';
        $this->dispatch();

        Mail::assertNothingQueued();
        $this->assertSame(0, VkvpaPrihlaseni::count());
    }
}
