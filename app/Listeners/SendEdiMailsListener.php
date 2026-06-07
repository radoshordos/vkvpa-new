<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\EdiImported;
use App\Mail\HlaseniPrijato;
use App\Mail\HlaseniProVyhodnocovatele;
use App\Models\User;
use App\Models\VkvpaPrihlaseni;
use App\Support\VkvpaSettings;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Po importu EDI deníku odešle potvrzovací e-mail účastníkovi
 * a oznámení vyhodnocovateli s odkazem pro převzetí záznamu.
 */
final class SendEdiMailsListener implements ShouldQueue
{
    public function handle(EdiImported $event): void
    {
        $data = $event->data;
        $data->loadMissing(['kolo', 'kategorie']);

        $koloNazev = $data->kolo->nazev ?? '';
        $kategorieNazev = $data->kategorie->nazev ?? '';

        if (filter_var($data->mail, FILTER_VALIDATE_EMAIL) !== false) {
            Mail::to($data->mail)->queue(new HlaseniPrijato($data, $koloNazev, $kategorieNazev));
        }

        $contactMail = VkvpaSettings::contactMail();
        if (filter_var($contactMail, FILTER_VALIDATE_EMAIL) !== false) {
            $kod = Str::password(32, letters: true, numbers: true, symbols: false);
            // Token svážeme s administrátorem (vyhodnocovatelem), takže přihlášení
            // přes magic-link vede ke konkrétní identitě, ne k „prvnímu adminovi".
            $adminId = User::query()->where('is_admin', true)->value('id');
            VkvpaPrihlaseni::create([
                'kod' => hash('sha256', $kod),
                'time' => now(),
                'user_id' => $adminId,
            ]);

            Mail::to($contactMail)->queue(
                new HlaseniProVyhodnocovatele($data, $koloNazev, $kategorieNazev, $kod),
            );
        }
    }
}
