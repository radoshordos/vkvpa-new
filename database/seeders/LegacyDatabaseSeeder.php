<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class LegacyDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            EdiheadTableSeeder::class,
            EdilinesTableSeeder::class,
            PrefixesTableSeeder::class,
            VkvpaConfigTableSeeder::class,
            VkvpaKategorieTableSeeder::class,
            VkvpaKolaTableSeeder::class,
            VkvpaDataTableSeeder::class,
            VkvpaPrihlaseniTableSeeder::class,
            DiskuseSeeder::class,
        ]);
    }
}
