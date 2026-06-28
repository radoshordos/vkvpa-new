<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\KoloRequest;
use App\Models\EdiRound;
use App\Services\Scoring\ScoringService;
use App\Support\AdminLogger;
use App\Support\ContestCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Admin CRUD pro kola závodu.
 */
class KolaAdminController extends Controller
{
    public function index(): View
    {
        return view('pages.kola', [
            'active' => 'kola.admin.index',
            'isAdmin' => true,
            'kola' => EdiRound::query()->withCount('entries')->orderByDesc('starts_at')->get(),
        ]);
    }

    public function create(): View
    {
        return view('pages.admin.kolo-form', [
            'active' => 'kola.admin.index',
            'kolo' => null,
            'suggested' => $this->nextSuggestedRound(),
        ]);
    }

    /**
     * Vrátí předvyplněné hodnoty pro nejbližší měsíc, pro který ještě kolo neexistuje.
     *
     * @return array{name: string, starts_at: string, closes_at: string}
     */
    private function nextSuggestedRound(): array
    {
        $now = CarbonImmutable::now('UTC');

        for ($i = 0; $i < 24; $i++) {
            $target = $now->addMonths($i);
            $year = (int) $target->format('Y');
            $month = (int) $target->format('n');

            $exists = EdiRound::query()->inYearMonth($year, $month)->exists();

            if (! $exists) {
                $start = ContestCalendar::roundStart($year, $month);

                return [
                    'name' => ContestCalendar::roundName($year, $month),
                    'starts_at' => $start->format('Y-m-d\TH:i'),
                    'closes_at' => ContestCalendar::uploadDeadline($start)->format('Y-m-d\TH:i'),
                ];
            }
        }

        // Záložní hodnota: příští měsíc.
        $next = $now->addMonth();
        $year = (int) $next->format('Y');
        $month = (int) $next->format('n');
        $start = ContestCalendar::roundStart($year, $month);

        return [
            'name' => ContestCalendar::roundName($year, $month),
            'starts_at' => $start->format('Y-m-d\TH:i'),
            'closes_at' => ContestCalendar::uploadDeadline($start)->format('Y-m-d\TH:i'),
        ];
    }

    public function store(KoloRequest $request): RedirectResponse
    {
        $kolo = EdiRound::create($request->toModel());

        AdminLogger::log('admin.kolo.create', [
            'round_id' => $kolo->id,
            'name' => $kolo->name,
        ]);

        return redirect()
            ->route('kola.admin.index')
            ->with('announcement', 'Kolo „'.$kolo->name.'" bylo vytvořeno.');
    }

    public function edit(EdiRound $kolo): View
    {
        return view('pages.admin.kolo-form', [
            'active' => 'kola.admin.index',
            'kolo' => $kolo,
            'suggested' => [],
        ]);
    }

    public function update(KoloRequest $request, EdiRound $kolo, ScoringService $scoring): RedirectResponse
    {
        $puvodniRok = $kolo->starts_at->year;
        $kolo->update($request->toModel());

        // Roční výsledky se agregují podle roku `starts_at` – přesun kola
        // do jiného roku musí shodit cache obou dotčených let.
        if ($kolo->starts_at->year !== $puvodniRok) {
            $scoring->forgetYearlyCache($puvodniRok);
            $scoring->forgetYearlyCache($kolo->starts_at->year);
        }

        AdminLogger::log('admin.kolo.update', [
            'round_id' => $kolo->id,
            'name' => $kolo->name,
        ]);

        return redirect()
            ->route('kola.admin.index')
            ->with('announcement', 'Kolo „'.$kolo->name.'" bylo aktualizováno.');
    }
}
