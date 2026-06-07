<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\VkvpaKola;
use App\Support\ContestCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * Admin CRUD pro kola závodu.
 */
class KolaAdminController extends Controller
{
    public function create(): View
    {
        return view('pages.admin.kolo-form', [
            'active' => 'kola.index',
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

    public function store(Request $request): RedirectResponse
    {
        $v = $this->formData($request);

        $kolo = VkvpaKola::create($v);

        Log::info('admin.kolo.create', [
            'kolo_id' => $kolo->id,
            'nazev' => $kolo->nazev,
            'admin' => Auth::user()?->name,
        ]);

        return redirect()
            ->route('kola.index')
            ->with('announcement', 'Kolo „'.$kolo->nazev.'" bylo vytvořeno.');
    }

    public function edit(VkvpaKola $kolo): View
    {
        return view('pages.admin.kolo-form', [
            'active' => 'kola.index',
            'kolo' => $kolo,
            'suggested' => [],
        ]);
    }

    public function update(Request $request, VkvpaKola $kolo): RedirectResponse
    {
        $v = $this->formData($request);

        $kolo->update($v);

        Log::info('admin.kolo.update', [
            'kolo_id' => $kolo->id,
            'nazev' => $kolo->nazev,
            'admin' => Auth::user()?->name,
        ]);

        return redirect()
            ->route('kola.index')
            ->with('announcement', 'Kolo „'.$kolo->nazev.'" bylo aktualizováno.');
    }

    /** @return array<string, mixed> */
    private function formData(Request $request): array
    {
        $request->validate([
            'nazev' => ['required', 'string', 'max:250'],
            'datum_konani' => ['required', 'date'],
            'datum_uzaverky' => ['required', 'date'],
            'aktivni' => ['boolean'],
            'poznamka' => ['nullable', 'string', 'max:250'],
        ]);

        return [
            'nazev' => $request->string('nazev')->value(),
            'datum_konani' => $request->string('datum_konani')->value(),
            'datum_uzaverky' => $request->string('datum_uzaverky')->value(),
            'aktivni' => $request->boolean('aktivni'),
            // poznamka je v DB NOT NULL – string() vrátí prázdný řetězec místo null.
            'poznamka' => $request->string('poznamka')->value(),
        ];
    }
}
