<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\EdiImported;
use App\Mail\HlaseniPrijato;
use App\Mail\HlaseniProVyhodnocovatele;
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

        if ($data->mail !== '') {
            Mail::to($data->mail)->queue(new HlaseniPrijato($data, $koloNazev, $kategorieNazev));
        }

        $contactMail = VkvpaSettings::contactMail();
        if ($contactMail !== '') {
            $kod = Str::password(32, letters: true, numbers: true, symbols: false);
            VkvpaPrihlaseni::create(['kod' => hash('sha256', $kod), 'time' => now()]);

            Mail::to($contactMail)->queue(
                new HlaseniProVyhodnocovatele($data, $koloNazev, $kategorieNazev, $kod),
            );
        }
    }
}
