<?php

declare(strict_types=1);

/**
 * Struktura hlavního menu.
 *
 * Položky s 'key' jsou interní (pojmenovaná routa, podporují aktivní stav).
 * Položky s 'url' jsou externí.
 * 'trans' je klíč do překladového souboru nav.php (zobrazí se v aktuálním jazyce).
 */

return [

    'public' => [
        ['key' => 'kola.index',         'trans' => 'nav.contest_periods'],
        ['key' => 'hlaseni.index',      'trans' => 'nav.log_import'],
        ['key' => 'pribezne_vysledky',  'trans' => 'nav.interim_results'],
        ['key' => 'vysledkova_listina', 'trans' => 'nav.results'],
        ['key' => 'rocni_vysledky',     'trans' => 'nav.year_results'],
        ['key' => 'diskuse.index',      'trans' => 'nav.discussion'],
        ['url' => 'https://vkvpa.hamradio.cz/rules/PA_VKV_2023–2022_12_23_cz.pdf', 'trans' => 'nav.rules_cz', 'target' => '_blank'],
        ['url' => 'http://www.ok1kpa.com/pa-podminky.htm', 'trans' => 'nav.official_rules',   'target' => '_blank'],
        ['url' => 'http://www.ok1kpa.com/',                'trans' => 'nav.official_archive',  'target' => '_blank'],
        ['url' => 'http://www.crk.cz/',                    'trans' => 'nav.official_web',      'target' => '_blank'],
    ],

    'admin' => [
        ['key' => 'admin.dashboard',  'trans' => 'admin.nav_dashboard'],
        ['key' => 'kola.index',       'trans' => 'admin.nav_rounds'],
        ['key' => 'hlaseni.index',    'trans' => 'admin.nav_reports'],
        ['key' => 'deniky.index',     'trans' => 'admin.nav_logs'],
        ['key' => 'edi.debug.create', 'trans' => 'admin.nav_edi_debug'],
        ['key' => 'kategorie.index',  'trans' => 'admin.nav_categories'],
        ['key' => 'importy.index',    'trans' => 'admin.nav_imports'],
        ['key' => 'api.docs',         'trans' => 'admin.nav_api_docs'],
    ],

];
