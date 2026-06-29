<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\EdiEntry;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Oznámení o přijetí hlášení – vyhodnocovateli.
 * Obsahuje „převzít záznam" odkaz s jednorázovým kódem, který po přihlášení
 * přesměruje na převzetí konkrétního hlášení.
 */
class HlaseniProVyhodnocovatele extends Mailable
{
    use Queueable;
    use SerializesModels;

    public string $prevzitUrl;

    public function __construct(
        public readonly EdiEntry $hlaseni,
        public readonly string $koloNazev,
        public readonly string $kategorieNazev,
        public readonly string $token,
    ) {
        $this->prevzitUrl = route('login.token', ['token' => $token, 'confirm' => $hlaseni->id]);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->hlaseni->callsign.' '.$this->koloNazev,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.hlaseni-vyhodnocovatel');
    }
}
