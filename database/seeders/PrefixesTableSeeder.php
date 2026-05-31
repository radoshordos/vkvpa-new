<?php

declare(strict_types=1);

namespace Database\Seeders;

use Override;

class PrefixesTableSeeder extends LegacyJsonTableSeeder
{
    #[Override]
    protected string $table = 'prefixes';

    #[Override]
    protected ?int $autoIncrement = 533;
}
