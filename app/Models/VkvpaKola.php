<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\KoloStav;
use App\Support\ContestWindow;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Override;

/**
 * Kolo závodu (contest period).
 *
 * @property int $id
 * @property Carbon $datum_konani start závodu (datetime, standardně 3. neděle 08:00 UTC)
 * @property Carbon|null $datum_uzaverky
 * @property string $nazev
 * @property Carbon|null $vyhodnoceno
 * @property string $poznamka
 * @property-read Collection<int, VkvpaData> $hlaseni
 * @property-read Collection<int, Prispevek> $diskuse
 */
#[Fillable(['datum_konani', 'datum_uzaverky', 'nazev', 'poznamka', 'vyhodnoceno'])]
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
     * Fáze životního cyklu kola – čistá funkce času, žádný stavový příznak:
     *  1) vyhodnocené kolo je terminální stav (`vyhodnoceno`),
     *  2) start závodu (`datum_konani`) ještě nenastal → nadcházející,
     *  3) uzávěrka uplynula a kolo není vyhodnocené → zpracování výsledků,
     *  4) závodní okno právě běží → probíhá,
     *  5) jinak (po závodě, před uzávěrkou) → příjem hlášení.
     */
    public function stav(): KoloStav
    {
        if ($this->vyhodnoceno !== null) {
            return KoloStav::Vyhodnocene;
        }

        $now = Carbon::now();

        if ($now->lt($this->datum_konani)) {
            return KoloStav::Nadchazejici;
        }

        if ($this->datum_uzaverky === null || $now->gt($this->datum_uzaverky)) {
            return KoloStav::Uzavrene;
        }

        if ($now->lt($this->konecZavodu())) {
            return KoloStav::Aktivni;
        }

        return KoloStav::Prijem;
    }

    /**
     * Konec závodního okna: start (`datum_konani`) + délka závodu odvozená
     * z konfigurace {@see ContestWindow} (standardně 0800–1100 = 3 hodiny).
     * Délka se přičítá ke skutečnému startu, takže posunutý start zachová
     * trvání závodu.
     */
    public function konecZavodu(): Carbon
    {
        return $this->datum_konani->copy()->addMinutes(self::delkaZavoduMinuty());
    }

    /**
     * Délka závodního okna v minutách dle ContestWindow ('HHMM' hranice).
     */
    private static function delkaZavoduMinuty(): int
    {
        $minutes = static fn (string $hhmm): int => 60 * (int) substr($hhmm, 0, 2) + (int) substr($hhmm, 2, 2);

        return max(0, $minutes(ContestWindow::to()) - $minutes(ContestWindow::from()));
    }

    /**
     * Je otevřené upload okno kola? Hlášení (EDI i manuální) se od běžných
     * závodníků přijímají jen ve stavech Probíhá a Příjem hlášení – od startu
     * závodu (`datum_konani`) do uzávěrky. Admin smí nahrávat kdykoliv
     * (výjimku řeší volající, ne tato metoda).
     */
    public function prijimaHlaseni(): bool
    {
        return in_array($this->stav(), [KoloStav::Aktivni, KoloStav::Prijem], true);
    }

    /**
     * Kolo, pro které se zobrazují průběžné výsledky: nejstarší nevyhodnocené
     * s otevřeným upload oknem. Když žádné není, průběžné výsledky se
     * neukazují (stránka hlásí „vyhodnocování neprobíhá", položka menu
     * je skrytá).
     */
    public static function aktualniProPrubezne(): ?self
    {
        return static::query()
            ->whereNull('vyhodnoceno')
            ->orderBy('datum_konani')
            ->get()
            ->first(fn (self $kolo): bool => $kolo->prijimaHlaseni());
    }

    /**
     * Je otevřené upload okno alespoň jednoho kola? Řídí zobrazení stránky
     * hlášení (EDI upload i ruční formulář) – mimo okno se formuláře neukazují
     * (a POST je stejně odmítne). Jediná definice okna je {@see self::prijimaHlaseni()}.
     */
    public static function existujeUploadOkno(): bool
    {
        return static::query()
            ->whereNull('vyhodnoceno')
            ->get()
            ->contains(fn (self $kolo): bool => $kolo->prijimaHlaseni());
    }

    #[Override]
    protected function casts(): array
    {
        return [
            'datum_konani' => 'datetime',
            'datum_uzaverky' => 'datetime',
            'vyhodnoceno' => 'datetime',
        ];
    }
}
