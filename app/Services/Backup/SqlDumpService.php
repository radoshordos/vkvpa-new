<?php

declare(strict_types=1);

namespace App\Services\Backup;

use Generator;
use Illuminate\Database\Connection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PDO;

/**
 * Generátor SQL zálohy vybraných tabulek (schéma + data) čistě v PHP.
 *
 * Výstup je proud řetězců (Generator) určený pro StreamedResponse, takže ani
 * velké tabulky (`edilines`) nedrží celý dump v paměti – řádky se čtou po
 * dávkách (`chunkById`) a rovnou zapisují jako vícehodnotové `INSERT`.
 *
 * Cílem obnovy je produkční MySQL, proto je rámec dumpu psán v MySQL dialektu
 * (zpětníky kolem identifikátorů, `SET FOREIGN_KEY_CHECKS`). DDL (`CREATE TABLE`)
 * se čte přímo z databáze, takže odpovídá reálnému schématu připojeného driveru
 * (MySQL v produkci, SQLite v testech).
 */
final class SqlDumpService
{
    /** Počet řádků sloučených do jednoho INSERT příkazu. */
    private const int ROWS_PER_INSERT = 200;

    /** Velikost dávky čtené z databáze. */
    private const int CHUNK = 1000;

    /**
     * Vygeneruje SQL dump zadaných tabulek jako proud řetězců.
     *
     * @param  list<string>  $tables  názvy tabulek (volající ručí za allowlist)
     * @return Generator<int, string>
     */
    public function stream(array $tables): Generator
    {
        /** @var Connection $conn */
        $conn = DB::connection();
        $pdo = $conn->getPdo();

        yield $this->fileHeader($tables);

        foreach ($tables as $table) {
            yield from $this->tableSection($conn, $pdo, $table);
        }

        yield $this->fileFooter();
    }

    /**
     * @param  list<string>  $tables
     */
    private function fileHeader(array $tables): string
    {
        $now = Carbon::now()->format('Y-m-d H:i:s');
        $list = implode(', ', $tables);

        return <<<SQL
            -- VKV PA – SQL záloha tabulek
            -- Vygenerováno: {$now}
            -- Tabulky: {$list}

            SET NAMES utf8mb4;
            SET FOREIGN_KEY_CHECKS = 0;

            SQL;
    }

    private function fileFooter(): string
    {
        return "\nSET FOREIGN_KEY_CHECKS = 1;\n";
    }

    /**
     * @return Generator<int, string>
     */
    private function tableSection(Connection $conn, PDO $pdo, string $table): Generator
    {
        $q = $this->quoteIdent($table);

        yield "\n-- --------------------------------------------------------\n";
        yield "-- Tabulka {$q}\n";
        yield "-- --------------------------------------------------------\n\n";
        yield "DROP TABLE IF EXISTS {$q};\n";
        yield $this->createTableStatement($conn, $table)."\n\n";

        yield from $this->dataStatements($conn, $pdo, $table);
    }

    /**
     * DDL tabulky přečtené z databáze (driver-specific), ukončené středníkem.
     */
    private function createTableStatement(Connection $conn, string $table): string
    {
        if ($conn->getDriverName() === 'sqlite') {
            $row = $conn->selectOne(
                "SELECT sql FROM sqlite_master WHERE type = 'table' AND name = ?",
                [$table],
            );

            $sql = is_object($row) && is_string($row->sql ?? null) ? $row->sql : '';

            return rtrim($sql, "; \n").'; ';
        }

        // MySQL/MariaDB: SHOW CREATE TABLE vrací sloupec „Create Table".
        $row = (array) $conn->selectOne('SHOW CREATE TABLE '.$this->quoteIdent($table));
        $ddl = $row['Create Table'] ?? $row['Create View'] ?? '';

        return (is_string($ddl) ? $ddl : '').';';
    }

    /**
     * Vícehodnotové INSERT příkazy s daty tabulky, čtené po dávkách.
     *
     * @return Generator<int, string>
     */
    private function dataStatements(Connection $conn, PDO $pdo, string $table): Generator
    {
        $columns = [];
        $quotedCols = [];
        foreach (Schema::getColumnListing($table) as $col) {
            if (is_string($col)) {
                $columns[] = $col;
                $quotedCols[] = $this->quoteIdent($col);
            }
        }
        if ($columns === []) {
            return;
        }

        $qTable = $this->quoteIdent($table);
        $insertHead = "INSERT INTO {$qTable} (".implode(', ', $quotedCols).") VALUES\n";

        /** @var list<string> $buffer */
        $buffer = [];
        $key = $this->orderKey($table, $columns);

        // Keyset stránkování přes seřaditelný klíč drží konstantní paměť i u
        // velmi velkých tabulek (edilines) – na rozdíl od OFFSET nezpomaluje.
        $lastId = 0;
        do {
            $rows = $conn->table($table)
                ->where($key, '>', $lastId)
                ->orderBy($key)
                ->limit(self::CHUNK)
                ->get();

            foreach ($rows as $row) {
                $buffer[] = $this->rowTuple($pdo, (array) $row, $columns);
                if (count($buffer) >= self::ROWS_PER_INSERT) {
                    yield $insertHead.implode(",\n", $buffer).";\n";
                    $buffer = [];
                }
            }

            $last = $rows->last();
            $lastValue = is_object($last) ? ($last->{$key} ?? null) : null;
            $lastId = is_numeric($lastValue) ? (int) $lastValue : 0;
        } while ($rows->count() === self::CHUNK && $lastId > 0);

        if ($buffer !== []) {
            yield $insertHead.implode(",\n", $buffer).";\n";
        }
    }

    /**
     * Sloupec pro stránkování dávek – primárně `id`, jinak první sloupec.
     *
     * @param  list<string>  $columns
     */
    private function orderKey(string $table, array $columns): string
    {
        return in_array('id', $columns, true) ? 'id' : $columns[0];
    }

    /**
     * Jeden řádek jako `(v1, v2, …)`.
     *
     * @param  array<array-key, mixed>  $row  řádek jako pole (přetypovaný stdClass)
     * @param  list<string>  $columns
     */
    private function rowTuple(PDO $pdo, array $row, array $columns): string
    {
        $values = [];
        foreach ($columns as $col) {
            $values[] = $this->quoteValue($pdo, $row[$col] ?? null);
        }

        return '('.implode(', ', $values).')';
    }

    /**
     * Bezpečné SQL literály. Nevalidní UTF-8 (binární obsah, např. originální
     * `edihead.src` ve Windows-1250) se zapíše jako hexadecimální literál `0x…`,
     * aby utf8mb4 dump neobsahoval poškozené bajty.
     */
    private function quoteValue(PDO $pdo, mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_string($value)) {
            if ($value !== '' && ! mb_check_encoding($value, 'UTF-8')) {
                return '0x'.bin2hex($value);
            }

            return $pdo->quote($value);
        }

        // Sloupce závodních tabulek vrací jen skaláry/NULL; nečekaný typ
        // (pole/objekt) raději vynecháme jako NULL, než bychom riskovali pád.
        return 'NULL';
    }

    /**
     * Identifikátor obalený zpětníky (MySQL); zpětníky akceptuje i SQLite.
     */
    private function quoteIdent(string $name): string
    {
        return '`'.str_replace('`', '``', $name).'`';
    }
}
