<?php

declare(strict_types=1);

/**
 * Konfigurace aplikace VKV PA.
 */
return [
    'contact_mail' => env('CONTACT_MAIL', 'ok1vum@hamradio.cz'),
    'contact_name' => env('CONTACT_NAME', 'Míla OK1VUM'),

    'token_ttl_days' => 5,

    // Maximální velikost nahrávaného EDI souboru v kilobajtech.
    'edi_max_size_kb' => (int) env('EDI_MAX_SIZE_KB', 500),

    // Od tohoto kola se hlášení bez EDI do ročního součtu nezapočítávají.
    'non_edi_nullify_from_kolo' => 91,

    // Závodní časové okno (UTC, formát HHMM). QSO mimo se nezapočítávají.
    'contest_window' => [
        'from' => '0800',
        'to' => '1100',
    ],
];
