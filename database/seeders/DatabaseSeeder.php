<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            // Data z původní DB (legacy dump → JSON). Volá dílčí seedery.
            LegacyDatabaseSeeder::class,

            // Administrátorský účet z .env (ADMIN_USER / ADMIN_PASS).
            AdminUserSeeder::class,
        ]);
    }
}
