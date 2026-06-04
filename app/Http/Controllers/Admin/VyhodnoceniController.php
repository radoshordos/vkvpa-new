<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\VkvpaKola;
use App\Services\Scoring\ScoringService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Vyhodnocení a uzávěrka kola – admin.
 */
class VyhodnoceniController extends Controller
{
    public function __construct(private readonly ScoringService $scoring) {}

    /** Přepočítá pořadí v kole (vyhodnoceni.php). */
    public function vyhodnotit(VkvpaKola $kolo): RedirectResponse
    {
        $this->scoring->rankRound($kolo->id);

        Log::info('admin.kolo.vyhodnotit', [
            'kolo_id' => $kolo->id,
            'nazev' => $kolo->nazev,
            'admin' => Auth::user()?->name,
        ]);

        return redirect()->route('kola.index')
            ->with('announcement', 'Kolo „'.$kolo->nazev.'" vyhodnoceno.');
    }

    /** Uzavře kolo (uzavreni.php) – přepočítá pořadí a nastaví vyhodnoceno. */
    public function uzavrit(VkvpaKola $kolo): RedirectResponse
    {
        $this->scoring->rankRound($kolo->id);
        $this->scoring->closeRound($kolo->id);

        Log::info('admin.kolo.uzavrit', [
            'kolo_id' => $kolo->id,
            'nazev' => $kolo->nazev,
            'admin' => Auth::user()?->name,
        ]);

        return redirect()->route('kola.index')
            ->with('announcement', 'Kolo „'.$kolo->nazev.'" uzavřeno.');
    }
}
