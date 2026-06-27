<?php

declare(strict_types=1);

namespace Database\Seeders;

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
    }
}
