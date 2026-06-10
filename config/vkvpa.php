<?php

declare(strict_types=1);

/**
 * Konfigurace aplikace VKV PA.
 */
return [
    'contact_mail' => env('CONTACT_MAIL', 'ok1vum@hamradio.cz'),
    'contact_name' => env('CONTACT_NAME', 'Míla OK1VUM'),

    // Texty, které smí MailImageController vykreslit jako PNG (adresy z patičky).
    // Jiný vstup vrací 404 – endpoint nemá generovat obrázky s libovolným textem.
    'mail_image_allowlist' => [
        env('CONTACT_MAIL', 'ok1vum@hamradio.cz'),
        'ok1mab@hamradio.cz',
    ],

    // Admin účet pro seeder – čtení přes config() je bezpečné i při kešování.
    'admin_user' => env('ADMIN_USER'),
    'admin_email' => env('ADMIN_EMAIL', 'admin@example.com'),
    'admin_pass' => env('ADMIN_PASS'),

    'token_ttl_days' => 5,

    // Maximální velikost nahrávaného EDI souboru v kilobajtech.
    'edi_max_size_kb' => (int) env('EDI_MAX_SIZE_KB', 500),

    // Hromadný import (ZIP): max. velikost archivu v kB a max. počet zpracovaných souborů.
    'import_max_size_kb' => (int) env('IMPORT_MAX_SIZE_KB', 20480),
    'import_max_files' => (int) env('IMPORT_MAX_FILES', 200),

    // Maximální počet řádků výsledkové listiny jednoho kola (ochrana před přetížením paměti).
    'listina_max_rows' => (int) env('LISTINA_MAX_ROWS', 1000),

    // Od tohoto kola se hlášení bez EDI do ročního součtu nezapočítávají.
    'non_edi_nullify_from_kolo' => 91,

    // Neschválený záznam se počítá jako „čerstvý" (drží kolo v aktivním stavu
    // a blokuje veřejný přístup k EDI souborům) jen po tuto dobu od podání.
    // Starší zapomenuté záznamy už aktivitu nedrží.
    'fresh_unapproved_days' => 28,

    // Cache ročních výsledků (Cache::flexible): doba „čerstvosti" a krajní doba
    // platnosti staré hodnoty (stale-while-revalidate), v sekundách.
    'yearly_cache_fresh' => (int) env('YEARLY_CACHE_FRESH', 300),
    'yearly_cache_stale' => (int) env('YEARLY_CACHE_STALE', 1800),

    // Cache mapové vrstvy „všechny stanice z kola" (sekundy). Vrstva se vydává
    // až po uzávěrce kola, od té chvíle se data prakticky nemění – stačí TTL.
    'round_stations_cache_ttl' => (int) env('ROUND_STATIONS_CACHE_TTL', 3600),

    // Prefixy tuzemských stanic (určuje DX vs. domácí kategorie).
    'domestic_prefixes' => ['OK', 'OL'],

    // Závodní časové okno (UTC, formát HHMM). QSO mimo se nezapočítávají.
    'contest_window' => [
        'from' => '0800',
        'to' => '1100',
    ],
];
