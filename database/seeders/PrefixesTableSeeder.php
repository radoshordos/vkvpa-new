<?php

declare(strict_types=1);

namespace Database\Seeders;

use Override;

class PrefixesTableSeeder extends JsonTableSeeder
{
    #[Override]
    protected string $table = 'prefixes';

    #[Override]
    protected ?int $autoIncrement = 533;
}
