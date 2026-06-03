<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\VkvpaData;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Potvrzení o přijetí hlášení – účastníkovi.
 */
class HlaseniPrijato extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly VkvpaData $hlaseni,
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
