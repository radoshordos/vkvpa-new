<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\KoloStav;
use App\Models\EdiEntry;
use App\Models\EdiRound;
use App\Services\Edi\KoloStatistiky;
use App\Services\Scoring\RekordyService;
use App\Services\Scoring\StaniceProfil;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\View\View;

/**
 * Veřejné statistiky kol: rozcestník vyhodnocených kol (+ all-time síň slávy)
 * a detail jednoho kola (souhrn, mapy, grafy, TOP žebříčky, trend, zajímavosti).
 * Agregaci dodává {@see KoloStatistiky}, rekordy {@see RekordyService};
 * zveřejňují se jen vyhodnocená kola.
 */
final class StatistikyController extends Controller
{
    public function __construct(
        private readonly KoloStatistiky $statistiky,
        private readonly RekordyService $rekordy,
        private readonly StaniceProfil $staniceProfil,
    ) {}

    public function index(): View
    {
        // „Účastníci" = počet unikátních značek kola (shodně s detailem i síní
        // slávy); jedna značka může mít víc záznamů (kategorií), proto distinct.
        $kola = EdiRound::query()
            ->whereNotNull('evaluated_at')
            ->whereHas('entries', fn (Builder $query): Builder => $query->where('approved', true))
            ->select(['id', 'name', 'starts_at', 'evaluated_at'])
            ->selectSub(
                EdiEntry::query()
                    ->whereColumn('round_id', 'edi_rounds.id')
                    ->where('approved', true)
                    ->selectRaw('COUNT(DISTINCT callsign)'),
                'ucastniku',
            )
            ->selectSub(
                EdiEntry::query()
                    ->whereColumn('round_id', 'edi_rounds.id')
                    ->where('approved', true)
                    ->selectRaw('COUNT(*)'),
                'zaznamu',
            )
            ->orderByDesc('starts_at')
            ->get();

        /** @var SupportCollection<int, SupportCollection<int, EdiRound>> $kolaPodleRoku */
        $kolaPodleRoku = $kola->groupBy(fn (EdiRound $kolo): int => (int) $kolo->starts_at->year);
        $rocniSouhrny = $kolaPodleRoku
            ->map(fn (SupportCollection $rocniKola): array => $this->rocniSouhrn($rocniKola))
            ->all();

        return view('pages.statistiky.index', [
            'active' => 'statistiky.index',
            'kola' => $kola,
            'kolaPodleRoku' => $kolaPodleRoku,
            'rocniSouhrny' => $rocniSouhrny,
            'rekordy' => $this->rekordy->vrcholy(),
            'odxAllTime' => $this->rekordy->odxAllTime(),
        ]);
    }

    public function kolo(EdiRound $kolo): View
    {
        // Veřejně jen vyhodnocená kola – u rozpracovaných by se zveřejňovala
        // neúplná, měnící se data (a deníky soupeřů během příjmu hlášení).
        abort_unless($kolo->state() === KoloStav::Vyhodnocene, 404);

        return view('pages.statistiky.kolo', [
            'active' => 'statistiky.index',
            'kolo' => $kolo,
            'prehled' => $this->statistiky->prehled($kolo),
        ]);
    }

    public function stanice(string $znacka): View
    {
        $profil = $this->staniceProfil->profil($znacka);
        abort_if($profil === null, 404);

        return view('pages.statistiky.stanice', [
            'active' => 'statistiky.index',
            'profil' => $profil,
        ]);
    }

    /**
     * @param  SupportCollection<int, EdiRound>  $kola
     * @return array{pocetKol: int, prumerUcast: int, maxUcast: int, minUcast: int, zaznamu: int}
     */
    private function rocniSouhrn(SupportCollection $kola): array
    {
        $ucasti = $kola->map(fn (EdiRound $kolo): int => self::intAttr($kolo, 'ucastniku'));
        $zaznamy = $kola->map(fn (EdiRound $kolo): int => self::intAttr($kolo, 'zaznamu'));

        return [
            'pocetKol' => $kola->count(),
            'prumerUcast' => self::roundedInt($ucasti->avg()),
            'maxUcast' => self::intValue($ucasti->max()),
            'minUcast' => self::intValue($ucasti->min()),
            'zaznamu' => self::intValue($zaznamy->sum()),
        ];
    }

    private static function intAttr(EdiRound $kolo, string $key): int
    {
        $value = $kolo->getAttribute($key);

        return self::intValue($value);
    }

    private static function intValue(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private static function roundedInt(mixed $value): int
    {
        return is_numeric($value) ? (int) round((float) $value) : 0;
    }
}
