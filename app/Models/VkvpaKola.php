<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\KoloStav;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Override;

/**
 * Kolo závodu (contest period).
 *
 * @property int $id
 * @property Carbon $datum_konani
 * @property Carbon|null $datum_uzaverky
 * @property bool $aktivni
 * @property string $nazev
 * @property Carbon|null $vyhodnoceno
 * @property string $poznamka
 * @property-read Collection<int, VkvpaData> $hlaseni
 * @property-read Collection<int, Prispevek> $diskuse
 */
#[Fillable(['datum_konani', 'datum_uzaverky', 'nazev', 'poznamka', 'vyhodnoceno', 'aktivni'])]
#[Table(name: 'vkvpa_kola', key: 'id')]
#[WithoutTimestamps]
class VkvpaKola extends Model
{
    /** @return HasMany<VkvpaData, $this> */
    public function hlaseni(): HasMany
    {
        return $this->hasMany(VkvpaData::class, 'id_kola', 'id');
    }

    /** @return HasMany<Prispevek, $this> */
    public function diskuse(): HasMany
    {
        return $this->hasMany(Prispevek::class, 'kolo_id', 'id');
    }

    /**
     * Fáze životního cyklu kola odvozená ze sloupců.
     *
     * Pořadí podmínek je dané prioritou:
     *  1) vyhodnocené kolo je terminální stav (`vyhodnoceno`),
     *  2) jinak rozhoduje příznak `aktivni` (probíhá příjem),
     *  3) den závodu ještě nenastal → nadcházející,
     *  4) den závodu proběhl, ale uzávěrka ne → stále se přijímají hlášení,
     *  5) uzávěrka uplynula a kolo není vyhodnocené → zpracování výsledků.
     */
    public function stav(): KoloStav
    {
        if ($this->vyhodnoceno !== null) {
            return KoloStav::Vyhodnocene;
        }

        if ($this->aktivni) {
            return KoloStav::Aktivni;
        }

        if ($this->datum_konani->isAfter(Carbon::now()->endOfDay())) {
            return KoloStav::Nadchazejici;
        }

        if ($this->datum_uzaverky !== null && $this->datum_uzaverky->isFuture()) {
            return KoloStav::Prijem;
        }

        return KoloStav::Uzavrene;
    }

    /**
     * Je kolo v aktivní fázi pro příjem hlášení?
     *  1) stav kola je {@see KoloStav::Aktivni}, nebo
     *  2) záložní pojistka – v kole jsou čerstvá neschválená data
     *     (účastník právě odeslal hlášení a kolo se mezitím automaticky
     *     deaktivovalo; ať pořád vidí a může upravit svůj rozpracovaný záznam).
     */
    public function isActive(): bool
    {
        if ($this->stav() === KoloStav::Aktivni) {
            return true;
        }

        return VkvpaData::query()
            ->where('id_kola', $this->id)
            ->where('schvaleno', false)
            ->exists();
    }

    /**
     * Statická varianta isActive() pro dané kolo dle ID.
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

    /**
     * Scope: jen kola označená jako aktivní.
     *
     * @param  Builder<VkvpaKola>  $query
     * @return Builder<VkvpaKola>
     */
    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->where('aktivni', true);
    }

    #[Override]
    protected function casts(): array
    {
        return [
            'aktivni' => 'boolean',
            'datum_konani' => 'date',
            'datum_uzaverky' => 'datetime',
            'vyhodnoceno' => 'datetime',
        ];
    }
}
