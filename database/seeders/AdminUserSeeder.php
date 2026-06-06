<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Vytvoří administrátorský účet.
 *
 * Heslo se předává v plaintextu – cast `password => hashed` na modelu User
 * ho při uložení zahashuje (bcrypt), takže se zde nevolá Hash::make (jinak by
 * došlo ke dvojímu hashování). Idempotentní: opakovaný seed účet jen aktualizuje.
 *
 * Vyžaduje proměnné prostředí ADMIN_USER a ADMIN_PASS.
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $name = env('ADMIN_USER');
        $pass = env('ADMIN_PASS');
        $email = env('ADMIN_EMAIL', 'admin@example.com');

        if (blank($name) || blank($pass)) {
            throw new RuntimeException('ADMIN_USER a ADMIN_PASS musí být nastaveny v .env před spuštěním seederu.');
        }

        User::query()->updateOrCreate(
            ['name' => $name],
            [
                'email' => $email,
                'password' => $pass,
                'is_admin' => true,
            ],
        );

        $this->command->info("Admin účet '{$name}' připraven.");
    }
}
