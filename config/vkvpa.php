<?php

declare(strict_types=1);

/**
 * Konfigurace aplikace VKV PA (Fáze 8).
 * Kontaktní e-mail vyhodnocovatele nahrazuje file-based mail.inc (bod S7)
 * i ereg validaci z head.php – hodnota je nyní v .env / DB (VkvpaConfig).
 */
return [
    'contact_mail' => env('CONTACT_MAIL', 'ok1vum@hamradio.cz'),
    'contact_name' => env('CONTACT_NAME', 'Míla OK1VUM'),

    // Platnost „převzít záznam" odkazu (login kód), shodně s legací.
    'token_ttl_days' => 5,
];
