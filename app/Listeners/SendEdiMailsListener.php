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
        $data->loadMissing(['round', 'category']);

        $koloNazev = $data->round->name ?? '';
        $kategorieNazev = $data->category->name ?? '';

        if (filter_var($data->email, FILTER_VALIDATE_EMAIL) !== false) {
            Mail::to($data->email)->queue(new HlaseniPrijato($data, $koloNazev, $kategorieNazev));
        }

        $contactMail = VkvpaSettings::contactMail();
        if (filter_var($contactMail, FILTER_VALIDATE_EMAIL) !== false) {
            $token = Str::password(32, letters: true, numbers: true, symbols: false);
            // Token svážeme s administrátorem (vyhodnocovatelem), takže přihlášení
            // přes magic-link vede ke konkrétní identitě, ne k „prvnímu adminovi".
            $adminId = User::query()->where('is_admin', true)->value('id');
            LoginToken::create([
                'token' => hash('sha256', $token),
                'user_id' => $adminId,
            ]);

            Mail::to($contactMail)->queue(
                new HlaseniProVyhodnocovatele($data, $koloNazev, $kategorieNazev, $token),
            );
        }
    }
}
