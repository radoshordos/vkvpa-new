<?php

declare(strict_types=1);

/**
 * Struktura hlavního menu.
 *
 * 'menu' je seznam tematických skupin; nadpisy skupin se zobrazují jen
 * administrátorům (ne-admin vidí pouze veřejné položky jako plochý seznam).
 * Položky s 'admin' => true vidí jen administrátor.
 *
 * Položky s 'key' jsou interní (pojmenovaná routa, podporují aktivní stav).
 * Položky s 'url' jsou externí.
 * 'trans' je klíč do překladových souborů (zobrazí se v aktuálním jazyce).
 */

return [

    'menu' => [
        [
            'heading' => 'nav.group_logs',
            'items' => [
                ['key' => 'hlaseni.index',    'trans' => 'nav.log_import'],
                ['key' => 'vizualizer.create', 'trans' => 'nav.vizualizer'],
                ['key' => 'deniky.index',     'trans' => 'admin.nav_logs',      'admin' => true],
                ['key' => 'export.index',     'trans' => 'admin.nav_export',    'admin' => true],
                ['key' => 'uzivatele.index',  'trans' => 'admin.nav_users',     'admin' => true],
                ['key' => 'importy.index',    'trans' => 'admin.nav_imports',   'admin' => true],
                ['key' => 'edi.debug.create', 'trans' => 'admin.nav_edi_debug', 'admin' => true],
                ['key' => 'heslo.edit',       'trans' => 'admin.nav_password',  'admin' => true],
            ],
        ],
        [
            'heading' => 'nav.group_results',
            'items' => [
                ['key' => 'pribezne_vysledky',  'trans' => 'nav.interim_results'],
                ['key' => 'vysledkova_listina', 'trans' => 'nav.results'],
                ['key' => 'rocni_vysledky',     'trans' => 'nav.year_results'],
                ['key' => 'statistiky.index',   'trans' => 'nav.statistics'],
                ['key' => 'admin.dashboard',    'trans' => 'admin.nav_dashboard', 'admin' => true],
            ],
        ],
        [
            'heading' => 'nav.group_contest',
            'items' => [
                ['key' => 'kola.admin.index', 'trans' => 'admin.nav_rounds',     'admin' => true],
                ['key' => 'kategorie.index',  'trans' => 'admin.nav_categories', 'admin' => true],
                ['key' => 'diskuse.index',    'trans' => 'nav.discussion'],
            ],
        ],
    ],

    'footer' => [
        ['url' => '/rules/pa_vkv_2023–2022_12_23_{locale}.pdf',                      'trans' => 'nav.rules_cz',       'target' => '_blank'],
        ['url' => 'https://ceskyradioklub.cz/zavody/vkv-zavody/content/vkv-provozni-aktiv', 'trans' => 'nav.history', 'target' => '_blank'],
        ['url' => 'https://ceskyradioklub.cz/',                                      'trans' => 'nav.official_web',    'target' => '_blank'],
    ],

];
