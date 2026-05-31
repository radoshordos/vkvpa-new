<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Kolo závodu (contest period).
 *
 * @property int $id
 * @property \Illuminate\Support\Carbon $datum_konani
 * @property \Illuminate\Support\Carbon|null $datum_uzaverky
 * @property bool $aktivni
 * @property string $nazev
 * @property \Illuminate\Support\Carbon|null $vyhodnoceno
 * @property string $poznamka
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\VkvpaData> $hlaseni
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\VkvpaDiskuse> $diskuse
 */
class VkvpaKola extends Model
{
    protected $table = 'vkvpa_kola';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'aktivni' => 'boolean',
        'datum_konani' => 'date',
        'datum_uzaverky' => 'datetime',
        'vyhodnoceno' => 'datetime',
    ];

    public function hlaseni(): HasMany
    {
        return $this->hasMany(VkvpaData::class, 'id_kola', 'id');
    }

    public function diskuse(): HasMany
    {
        return $this->hasMany(VkvpaDiskuse::class, 'id_kola', 'id');
    }

    /**
     * Je kolo v aktivní fázi pro příjem hlášení?
     * Ekvivalent legacy je_aktivni_kolo():
     *  1) sloupec aktivni = 1, nebo
     *  2) záložní pojistka – v kole jsou čerstvá neschválená data.
     */
    public function isActive(): bool
    {
        if ($this->aktivni) {
            return true;
        }

        return VkvpaData::query()
            ->where('id_kola', $this->id)
            ->where('schvaleno', false)
            ->exists();
    }

    /**
     * Statický ekvivalent legacy je_aktivni_kolo($id_kola).
     */
    public static function jeAktivni(int $idKola): bool
    {
        if ($idKola <= 0) {
            return false;
        }

        return static::query()->whereKey($idKola)->first()?->isActive() ?? false;
    }

    /**
     * Existuje vůbec nějaké aktivní kolo (pro zobrazení formuláře)?
     */
    public static function existujeAktivni(): bool
    {
        if (static::query()->where('aktivni', true)->exists()) {
            return true;
        }

        return VkvpaData::query()->where('schvaleno', false)->exists();
    }

    /** Scope: jen kola označená jako aktivní. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('aktivni', true);
    }
}
