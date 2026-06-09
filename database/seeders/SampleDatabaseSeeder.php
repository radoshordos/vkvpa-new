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
        $this->call([
            EdiheadTableSeeder::class,
            EdilinesTableSeeder::class,
            PrefixesTableSeeder::class,
            VkvpaKategorieTableSeeder::class,
            VkvpaKolaTableSeeder::class,
            VkvpaDataTableSeeder::class,
            VkvpaPrihlaseniTableSeeder::class,
            DiskuseSeeder::class,
        ]);
    }
}
