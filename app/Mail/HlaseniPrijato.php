<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\EdiEntry;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\SerializesModels;

/**
 * Potvrzení o přijetí hlášení – účastníkovi.
 */
#[Tries(3)]
#[Backoff(60, 300, 900)]
#[Timeout(30)]
class HlaseniPrijato extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly EdiEntry $hlaseni,
        public readonly string $koloNazev,
        public readonly string $kategorieNazev,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Přijetí hlášení VKV PA '.$this->koloNazev,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.hlaseni-prijato');
    }
}
