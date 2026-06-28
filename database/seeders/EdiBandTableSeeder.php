<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\EdiBand;

/**
 * Naplní číselník pásem `edi_bands` z kanonického seznamu {@see EdiBand::CANONICAL}.
 * Musí běžet PŘED {@see EdiCategoryTableSeeder} (kategorie na pásmo FK-ují).
 */
class EdiBandTableSeeder extends JsonTableSeeder
{
    protected string $table = 'edi_bands';

    protected ?int $autoIncrement = 12;

    /**
     * @return list<array{id: int, token: string, name: string}>
     */
    protected function rows(): array
    {
        $rows = [];
        foreach (EdiBand::CANONICAL as $id => [$token, $name]) {
            $rows[] = ['id' => $id, 'token' => $token, 'name' => $name];
        }

        return $rows;
    }
}
