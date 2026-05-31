<?php

declare(strict_types=1);

namespace Database\Seeders;

use Override;

class VkvpaDataTableSeeder extends LegacyJsonTableSeeder
{
    #[Override]
    protected string $table = 'vkvpa_data';

    #[Override]
    protected ?int $autoIncrement = 28747;
}
