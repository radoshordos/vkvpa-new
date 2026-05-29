<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

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
        $username = (string) env('ADMIN_USER', 'Beda');
        $password = (string) env('ADMIN_PASS', '');

        if ($password === '') {
            $this->command?->warn('ADMIN_PASS není nastaveno v .env – admin účet se nevytvoří.');
            return;
        }

        User::query()->updateOrCreate(
            ['name' => $username],
            [
                'password' => Hash::make($password),
                'is_admin' => true,
            ],
        );

        $this->command?->info("Admin účet '{$username}' připraven.");
    }
}
