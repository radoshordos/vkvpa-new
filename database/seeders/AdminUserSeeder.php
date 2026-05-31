<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Vytvoří administrátorský účet z .env (ADMIN_USER / ADMIN_PASS).
 * Nahrazuje hardcoded `Beda`/`oK1dOz` z legacy head.php.
 *
 * V .env nastav ADMIN_USER a (silné) ADMIN_PASS. Heslo se uloží hashované.
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        /*
        User::query()->updateOrCreate(
            ['name' => $name],
            [
                'password' => Hash::make($password),
                'is_admin' => true,
            ],
        );

        $this->command?->info("Admin účet '{$name}' připraven.");
        */
    }
}
