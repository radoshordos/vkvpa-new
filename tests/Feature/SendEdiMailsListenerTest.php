<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Events\EdiImported;
use App\Listeners\SendEdiMailsListener;
use App\Mail\HlaseniPrijato;
use App\Mail\HlaseniProVyhodnocovatele;
use App\Models\EdiCategory;
use App\Models\EdiEntry;
use App\Models\EdiRound;
use App\Models\LoginToken;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\SendQueuedMailable;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Testy pro SendEdiMailsListener: kdy se posílají maily závodníkovi a vyhodnocovateli.
 */
class SendEdiMailsListenerTest extends TestCase
{
    use RefreshDatabase;

    private EdiEntry $data;

    private SendEdiMailsListener $listener;

    protected function setUp(): void
    {
        parent::setUp();

        $kolo = EdiRound::create([
            'starts_at' => now()->subDay(),
            'closes_at' => now()->addDays(5),
            'name' => 'Testovací kolo',
            'note' => '',
        ]);
        $kat = EdiCategory::create(['name' => '144 MHz', 'section' => 'SO', 'variant' => 'domestic']);

        $this->data = EdiEntry::create([
            'round_id' => $kolo->id,
            'category_id' => $kat->id,
            'callsign' => 'OK2KJT',
            'locator' => 'JN99AJ',
            'email' => 'zavodnik@example.com',
            'qso_count' => 10,
            'multiplier' => 5,
            'points' => 50,
            'approved' => false,
        ]);

        $this->listener = new SendEdiMailsListener;

        // Maily jsou deterministicky zapnuté – ambientní .env má MAIL_ENABLED=false
        // (CI kopíruje .env.example), jinak by se listener ukončil a nic neodeslal.
        // Test na vypnutý mail si hodnotu přepíše na false sám.
        Config::set('vkvpa.mail_enabled', true);
        Config::set('vkvpa.contact_mail', '');
    }

    private function dispatch(): void
    {
        $this->listener->handle(new EdiImported($this->data));
    }

    // ── Závodník ──────────────────────────────────────────────────────────────

    public function test_imported_event_listener_is_routed_to_mail_queue_with_retry_options(): void
    {
        Queue::fake();

        EdiImported::dispatch($this->data);

        Queue::assertPushedOn('mail', CallQueuedListener::class, fn (CallQueuedListener $job): bool => $job->class === SendEdiMailsListener::class
            && $job->tries === 3
            && $job->timeout === 30
            && $this->listenerBackoff($job) === [60, 300, 900]);
    }

    public function test_queued_mailables_are_routed_to_mail_queue_with_retry_options(): void
    {
        Queue::fake();

        Config::set('vkvpa.contact_mail', 'vyhodnocovatel@example.com');
        $this->dispatch();

        Queue::assertPushedOn('mail', SendQueuedMailable::class, fn (SendQueuedMailable $job): bool => $job->mailable instanceof HlaseniPrijato
            && $job->tries === 3
            && $job->timeout === 30
            && $job->backoff() === [60, 300, 900]);

        Queue::assertPushedOn('mail', SendQueuedMailable::class, fn (SendQueuedMailable $job): bool => $job->mailable instanceof HlaseniProVyhodnocovatele
            && $job->tries === 3
            && $job->timeout === 30
            && $job->backoff() === [60, 300, 900]);
    }

    private function listenerBackoff(CallQueuedListener $job): mixed
    {
        return (new \ReflectionProperty($job, 'backoff'))->getValue($job);
    }

    public function test_no_mail_or_token_when_mail_sending_is_disabled(): void
    {
        Mail::fake();

        Config::set('vkvpa.mail_enabled', false);
        Config::set('vkvpa.contact_mail', 'vyhodnocovatel@example.com');

        $this->dispatch();

        Mail::assertNothingQueued();
        $this->assertSame(0, LoginToken::count());
    }

    public function test_contestant_mail_queued_when_valid_email(): void
    {
        Mail::fake();

        $this->dispatch();

        Mail::assertQueued(HlaseniPrijato::class, fn (HlaseniPrijato $m) => $m->hasTo('zavodnik@example.com'));
    }

    public function test_contestant_mail_not_queued_when_email_empty(): void
    {
        Mail::fake();

        $this->data->email = '';
        $this->dispatch();

        Mail::assertNotQueued(HlaseniPrijato::class);
    }

    public function test_contestant_mail_not_queued_when_email_invalid(): void
    {
        Mail::fake();

        $this->data->email = 'tohle-neni-email';
        $this->dispatch();

        Mail::assertNotQueued(HlaseniPrijato::class);
    }

    public function test_contestant_mail_not_queued_when_email_missing_domain(): void
    {
        Mail::fake();

        $this->data->email = 'ok2kjt@';
        $this->dispatch();

        Mail::assertNotQueued(HlaseniPrijato::class);
    }

    public function test_contestant_mail_subject_contains_round_name(): void
    {
        Mail::fake();

        $this->dispatch();

        Mail::assertQueued(
            HlaseniPrijato::class,
            fn (HlaseniPrijato $m) => str_contains((string) $m->envelope()->subject, 'Testovací kolo'),
        );
    }

    // ── Vyhodnocovatel ───────────────────────────────────────────────────────

    public function test_judge_mail_queued_when_contact_mail_configured(): void
    {
        Mail::fake();

        Config::set('vkvpa.contact_mail', 'vyhodnocovatel@example.com');
        $this->data->email = '';
        $this->dispatch();

        Mail::assertQueued(
            HlaseniProVyhodnocovatele::class,
            fn (HlaseniProVyhodnocovatele $m) => $m->hasTo('vyhodnocovatel@example.com'),
        );
    }

    public function test_judge_mail_not_queued_when_contact_mail_empty(): void
    {
        Mail::fake();

        $this->data->email = '';
        $this->dispatch();

        Mail::assertNotQueued(HlaseniProVyhodnocovatele::class);
    }

    public function test_judge_mail_not_queued_when_contact_mail_invalid(): void
    {
        Mail::fake();

        Config::set('vkvpa.contact_mail', 'not-an-email');
        $this->data->email = '';
        $this->dispatch();

        Mail::assertNotQueued(HlaseniProVyhodnocovatele::class);
    }

    public function test_judge_mail_subject_contains_call_sign_and_round(): void
    {
        Mail::fake();

        Config::set('vkvpa.contact_mail', 'vyhodnocovatel@example.com');
        $this->data->email = '';
        $this->dispatch();

        Mail::assertQueued(HlaseniProVyhodnocovatele::class, function (HlaseniProVyhodnocovatele $m) {
            $subject = $m->envelope()->subject;

            return str_contains((string) $subject, 'OK2KJT') && str_contains((string) $subject, 'Testovací kolo');
        });
    }

    public function test_judge_mail_prevzit_url_contains_data_id(): void
    {
        Mail::fake();

        Config::set('vkvpa.contact_mail', 'vyhodnocovatel@example.com');
        $this->data->email = '';
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

        $this->assertSame(1, LoginToken::count());
    }

    public function test_token_is_hashed_via_hash_driver_in_db(): void
    {
        Config::set('vkvpa.contact_mail', 'vyhodnocovatel@example.com');
        $this->data->email = '';

        $mailable = null;
        Mail::fake();
        $this->dispatch();

        Mail::assertQueued(HlaseniProVyhodnocovatele::class, function (HlaseniProVyhodnocovatele $m) use (&$mailable) {
            $mailable = $m;

            return true;
        });

        $this->assertNotNull($mailable);
        $stored = LoginToken::first();
        $this->assertNotNull($stored);

        // Plaintext token = selector + verifier; v DB je veřejný selector a
        // verifier hashovaný přes Hash fasádu (produkce argon2id, testy bcrypt –
        // viz phpunit.xml), tj. už ne deterministický SHA-2.
        $selector = substr($mailable->token, 0, LoginToken::SELECTOR_LENGTH);
        $verifier = substr($mailable->token, LoginToken::SELECTOR_LENGTH);

        $this->assertSame($selector, $stored->selector);
        $this->assertNotSame(hash('sha256', $verifier), $stored->token);
        $this->assertTrue(Hash::check($verifier, $stored->token));
    }

    public function test_no_token_stored_when_contact_mail_empty(): void
    {
        Mail::fake();

        $this->dispatch();

        $this->assertSame(0, LoginToken::count());
    }

    // ── Kombinace ────────────────────────────────────────────────────────────

    public function test_both_mails_sent_when_both_addresses_valid(): void
    {
        Mail::fake();

        Config::set('vkvpa.contact_mail', 'vyhodnocovatel@example.com');
        $this->dispatch();

        Mail::assertQueued(HlaseniPrijato::class);
        Mail::assertQueued(HlaseniProVyhodnocovatele::class);
        $this->assertSame(1, LoginToken::count());
    }

    public function test_no_mail_queued_when_both_addresses_absent(): void
    {
        Mail::fake();

        $this->data->email = '';
        $this->dispatch();

        Mail::assertNothingQueued();
        $this->assertSame(0, LoginToken::count());
    }
}
