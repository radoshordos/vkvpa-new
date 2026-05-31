<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use JsonException;

abstract class LegacyJsonTableSeeder extends Seeder
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
        return "seeders/data/{$this->table}.json"
                |> database_path(...)
                |> file_get_contents(...)
                |> (fn($x): mixed => json_decode((string) $x, true, 512, JSON_THROW_ON_ERROR));
    }

    private function restoreAutoIncrement(): void
    {
        if ($this->autoIncrement === null || DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE `{$this->table}` AUTO_INCREMENT = {$this->autoIncrement}");
    }
}
