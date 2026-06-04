<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Vytvoří administrátorský účet „Beda".
 *
 * Heslo se předává v plaintextu – cast `password => hashed` na modelu User
 * ho při uložení zahashuje (bcrypt), takže se zde nevolá Hash::make (jinak by
 * došlo ke dvojímu hashování). Idempotentní: opakovaný seed účet jen aktualizuje.
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['name' => 'Beda'],
            [
                'email' => 'mail@hcsradio.cz',
                'password' => 'oK1dOz',
                'is_admin' => true,
            ],
        );

        $this->command->info("Admin účet 'Beda' připraven.");
    }
}
