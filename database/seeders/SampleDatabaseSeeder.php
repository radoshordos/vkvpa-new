<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Services\Edi\EdiheadCategoryBackfiller;
use Illuminate\Database\Seeder;

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
            VkvpaKategorieTableSeeder::class,
            EdiCategoryTableSeeder::class,
            EdiheadTableSeeder::class,
            EdilinesTableSeeder::class,
            VkvpaDataTableSeeder::class,
            DiskuseSeeder::class,
            VkvpaPrihlaseniTableSeeder::class,
            PrefixesTableSeeder::class,
        ]);

        // edi_head.edi_category_id snapshot nenese – dopočítáme ho (až po
        // naseedovaných vkvpa_data, na nichž závisí kroky 2–3):
        //   1. zařazení z hlavičky (shodně s importem),
        //   2. u kol <2026 převzetí autoritativní kategorie příspěvku
        //      (historické hlavičky jsou nespolehlivé),
        //   3. vynulování zbylých rozdílů vůči vkvpa_data.id_kategorie.
        $backfiller = app(EdiheadCategoryBackfiller::class);
        $backfiller->backfill();
        $backfiller->adoptVkvpaDataForOldRounds();
        $backfiller->nullifyVkvpaDataConflicts();
    }
}
