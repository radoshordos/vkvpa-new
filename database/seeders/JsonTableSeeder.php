<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use JsonException;

/**
 * Základ pro seedery plnící tabulku z JSON snapshotu v `seeders/data/{table}.json`.
 */
abstract class JsonTableSeeder extends Seeder
{
    protected string $table;

    protected ?int $autoIncrement = null;

    /**
     * @throws JsonException
     */
    public function run(): void
    {
        $this->truncateTable();

        foreach (array_chunk($this->rows(), 500) as $chunk) {
            DB::table($this->table)->insert($chunk);
        }

        $this->restoreAutoIncrement();
    }

    private function truncateTable(): void
    {
        Schema::disableForeignKeyConstraints();

        try {
            DB::table($this->table)->truncate();
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rows(): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = json_decode(
            (string) file_get_contents(database_path(sprintf('seeders/data/%s.json', $this->table))),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        return $rows;
    }

    private function restoreAutoIncrement(): void
    {
        if ($this->autoIncrement === null || DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement(sprintf('ALTER TABLE `%s` AUTO_INCREMENT = %d', $this->table, $this->autoIncrement));
    }
}
