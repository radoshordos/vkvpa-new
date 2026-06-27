<?php

declare(strict_types=1);

namespace Tests;

use Database\Seeders\EdiCategoryTableSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Once;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Once::flush();

        // edi_category je referenční číselník, ze kterého CategoryResolver páruje
        // kategorie – import bez něj selže. V provozu je vždy naseedovaný, takže
        // ho zpřístupníme i testům (jen když migrace proběhly a je prázdný).
        if (Schema::hasTable('edi_category') && DB::table('edi_category')->doesntExist()) {
            $this->seed(EdiCategoryTableSeeder::class);
        }
    }
}
