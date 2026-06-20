<?php

declare(strict_types=1);

namespace Database\Seeders;

use Generator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use JsonException;
use RuntimeException;

/**
 * Základ pro seedery plnící tabulku ze snapshotu v `seeders/data/`.
 *
 * Velké tabulky se drží jako gzipovaný newline-delimited JSON
 * (`{table}.jsonl.gz` – jeden objekt na řádek) a čtou se streamovaně po
 * řádcích, takže seedování nedrží v paměti celý dataset. Malé tabulky můžou
 * zůstat jako jedno JSON pole (`{table}.json`); obě varianty se rozpoznají
 * automaticky.
 */
abstract class JsonTableSeeder extends Seeder
{
    private const CHUNK = 500;

    protected string $table;

    protected ?int $autoIncrement = null;

    /**
     * @throws JsonException
     */
    public function run(): void
    {
        $this->truncateTable();

        $chunk = [];
        foreach ($this->rows() as $row) {
            $chunk[] = $row;
            if (count($chunk) >= self::CHUNK) {
                DB::table($this->table)->insert($chunk);
                $chunk = [];
            }
        }
        if ($chunk !== []) {
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
     * Vrátí řádky snapshotu. Preferuje streamovaný `{table}.jsonl[.gz]`
     * (nízká paměť), jinak spadne zpět na celé JSON pole `{table}.json[.gz]`.
     *
     * @return iterable<array<string, mixed>>
     *
     * @throws JsonException
     */
    protected function rows(): iterable
    {
        $base = database_path(sprintf('seeders/data/%s', $this->table));

        foreach (["{$base}.jsonl.gz", "{$base}.jsonl"] as $file) {
            if (is_file($file)) {
                return $this->streamJsonl($file);
            }
        }

        $json = "{$base}.json";
        $path = is_file("{$json}.gz") ? "compress.zlib://{$json}.gz" : $json;

        /** @var list<array<string, mixed>> $rows */
        $rows = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        return $rows;
    }

    /**
     * Čte newline-delimited JSON řádek po řádku (gzip dekomprimuje stream
     * wrapper transparentně), takže v paměti je vždy jen jeden řádek.
     *
     * @return Generator<int, array<string, mixed>>
     *
     * @throws JsonException
     */
    private function streamJsonl(string $file): Generator
    {
        $path = str_ends_with($file, '.gz') ? "compress.zlib://{$file}" : $file;

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException(sprintf('Nelze otevřít snapshot %s.', $file));
        }

        try {
            while (($line = fgets($handle)) !== false) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }

                /** @var array<string, mixed> $row */
                $row = json_decode($line, true, 512, JSON_THROW_ON_ERROR);

                yield $row;
            }
        } finally {
            fclose($handle);
        }
    }

    private function restoreAutoIncrement(): void
    {
        if ($this->autoIncrement === null || DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement(sprintf('ALTER TABLE `%s` AUTO_INCREMENT = %d', $this->table, $this->autoIncrement));
    }
}
