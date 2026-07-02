<?php

declare(strict_types=1);

namespace Tests;

use App\Models\User;
use Database\Seeders\EdiBandTableSeeder;
use Database\Seeders\EdiCategoryTableSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Once;

abstract class TestCase extends BaseTestCase
{
    /**
     * Vytvoří uživatele pro testy. `is_admin` není ve #[Fillable] (ochrana proti
     * mass-assignment z requestu), proto ho nastavujeme explicitně přes forceFill.
     */
    protected function makeUser(string $name, bool $isAdmin = false, string $password = 'x'): User
    {
        $user = User::create(['name' => $name, 'password' => Hash::make($password)]);
        $user->forceFill(['is_admin' => $isAdmin])->save();

        return $user;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Once::flush();

        // edi_categories je referenční číselník, ze kterého CategoryResolver páruje
        // kategorie – import bez něj selže. V provozu je vždy naseedovaný, takže
        // ho zpřístupníme i testům (jen když migrace proběhly a je prázdný).
        // edi_bands (pásma) musí být naseedované dřív – kategorie na ně FK-ují.
        if (Schema::hasTable('edi_bands') && DB::table('edi_bands')->doesntExist()) {
            $this->seed(EdiBandTableSeeder::class);
        }
        if (Schema::hasTable('edi_categories') && DB::table('edi_categories')->doesntExist()) {
            $this->seed(EdiCategoryTableSeeder::class);
        }
    }
}
