<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\EdiImported;
use App\Mail\HlaseniPrijato;
use App\Mail\HlaseniProVyhodnocovatele;
use App\Models\LoginToken;
use App\Models\User;
use App\Support\VkvpaSettings;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Support\Facades\Mail;

/**
 * Po importu EDI deníku odešle potvrzovací e-mail účastníkovi
 * a oznámení vyhodnocovateli s odkazem pro převzetí záznamu.
 */
#[Tries(3)]
#[Backoff(60, 300, 900)]
#[Timeout(30)]
final class SendEdiMailsListener implements ShouldQueue
{
    public function handle(EdiImported $event): void
    {
        if (! VkvpaSettings::mailEnabled()) {
            return;
        }

        $data = $event->data;
        $data->loadMissing(['round', 'category']);

        $koloNazev = $data->round->name ?? '';
        $kategorieNazev = $data->category->name ?? '';

        if (filter_var($data->email, FILTER_VALIDATE_EMAIL) !== false) {
            Mail::to($data->email)->queue(new HlaseniPrijato($data, $koloNazev, $kategorieNazev));
        }

        $contactMail = VkvpaSettings::contactMail();
        if (filter_var($contactMail, FILTER_VALIDATE_EMAIL) !== false) {
            // Token svážeme s administrátorem (vyhodnocovatelem), takže přihlášení
            // přes magic-link vede ke konkrétní identitě, ne k „prvnímu adminovi".
            // LoginToken::issue uloží verifier jako argon2id hash a vrátí plaintext.
            $admin = User::query()->where('is_admin', true)->first();
            $token = LoginToken::issue($admin?->id);

            Mail::to($contactMail)->queue(
                new HlaseniProVyhodnocovatele($data, $koloNazev, $kategorieNazev, $token),
            );
        }
    }
}
