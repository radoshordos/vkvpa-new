<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\VkvpaData;
use App\Models\VkvpaKategorie;
use App\Models\VkvpaKola;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(): View
    {
        $now = Carbon::now();

        // Priority: active → next upcoming → most recent
        $kolo = VkvpaKola::query()->where('aktivni', true)->orderByDesc('datum_konani')->first()
            ?? VkvpaKola::query()->where('datum_konani', '>', $now->toDateString())->orderBy('datum_konani')->first()
            ?? VkvpaKola::query()->orderByDesc('datum_konani')->first();

        $state = $kolo ? $this->resolveState($kolo, $now) : null;
        $countdownTarget = ($kolo && $state) ? $this->resolveCountdownTarget($kolo, $state) : null;
        $liveMode = in_array($state, ['active', 'deadline', 'evaluating'], true);

        $kategorie = VkvpaKategorie::query()->orderBy('id')->get()->keyBy('id');
        $vysledky = ($kolo && $liveMode)
            ? VkvpaData::prubezne($kolo->id)->get()
            : collect();

        // Next upcoming rounds (for mini-calendar), excluding the round already shown.
        $excludeId = $kolo?->id;
        $upcomingRounds = VkvpaKola::query()
            ->where('datum_konani', '>', $now->toDateString())
            ->when($excludeId !== null, fn ($q) => $q->where('id', '!=', $excludeId))
            ->orderBy('datum_konani')
            ->limit(3)
            ->get();

        return view('pages.home', compact(
            'kolo', 'state', 'countdownTarget', 'liveMode',
            'vysledky', 'kategorie', 'upcomingRounds',
        ));
    }

    private function resolveState(VkvpaKola $kolo, Carbon $now): string
    {
        if ($kolo->aktivni) {
            return 'active';
        }

        if ($kolo->datum_konani->isAfter($now->copy()->endOfDay())) {
            return 'upcoming';
        }

        if ($kolo->datum_uzaverky !== null && $kolo->datum_uzaverky->isAfter($now)) {
            return 'deadline';
        }

        if ($kolo->vyhodnoceno === null) {
            return 'evaluating';
        }

        return 'evaluated';
    }

    /**
     * Returns the Carbon datetime the JS countdown should count down to.
     * upcoming → contest day at 08:00 UTC (start of contest window)
     * active / deadline → submission deadline
     */
    private function resolveCountdownTarget(VkvpaKola $kolo, string $state): ?Carbon
    {
        return match ($state) {
            'upcoming' => $kolo->datum_konani->copy()->setTime(8, 0, 0)->setTimezone('UTC'),
            'active', 'deadline' => $kolo->datum_uzaverky,
            default => null,
        };
    }
}
