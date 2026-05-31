<?php

declare(strict_types=1);

namespace Database\Seeders;

class EdiheadTableSeeder extends LegacyJsonTableSeeder
{
    #[\Override]
    protected string $table = 'edihead';

    #[\Override]
    protected ?int $autoIncrement = 23111;
}
