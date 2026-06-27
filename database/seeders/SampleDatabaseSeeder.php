<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Services\Edi\EdiheadCategoryBackfiller;
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
        // edihead → edilines/vkvpa_data → diskuse. prihlaseni (user_id NULL) a
        // prefixes jsou bez závislostí.
        $this->call([
            VkvpaKolaTableSeeder::class,
            EdiCategoryTableSeeder::class,
            EdiheadTableSeeder::class,
            EdilinesTableSeeder::class,
            VkvpaDataTableSeeder::class,
            DiskuseSeeder::class,
            VkvpaPrihlaseniTableSeeder::class,
            PrefixesTableSeeder::class,
        ]);

        // Některá kola (01–03/2026) mají ve snapshotu prázdné p_band/p_sect,
        // ale plný src – doplníme sloupce přeparsováním hlavičky (kvalita dat).
        Artisan::call('vkvpa:repair-edihead-band-sect');

        // edi_head.edi_category_id snapshot nenese – nastavíme ho 1:1 z
        // autoritativní kategorie příspěvku (vkvpa_data.id_kategorie); osiřelé
        // i víceznačné deníky zůstávají NULL. Musí běžet až po vkvpa_data.
        app(EdiheadCategoryBackfiller::class)->mirrorVkvpaDataCategory();
    }
}
