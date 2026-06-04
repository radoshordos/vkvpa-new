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
        ['key' => 'kola.index',         'label' => 'kola závodu <br> contest period'],
        ['key' => 'hlaseni.index',      'label' => 'odeslat deník<br>log import'],
        ['key' => 'vysledkova_listina', 'label' => 'výsledková listina<br>results'],
        ['key' => 'rocni_vysledky',     'label' => 'roční výsledky<br>year results'],
        ['url' => 'https://vkvpa.hamradio.cz/rules/PA_VKV_2023–2022_12_23_en.pdf', 'label' => 'contest rules (pdf eng)'],
        ['url' => 'https://vkvpa.hamradio.cz/rules/PA_VKV_2023–2022_12_23_cz.pdf', 'label' => 'podmínky závodu(pdf cz)', 'target' => '_blank'],
    ],

    'admin' => [
        ['key' => 'kola.index',         'label' => 'kola závodu'],
        ['key' => 'hlaseni.index',      'label' => 'hlášení'],
        ['key' => 'deniky.index',       'label' => 'deníky - upload'],
        ['key' => 'edi.debug.create',   'label' => 'EDI debug / kontrola bodů'],
        ['key' => 'vysledkova_listina', 'label' => 'výsledková listina'],
        ['key' => 'rocni_vysledky',     'label' => 'roční výsledky'],
        ['key' => 'kategorie.index',    'label' => 'správa kategorií, konfigurace'],
        ['key' => 'importy.index',      'label' => 'importy'],
    ],

    'admin_external' => [
        ['url' => 'http://www.ok1kpa.com/pa-podminky.htm', 'label' => 'Oficiální podmínky závodu', 'target' => '_blank'],
        ['url' => 'http://www.ok1kpa.com/',                'label' => 'Oficiální archiv výsledků',  'target' => '_blank'],
        ['url' => 'http://www.crk.cz/',                    'label' => 'Oficiální web pořadatele',    'target' => '_blank'],
    ],

];
