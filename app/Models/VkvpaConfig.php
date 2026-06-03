<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\WithoutIncrementing;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Override;

/**
 * Konfigurace systému jako klíč–hodnota.
 *
 * @property string $cfg_key
 * @property string|null $cfg_value
 */
#[Table(name: 'vkvpa_config', key: 'cfg_key', keyType: 'string')]
#[WithoutIncrementing]
#[WithoutTimestamps]
class VkvpaConfig extends Model
{
    #[Override]
    protected $guarded = [];

    /**
     * Pohodlné načtení hodnoty konfigurace s výchozí hodnotou.
     */
    public static function get(string $key, ?string $default = null): ?string
    {
        return static::query()->find($key)->cfg_value ?? $default;
    }

    /**
     * Uloží/aktualizuje hodnotu konfigurace.
     */
    public static function put(string $key, ?string $value): void
    {
        static::query()->updateOrCreate(
            ['cfg_key' => $key],
            ['cfg_value' => $value],
        );
    }
}
