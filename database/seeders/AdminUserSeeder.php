<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Config;
use RuntimeException;

/**
 * Vytvoří administrátorský účet.
 *
 * Heslo se předává v plaintextu – cast `password => hashed` na modelu User
 * ho při uložení zahashuje (bcrypt), takže se zde nevolá Hash::make (jinak by
 * došlo ke dvojímu hashování). Idempotentní: opakovaný seed účet jen aktualizuje.
 *
 * Vyžaduje proměnné prostředí ADMIN_USER a ADMIN_PASS (přes config/vkvpa.php).
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $name = Config::string('vkvpa.admin_user', '');
        $pass = Config::string('vkvpa.admin_pass', '');
        $email = Config::string('vkvpa.admin_email', 'admin@example.com');

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
