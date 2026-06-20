<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Config;
use Laravel\Prompts\Exceptions\NonInteractiveValidationException;
use RuntimeException;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * Vytvoří administrátorský účet.
 *
 * Heslo se předává v plaintextu – cast `password => hashed` na modelu User
 * ho při uložení zahashuje (bcrypt), takže se zde nevolá Hash::make (jinak by
 * došlo ke dvojímu hashování). Idempotentní: opakovaný seed účet jen aktualizuje.
 *
 * ADMIN_USER a ADMIN_PASS se čtou z .env (přes config/vkvpa.php); pokud chybí
 * a běh je interaktivní, doplní se promptem. V neinteraktivním běhu (CI, testy,
 * `--no-interaction`) prompt vyhodí NonInteractiveValidationException, kterou
 * zachytíme a vyhodíme stejnou RuntimeException jako dřív.
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $name = Config::string('vkvpa.admin_user', '');
        $pass = Config::string('vkvpa.admin_pass', '');
        $email = Config::string('vkvpa.admin_email', 'admin@example.com');

        if (blank($name) || blank($pass)) {
            [$name, $pass] = $this->promptForCredentials($name, $pass);
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

    /**
     * @return array{0: string, 1: string}
     */
    private function promptForCredentials(string $name, string $pass): array
    {
        try {
            if (blank($name)) {
                $name = text('Jméno administrátorského účtu (ADMIN_USER)', required: true);
            }

            if (blank($pass)) {
                $pass = password('Heslo administrátorského účtu (ADMIN_PASS)', required: true);
            }
        } catch (NonInteractiveValidationException) {
            throw new RuntimeException('ADMIN_USER a ADMIN_PASS musí být nastaveny v .env před spuštěním seederu.');
        }

        return [$name, $pass];
    }
}
