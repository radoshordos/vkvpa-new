<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

/**
 * Naplní DB ukázkovým datasetem (snapshot původního provozu → JSON v `seeders/data/`).
 */
class SampleDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Pořadí respektuje cizí klíče (na MySQL se vynucují) – rodičovské
        // tabulky se plní dřív než ty, které na ně odkazují: kola+kategorie →
        // edihead → edilines/edi_entries → discussion_posts. edi_prefixes je bez
        // závislostí.
        $this->call([
            EdiRoundTableSeeder::class,
            EdiBandTableSeeder::class,
            EdiCategoryTableSeeder::class,
            EdiHeadTableSeeder::class,
            EdiLineTableSeeder::class,
            EdiEntryTableSeeder::class,
            DiscussionSeeder::class,
            EdiPrefixTableSeeder::class,
        ]);

        // Některá kola (01–03/2026) mají ve snapshotu prázdné p_band/p_sect,
        // ale plný src – doplníme sloupce přeparsováním hlavičky (kvalita dat).
        Artisan::call('vkvpa:repair-edihead-band-sect');
    }
}
