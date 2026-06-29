<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\KoloStav;
use App\Services\Scoring\ScoringService;
use App\Support\ContestWindow;
use App\Support\VkvpaSettings;
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
 * @property Carbon $starts_at start závodu (datetime, standardně 3. neděle 08:00 UTC)
 * @property Carbon|null $closes_at
 * @property string $name
 * @property Carbon|null $evaluated_at
 * @property string $note
 * @property-read Collection<int, EdiEntry> $entries
 * @property-read Collection<int, DiscussionPost> $discussion
 */
#[Fillable(['starts_at', 'closes_at', 'name', 'note', 'evaluated_at'])]
#[Table(name: 'edi_rounds', key: 'id')]
#[WithoutTimestamps]
class EdiRound extends Model
{
    /** @return HasMany<EdiEntry, $this> */
    public function entries(): HasMany
    {
        return $this->hasMany(EdiEntry::class, 'round_id', 'id');
    }

    /** @return HasMany<DiscussionPost, $this> */
    public function discussion(): HasMany
    {
        return $this->hasMany(DiscussionPost::class, 'round_id', 'id');
    }

    /**
     * Scope: kola konaná v daném roce a měsíci (dle `starts_at`).
     *
     * @param  Builder<EdiRound>  $query
     * @return Builder<EdiRound>
     */
    #[Scope]
    protected function inYearMonth(Builder $query, int $year, int $month): Builder
    {
        return $query
            ->whereYear('starts_at', $year)
            ->whereMonth('starts_at', $month);
    }

    /**
     * Fáze životního cyklu kola – čistá funkce času, žádný stavový příznak:
     *  1) vyhodnocené kolo je terminální stav (`evaluated_at`),
     *  2) start závodu (`starts_at`) ještě nenastal → nadcházející,
     *  3) uzávěrka uplynula a kolo není vyhodnocené → zpracování výsledků,
     *  4) závodní okno právě běží → probíhá,
     *  5) jinak (po závodě, před uzávěrkou) → příjem hlášení.
     */
    public function state(): KoloStav
    {
        if ($this->evaluated_at !== null) {
            return KoloStav::Vyhodnocene;
        }

        $now = Carbon::now();

        if ($now->lt($this->starts_at)) {
            return KoloStav::Nadchazejici;
        }

        if ($this->closes_at === null || $now->gt($this->closes_at)) {
            return KoloStav::Uzavrene;
        }

        if ($now->lt($this->contestEnd())) {
            return KoloStav::Aktivni;
        }

        return KoloStav::Prijem;
    }

    /**
     * Konec závodního okna: start (`starts_at`) + délka závodu odvozená
     * z konfigurace {@see ContestWindow} (standardně 0800–1100 = 3 hodiny).
     * Délka se přičítá ke skutečnému startu, takže posunutý start zachová
     * trvání závodu.
     */
    public function contestEnd(): Carbon
    {
        return $this->starts_at->copy()->addMinutes(self::contestLengthMinutes());
    }

    /**
     * Délka závodního okna v minutách dle ContestWindow ('HHMM' hranice).
     */
    private static function contestLengthMinutes(): int
    {
        $minutes = static fn (string $hhmm): int => 60 * (int) substr($hhmm, 0, 2) + (int) substr($hhmm, 2, 2);

        return max(0, $minutes(ContestWindow::to()) - $minutes(ContestWindow::from()));
    }

    /**
     * Je otevřené upload okno kola? Hlášení (EDI i manuální) se od běžných
     * závodníků přijímají jen ve stavech Probíhá a Příjem hlášení – od startu
     * závodu (`starts_at`) do uzávěrky. Admin smí nahrávat kdykoliv
     * (výjimku řeší volající, ne tato metoda).
     *
     * Toto okno zároveň vymezuje dobu, kdy lze odebrat převzetí záznamu
     * („zrušit převzetí" smí admin jen mezi `starts_at` a `closes_at`).
     */
    public function acceptsReports(): bool
    {
        return in_array($this->state(), [KoloStav::Aktivni, KoloStav::Prijem], true);
    }

    /**
     * Jsou všechny záznamy kola převzaté (`approved = true`)? Prázdné kolo
     * (bez záznamů) je vakuózně „celé převzaté". Čte se vždy čerstvě z DB.
     */
    public function allEntriesTakenOver(): bool
    {
        return ! $this->entries()->where('approved', false)->exists();
    }

    /**
     * Uplynula záchranná lhůta automatického vyhodnocení (standardně 20 dní
     * od `closes_at`, viz {@see VkvpaSettings::finalizeFallbackDays()})?
     * Bez uzávěrky lhůtu nelze odvodit → false.
     */
    public function finalizeDeadlinePassed(): bool
    {
        if ($this->closes_at === null) {
            return false;
        }

        return Carbon::now()->gte(
            $this->closes_at->copy()->addDays(VkvpaSettings::finalizeFallbackDays())
        );
    }

    /**
     * Má se kolo automaticky vyhodnotit? Až po skončení příjmu hlášení
     * (stav {@see KoloStav::Uzavrene}) a zároveň když administrátor převzal
     * všechny záznamy, nebo uplynula 20denní záchranná lhůta od uzávěrky.
     * Vlastní nastavení `evaluated_at` provádí {@see ScoringService::finalizeIfDue()}.
     */
    public function shouldBeEvaluated(): bool
    {
        return $this->state() === KoloStav::Uzavrene
            && ($this->allEntriesTakenOver() || $this->finalizeDeadlinePassed());
    }

    /**
     * Kolo, pro které se zobrazují průběžné výsledky: nejstarší nevyhodnocené
     * s otevřeným upload oknem. Když žádné není, průběžné výsledky se
     * neukazují (stránka hlásí „vyhodnocování neprobíhá", položka menu
     * je skrytá).
     */
    public static function currentForStandings(): ?self
    {
        return static::query()
            ->whereNull('evaluated_at')
            ->orderBy('starts_at')
            ->get()
            ->first(fn (self $round): bool => $round->acceptsReports());
    }

    /**
     * Je otevřené upload okno alespoň jednoho kola? Řídí zobrazení stránky
     * hlášení (EDI upload i ruční formulář) – mimo okno se formuláře neukazují
     * (a POST je stejně odmítne). Jediná definice okna je {@see self::acceptsReports()}.
     */
    public static function uploadWindowExists(): bool
    {
        return static::query()
            ->whereNull('evaluated_at')
            ->get()
            ->contains(fn (self $round): bool => $round->acceptsReports());
    }

    #[Override]
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'closes_at' => 'datetime',
            'evaluated_at' => 'datetime',
        ];
    }
}
