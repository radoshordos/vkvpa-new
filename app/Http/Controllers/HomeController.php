<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\KoloStav;
use App\Models\EdiCategory;
use App\Models\Prispevek;
use App\Models\VkvpaData;
use App\Models\VkvpaKola;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(): View
    {
        $now = Carbon::now();

        // Priority: kolo s otevřeným upload oknem → nejbližší nadcházející → poslední
        $kolo = VkvpaKola::query()
            ->whereNull('vyhodnoceno')
            ->where('datum_konani', '<=', $now)
            ->where('datum_uzaverky', '>=', $now)
            ->orderBy('datum_konani')
            ->first()
            ?? VkvpaKola::query()->where('datum_konani', '>', $now)->orderBy('datum_konani')->first()
            ?? VkvpaKola::query()->orderByDesc('datum_konani')->first();

        $state = $kolo ? $this->stateKey($kolo->stav()) : null;

        $countdownTarget = ($kolo && $state) ? $this->resolveCountdownTarget($kolo, $state) : null;
        $liveMode = in_array($state, ['running', 'deadline', 'evaluating'], true);

        $kategorie = EdiCategory::query()->orderBy('id')->get()->keyBy('id');
        $vysledky = ($kolo && $liveMode)
            ? VkvpaData::prubezne($kolo->id)->get()
            : collect();

        // Poslední vyhodnocené kolo – kompaktní karta s odkazem na výsledky,
        // jen když hero ukazuje nadcházející kolo (jinak by výsledky
        // předchozího kola z úvodky mezi koly úplně zmizely).
        $posledniVyhodnocene = $state === 'upcoming'
            ? VkvpaKola::query()->whereNotNull('vyhodnoceno')->orderByDesc('datum_konani')->first()
            : null;

        // Diskuse: počet příspěvků ke kolu v hero + poslední 3 příspěvky
        // napříč koly (mezi koly je diskuse aktuálního kola prázdná).
        $diskuseCount = $kolo ? Prispevek::query()->where('kolo_id', $kolo->id)->count() : 0;
        $posledniPrispevky = Prispevek::query()
            ->with('kolo')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(3)
            ->get();

        // Next upcoming rounds (for mini-calendar), excluding the round already shown.
        $excludeId = $kolo?->id;
        $upcomingRounds = VkvpaKola::query()
            ->where('datum_konani', '>', $now)
            ->when($excludeId !== null, fn ($q) => $q->where('id', '!=', $excludeId))
            ->orderBy('datum_konani')
            ->limit(3)
            ->get();

        return view('pages.home', compact(
            'kolo', 'state', 'countdownTarget', 'liveMode',
            'vysledky', 'kategorie', 'upcomingRounds',
            'posledniVyhodnocene', 'diskuseCount', 'posledniPrispevky',
        ));
    }

    /**
     * Přemapuje stav kola na klíč používaný šablonou úvodky a odpočtem.
     * Stav je jediný zdroj pravdy ({@see VkvpaKola::stav()}), úvodka si jen
     * drží vlastní bohatší popisky a logiku odpočtu navázané na tyto klíče.
     */
    private function stateKey(KoloStav $stav): string
    {
        return match ($stav) {
            KoloStav::Nadchazejici => 'upcoming',
            KoloStav::Aktivni => 'running',
            KoloStav::Prijem => 'deadline',
            KoloStav::Uzavrene => 'evaluating',
            KoloStav::Vyhodnocene => 'evaluated',
        };
    }

    /**
     * Returns the Carbon datetime the JS countdown should count down to.
     * upcoming → start of the contest window (datum_konani)
     * running → end of the contest window
     * deadline → submission deadline
     */
    private function resolveCountdownTarget(VkvpaKola $kolo, string $state): ?Carbon
    {
        return match ($state) {
            'upcoming' => $kolo->datum_konani,
            'running' => $kolo->konecZavodu(),
            'deadline' => $kolo->datum_uzaverky,
            default => null,
        };
    }
}
