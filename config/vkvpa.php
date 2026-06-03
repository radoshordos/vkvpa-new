<?php

declare(strict_types=1);

/**
 * Konfigurace aplikace VKV PA.
 */
return [
    'contact_mail' => env('CONTACT_MAIL', 'ok1vum@hamradio.cz'),
    'contact_name' => env('CONTACT_NAME', 'Míla OK1VUM'),

    'token_ttl_days' => 5,
];
