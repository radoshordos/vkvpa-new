<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Config;

/**
 * Typový přístup ke konfiguraci vkvpa.* – nahrazuje roztroušené Config::string/integer volání.
 */
final class VkvpaSettings
{
    public static function mailEnabled(): bool
    {
        return Config::boolean('vkvpa.mail_enabled', true);
    }

    public static function contactMail(): string
    {
        return Config::string('vkvpa.contact_mail', '');
    }

    public static function contactName(): string
    {
        return Config::string('vkvpa.contact_name', '');
    }

    public static function tokenTtlDays(): int
    {
        return Config::integer('vkvpa.token_ttl_days', 5);
    }

    public static function ediMaxSizeKb(): int
    {
        return Config::integer('vkvpa.edi_max_size_kb', 500);
    }

    public static function importMaxSizeKb(): int
    {
        return Config::integer('vkvpa.import_max_size_kb', 20480);
    }

    public static function importMaxFiles(): int
    {
        return Config::integer('vkvpa.import_max_files', 200);
    }

    public static function listaMaxRows(): int
    {
        return Config::integer('vkvpa.listina_max_rows', 1000);
    }

    public static function nonEdiNullifyFromKolo(): int
    {
        return Config::integer('vkvpa.non_edi_nullify_from_kolo', 91);
    }

    public static function finalizeFallbackDays(): int
    {
        return Config::integer('vkvpa.finalize_fallback_days', 20);
    }

    public static function yearlyCacheFresh(): int
    {
        return Config::integer('vkvpa.yearly_cache_fresh', 300);
    }

    public static function yearlyCacheStale(): int
    {
        return Config::integer('vkvpa.yearly_cache_stale', 1800);
    }

    public static function roundStationsCacheTtl(): int
    {
        return Config::integer('vkvpa.round_stations_cache_ttl', 3600);
    }

    /** @return list<string> */
    public static function domesticPrefixes(): array
    {
        /** @var list<string> */
        return Config::array('vkvpa.domestic_prefixes', ['OK', 'OL']);
    }

    /** @return list<string> */
    public static function mailImageAllowlist(): array
    {
        /** @var list<string> */
        return Config::array('vkvpa.mail_image_allowlist', []);
    }

    /**
     * Tabulky povolené pro SQL zálohu, seskupené (klíč skupiny → seznam tabulek).
     *
     * @return array<string, list<string>>
     */
    public static function dbBackupTableGroups(): array
    {
        /** @var array<string, list<string>> */
        return Config::array('vkvpa.db_backup_table_groups', []);
    }

    /**
     * Plochý seznam všech tabulek povolených pro SQL zálohu (allowlist).
     *
     * @return list<string>
     */
    public static function dbBackupTables(): array
    {
        $groups = array_values(self::dbBackupTableGroups());

        return $groups === [] ? [] : array_merge(...$groups);
    }

    public static function contestWindowFrom(): string
    {
        return once(fn (): string => Config::string('vkvpa.contest_window.from', '0800'));
    }

    public static function contestWindowTo(): string
    {
        return once(fn (): string => Config::string('vkvpa.contest_window.to', '1100'));
    }
}
