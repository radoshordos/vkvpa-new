<?php

declare(strict_types=1);

namespace Database\Seeders;

use Override;

class EdiheadTableSeeder extends JsonTableSeeder
{
    #[Override]
    protected string $table = 'edihead';

    #[Override]
    protected ?int $autoIncrement = 23111;
}
