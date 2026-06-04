<?php

declare(strict_types=1);

/**
 * Struktura hlavního menu.
 *
 * Položky s 'key' jsou interní (pojmenovaná routa, podporují aktivní stav).
 * Položky s 'url' jsou externí. 'label' může obsahovat statické HTML (<br>).
 */

return [

    'public' => [
        ['key' => 'edit_kola',          'label' => 'kola závodu <br> contest period'],
        ['key' => 'edit_hlaseni',       'label' => 'odeslat deník<br>log import'],
        ['key' => 'vysledkova_listina', 'label' => 'výsledková listina<br>results'],
        ['key' => 'rocni_vysledky',     'label' => 'roční výsledky<br>year results'],
        ['url' => 'https://vkvpa.hamradio.cz/rules/PA_VKV_2023–2022_12_23_en.pdf', 'label' => 'contest rules (pdf eng)'],
        ['url' => 'https://vkvpa.hamradio.cz/rules/PA_VKV_2023–2022_12_23_cz.pdf', 'label' => 'podmínky závodu(pdf cz)', 'target' => '_blank'],
    ],

    'admin' => [
        ['key' => 'edit_kola',          'label' => 'kola závodu'],
        ['key' => 'edit_hlaseni',       'label' => 'hlášení'],
        ['key' => 'edit_deniky',        'label' => 'deníky - upload'],
        ['key' => 'edit_edi_debug',     'label' => 'EDI debug / kontrola bodů'],
        ['key' => 'vysledkova_listina', 'label' => 'výsledková listina'],
        ['key' => 'rocni_vysledky',     'label' => 'roční výsledky'],
        ['key' => 'edit_kategorie',     'label' => 'správa kategorií, konfigurace'],
        ['key' => 'edit_import',        'label' => 'importy'],
    ],

    'admin_external' => [
        ['url' => 'http://www.ok1kpa.com/pa-podminky.htm', 'label' => 'Oficiální podmínky závodu', 'target' => '_blank'],
        ['url' => 'http://www.ok1kpa.com/',                'label' => 'Oficiální archiv výsledků',  'target' => '_blank'],
        ['url' => 'http://www.crk.cz/',                    'label' => 'Oficiální web pořadatele',    'target' => '_blank'],
    ],

];
