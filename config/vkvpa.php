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

    // Hromadný import (ZIP): max. velikost archivu v kB a max. počet zpracovaných souborů.
    'import_max_size_kb' => (int) env('IMPORT_MAX_SIZE_KB', 20480),
    'import_max_files' => (int) env('IMPORT_MAX_FILES', 200),

    // Maximální počet řádků výsledkové listiny jednoho kola (ochrana před přetížením paměti).
    'listina_max_rows' => (int) env('LISTINA_MAX_ROWS', 1000),

    // Od tohoto kola se hlášení bez EDI do ročního součtu nezapočítávají.
    'non_edi_nullify_from_kolo' => 91,

    // Cache ročních výsledků (Cache::flexible): doba „čerstvosti" a krajní doba
    // platnosti staré hodnoty (stale-while-revalidate), v sekundách.
    'yearly_cache_fresh' => (int) env('YEARLY_CACHE_FRESH', 300),
    'yearly_cache_stale' => (int) env('YEARLY_CACHE_STALE', 1800),

    // Prefixy tuzemských stanic (určuje DX vs. domácí kategorie).
    'domestic_prefixes' => ['OK', 'OL'],

    // Závodní časové okno (UTC, formát HHMM). QSO mimo se nezapočítávají.
    'contest_window' => [
        'from' => '0800',
        'to' => '1100',
    ],
];
