<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\KoloRequest;
use App\Models\VkvpaKola;
use App\Support\ContestCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
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
            'kola' => VkvpaKola::query()->withCount('hlaseni')->orderByDesc('datum_konani')->get(),
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
     * @return array{nazev: string, datum_konani: string, datum_uzaverky: string}
     */
    private function nextSuggestedRound(): array
    {
        $now = CarbonImmutable::now('UTC');

        for ($i = 0; $i < 24; $i++) {
            $target = $now->addMonths($i);
            $year = (int) $target->format('Y');
            $month = (int) $target->format('n');

            $exists = VkvpaKola::query()
                ->whereYear('datum_konani', $year)
                ->whereMonth('datum_konani', $month)
                ->exists();

            if (! $exists) {
                $start = ContestCalendar::roundStart($year, $month);

                return [
                    'nazev' => ContestCalendar::roundName($year, $month),
                    'datum_konani' => $start->toDateString(),
                    'datum_uzaverky' => ContestCalendar::uploadDeadline($start)->format('Y-m-d\TH:i'),
                ];
            }
        }

        // Záložní hodnota: příští měsíc.
        $next = $now->addMonth();
        $year = (int) $next->format('Y');
        $month = (int) $next->format('n');
        $start = ContestCalendar::roundStart($year, $month);

        return [
            'nazev' => ContestCalendar::roundName($year, $month),
            'datum_konani' => $start->toDateString(),
            'datum_uzaverky' => ContestCalendar::uploadDeadline($start)->format('Y-m-d\TH:i'),
        ];
    }

    public function store(KoloRequest $request): RedirectResponse
    {
        $kolo = VkvpaKola::create($request->toModel());

        Log::info('admin.kolo.create', [
            'kolo_id' => $kolo->id,
            'nazev' => $kolo->nazev,
            'admin' => Auth::user()?->name,
        ]);

        return redirect()
            ->route('kola.admin.index')
            ->with('announcement', 'Kolo „'.$kolo->nazev.'" bylo vytvořeno.');
    }

    public function edit(VkvpaKola $kolo): View
    {
        return view('pages.admin.kolo-form', [
            'active' => 'kola.admin.index',
            'kolo' => $kolo,
            'suggested' => [],
        ]);
    }

    public function update(KoloRequest $request, VkvpaKola $kolo): RedirectResponse
    {
        $kolo->update($request->toModel());

        Log::info('admin.kolo.update', [
            'kolo_id' => $kolo->id,
            'nazev' => $kolo->nazev,
            'admin' => Auth::user()?->name,
        ]);

        return redirect()
            ->route('kola.admin.index')
            ->with('announcement', 'Kolo „'.$kolo->nazev.'" bylo aktualizováno.');
    }
}
