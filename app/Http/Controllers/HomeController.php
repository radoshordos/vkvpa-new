<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\KoloStav;
use App\Models\DiscussionPost;
use App\Models\EdiCategory;
use App\Models\EdiEntry;
use App\Models\EdiRound;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(): View
    {
        $now = Carbon::now();

        // Priority: kolo s otevřeným upload oknem → nejbližší nadcházející → poslední
        $kolo = EdiRound::query()
            ->whereNull('evaluated_at')
            ->where('starts_at', '<=', $now)
            ->where('closes_at', '>=', $now)
            ->orderBy('starts_at')
            ->first()
            ?? EdiRound::query()->where('starts_at', '>', $now)->orderBy('starts_at')->first()
            ?? EdiRound::query()->orderByDesc('starts_at')->first();

        $state = $kolo ? $this->stateKey($kolo->state()) : null;

        $countdownTarget = ($kolo && $state) ? $this->resolveCountdownTarget($kolo, $state) : null;
        $liveMode = in_array($state, ['running', 'deadline', 'evaluating'], true);

        $kategorie = EdiCategory::query()->orderBy('id')->get()->keyBy('id');
        $vysledky = ($kolo && $liveMode)
            ? EdiEntry::standings($kolo->id)->get()
            : collect();

        // Poslední vyhodnocené kolo – kompaktní karta s odkazem na výsledky,
        // jen když hero ukazuje nadcházející kolo (jinak by výsledky
        // předchozího kola z úvodky mezi koly úplně zmizely).
        $posledniVyhodnocene = $state === 'upcoming'
            ? EdiRound::query()->whereNotNull('evaluated_at')->orderByDesc('starts_at')->first()
            : null;

        // Diskuse: počet příspěvků ke kolu v hero + poslední 3 příspěvky
        // napříč koly (mezi koly je diskuse aktuálního kola prázdná).
        $diskuseCount = $kolo ? DiscussionPost::query()->where('round_id', $kolo->id)->count() : 0;
        $posledniPrispevky = DiscussionPost::query()
            ->with('round')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(3)
            ->get();

        // Next upcoming rounds (for mini-calendar), excluding the round already shown.
        $excludeId = $kolo?->id;
        $upcomingRounds = EdiRound::query()
            ->where('starts_at', '>', $now)
            ->when($excludeId !== null, fn ($q) => $q->where('id', '!=', $excludeId))
            ->orderBy('starts_at')
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
     * Stav je jediný zdroj pravdy ({@see EdiRound::stav()}), úvodka si jen
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
     * upcoming → start of the contest window (starts_at)
     * running → end of the contest window
     * deadline → submission deadline
     */
    private function resolveCountdownTarget(EdiRound $kolo, string $state): ?Carbon
    {
        return match ($state) {
            'upcoming' => $kolo->starts_at,
            'running' => $kolo->contestEnd(),
            'deadline' => $kolo->closes_at,
            default => null,
        };
    }
}
