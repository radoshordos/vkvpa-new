<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Jednorázový import historické DB (dumpy z bývalého systému) do nového schématu.
 *
 * Očekává Adminer dumpy edihead.sql, edilines.sql a vkvpa_data.sql se starými
 * názvy sloupců; přemapuje je na snake_case, nahradí dvojici EDI/EDI_ID
 * vazbou edihead_id a deduplikuje opakovaná odeslání téhož hlášení
 * (stejné kolo + značka + kategorie, vyhrává nejnovější záznam).
 *
 * POZOR: cílové tabulky edihead, edilines a vkvpa_data nejdřív vyprázdní.
 */
class ImportLegacyDb extends Command
{
    protected $signature = 'legacy:import
        {path=database/source_sql_full : Adresář s dumpy edihead.sql, edilines.sql a vkvpa_data.sql}
        {--force : Nevyžadovat potvrzení}';

    protected $description = 'Naimportuje dumpy historické DB do tabulek edihead, edilines a vkvpa_data (stávající obsah nahradí)';

    /** @var array<string, string> Mapa sloupců dumpu edihead na nové schéma (v pořadí dumpu). */
    private const EDIHEAD_COLUMNS = [
        'ID' => 'id',
        'id_kola' => 'id_kola',
        'TDate' => 't_date',
        'PCall' => 'p_call',
        'PWWLo' => 'p_wwlo',
        'PSect' => 'p_sect',
        'PBand' => 'p_band',
        'RName' => 'r_name',
        'REmai' => 'r_emai',
        'RPhon' => 'r_phon',
        'RHBBS' => 'r_hbbs',
        'SPowe' => 's_powe',
        'STXEq' => 's_tx_eq',
        'SAnte' => 's_ante',
        'src' => 'src',
        'Remarks' => 'remarks',
        'stamp' => 'stamp',
        'd_cas' => 'd_cas',
        'SRCR' => 's_rcr',
    ];

    /** @var array<string, string> Mapa sloupců dumpu edilines na nové schéma (v pořadí dumpu). */
    private const EDILINES_COLUMNS = [
        'ID' => 'id',
        'IDS' => 'edihead_id',
        'Date' => 'date',
        'Time' => 'time',
        'CallSign' => 'call_sign',
        'Mode-code' => 'mode_code',
        'Sent-RST' => 'sent_rst',
        'Sent QSO number' => 'sent_qso_number',
        'Received-RST' => 'received_rst',
        'Received QSO number' => 'received_qso_number',
        'Received exchange' => 'received_exchange',
        'Received-WWL' => 'received_wwl',
        'QSO-Points' => 'qso_points',
        'New-Exchange-(N)' => 'new_exchange_n',
        'New-WWL-(N)' => 'new_wwl_n',
        'New-DXCC-(N)' => 'new_dxcc_n',
        'Duplicate-QSO-(D)' => 'duplicate_qso_d',
        'sqr' => 'sqr',
        'lon' => 'lon',
        'lat' => 'lat',
    ];

    public function handle(): int
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->error('Import podporuje pouze MySQL.');

            return self::FAILURE;
        }

        /** @var string $path */
        $path = $this->argument('path');
        $files = [];
        foreach (['edihead', 'edilines', 'vkvpa_data'] as $name) {
            $file = base_path($path.'/'.$name.'.sql');
            if (! is_file($file)) {
                $this->error(sprintf('Soubor %s neexistuje.', $file));

                return self::FAILURE;
            }
            $files[$name] = $file;
        }

        if (! $this->option('force')
            && ! $this->confirm('Tabulky edihead, edilines a vkvpa_data budou vyprázdněny a nahrazeny importem. Pokračovat?')) {
            return self::FAILURE;
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            DB::table('edilines')->truncate();
            DB::table('vkvpa_data')->truncate();
            DB::table('edihead')->truncate();

            $this->importMappedDump($files['edihead'], 'edihead', 'edihead', self::EDIHEAD_COLUMNS);
            $this->info(sprintf('edihead: %d řádků', DB::table('edihead')->count()));

            $this->importMappedDump($files['edilines'], 'edilines', 'edilines', self::EDILINES_COLUMNS);
            $this->info(sprintf('edilines: %d řádků', DB::table('edilines')->count()));

            $this->importVkvpaData($files['vkvpa_data']);
            $this->info(sprintf('vkvpa_data: %d řádků', DB::table('vkvpa_data')->count()));

            $orphans = DB::delete('DELETE l FROM edilines l LEFT JOIN edihead h ON h.id = l.edihead_id WHERE h.id IS NULL');
            if ($orphans > 0) {
                $this->warn(sprintf('Smazáno %d osiřelých QSO řádků bez hlavičky deníku.', $orphans));
            }
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        Cache::flush();
        $this->info('Hotovo, cache vyprázdněna.');

        return self::SUCCESS;
    }

    /**
     * Streamuje dump po statementech a INSERTy pouští s přemapovanou hlavičkou sloupců.
     *
     * Dump nesmí obsahovat syrové nové řádky uvnitř stringů (Adminer je escapuje),
     * takže statement vždy končí řádkem zakončeným ");".
     *
     * @param  array<string, string>  $columns
     */
    private function importMappedDump(string $file, string $sourceTable, string $targetTable, array $columns): void
    {
        $oldHeader = sprintf(
            'INSERT INTO `%s` (%s) VALUES',
            $sourceTable,
            implode(', ', array_map(static fn (string $c): string => '`'.$c.'`', array_keys($columns))),
        );
        $newHeader = sprintf(
            'INSERT INTO `%s` (%s) VALUES',
            $targetTable,
            implode(', ', array_map(static fn (string $c): string => '`'.$c.'`', array_values($columns))),
        );

        $handle = fopen($file, 'r');
        if ($handle === false) {
            throw new RuntimeException(sprintf('Nelze otevřít %s.', $file));
        }

        try {
            $statement = '';
            while (($line = fgets($handle)) !== false) {
                $statement .= $line;
                if (! str_ends_with(rtrim($line), ';')) {
                    continue;
                }

                $statement = ltrim($statement);
                if (str_starts_with($statement, 'INSERT INTO')) {
                    if (! str_starts_with($statement, $oldHeader)) {
                        throw new RuntimeException(sprintf(
                            'Neočekávaná hlavička INSERT v %s: %s',
                            $file,
                            substr($statement, 0, 200),
                        ));
                    }
                    // PDO::exec místo DB::unprepared – statement pochází ze souboru,
                    // takže nemůže být literal-string, který unprepared vyžaduje.
                    DB::connection()->getPdo()->exec($newHeader.substr($statement, strlen($oldHeader)));
                }
                $statement = '';
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * vkvpa_data jde přes pomocnou tabulku: dump má navíc sloupce EDI/EDI_ID
     * (nahrazené vazbou edihead_id) a obsahuje opakovaná odeslání, ze kterých
     * se bere jen nejnovější řádek pro každé kolo + značku + kategorii.
     */
    private function importVkvpaData(string $file): void
    {
        DB::statement('DROP TABLE IF EXISTS _legacy_vkvpa_data');
        DB::statement(<<<'SQL'
            CREATE TABLE _legacy_vkvpa_data (
                id int NOT NULL,
                id_kola int NOT NULL,
                id_kategorie int NULL,
                qrp tinyint NOT NULL,
                lp tinyint NOT NULL,
                znacka varchar(10) NOT NULL,
                locator varchar(6) NOT NULL,
                pocet int NOT NULL,
                bodu_za_qso int NOT NULL,
                nasobice int NOT NULL,
                body int NOT NULL,
                jmeno varchar(60) NOT NULL,
                mail varchar(250) NOT NULL,
                telefon varchar(20) NOT NULL,
                poznamka varchar(250) NOT NULL,
                soapbox varchar(250) NOT NULL,
                ip varchar(64) NOT NULL,
                EDI tinyint NOT NULL,
                EDI_ID int NOT NULL,
                poradi int NOT NULL,
                schvaleno tinyint NOT NULL,
                odeslano tinyint NOT NULL,
                session_id varchar(255) NOT NULL,
                `timestamp` timestamp NULL,
                PRIMARY KEY (id)
            ) CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);

        try {
            $this->importMappedDump($file, 'vkvpa_data', '_legacy_vkvpa_data', $this->legacyVkvpaColumns());

            DB::statement(<<<'SQL'
                INSERT INTO vkvpa_data (
                    id, id_kola, id_kategorie, qrp, lp, znacka, locator, pocet,
                    bodu_za_qso, nasobice, body, jmeno, mail, telefon, poznamka,
                    soapbox, ip, poradi, schvaleno, odeslano, session_id, `timestamp`,
                    edihead_id
                )
                SELECT
                    l.id, l.id_kola, l.id_kategorie, l.qrp, l.lp, l.znacka, l.locator, l.pocet,
                    l.bodu_za_qso, l.nasobice, l.body, l.jmeno, l.mail, l.telefon, l.poznamka,
                    l.soapbox, l.ip, l.poradi, l.schvaleno, l.odeslano, l.session_id, l.`timestamp`,
                    CASE
                        WHEN l.EDI_ID > 0 AND EXISTS (SELECT 1 FROM edihead h WHERE h.id = l.EDI_ID)
                        THEN l.EDI_ID
                    END
                FROM _legacy_vkvpa_data l
                WHERE NOT EXISTS (
                    SELECT 1 FROM _legacy_vkvpa_data n
                    WHERE n.id_kola = l.id_kola
                        AND n.znacka = l.znacka
                        AND n.id_kategorie <=> l.id_kategorie
                        AND n.id > l.id
                )
                SQL);

            $skipped = DB::table('_legacy_vkvpa_data')->count() - DB::table('vkvpa_data')->count();
            if ($skipped > 0) {
                $this->warn(sprintf('Přeskočeno %d duplicitních (opakovaně odeslaných) hlášení.', $skipped));
            }
        } finally {
            DB::statement('DROP TABLE IF EXISTS _legacy_vkvpa_data');
        }
    }

    /**
     * @return array<string, string>
     */
    private function legacyVkvpaColumns(): array
    {
        $columns = [
            'id', 'id_kola', 'id_kategorie', 'qrp', 'lp', 'znacka', 'locator',
            'pocet', 'bodu_za_qso', 'nasobice', 'body', 'jmeno', 'mail', 'telefon',
            'poznamka', 'soapbox', 'ip', 'EDI', 'EDI_ID', 'poradi', 'schvaleno',
            'odeslano', 'session_id', 'timestamp',
        ];

        return array_combine($columns, $columns);
    }
}
