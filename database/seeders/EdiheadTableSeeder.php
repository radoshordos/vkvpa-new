<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Services\Edi\EdiheadCategoryBackfiller;

class EdiheadTableSeeder extends JsonTableSeeder
{
    protected string $table = 'edi_head';

    protected ?int $autoIncrement = 23379;

    public function run(): void
    {
        parent::run();

        // Snapshot nese edi_category_id = NULL; zařadíme ho přes resolver
        // (vyžaduje už naplněnou edi_category – seeduje se dřív). Tím má i
        // čerstvá instalace sloupec naplněný shodně s importní cestou.
        app(EdiheadCategoryBackfiller::class)->backfill();
    }
}
