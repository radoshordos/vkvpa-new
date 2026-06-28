<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\KoloResource;
use App\Http\Resources\Api\VysledkaResource;
use App\Models\EdiCategory;
use App\Models\EdiEntry;
use App\Models\EdiRound;
use App\Services\Scoring\ScoringService;
use Illuminate\Http\JsonResponse;

/**
 * Veřejné API pro výsledky závodů VKV PA.
 * Autentizace není vyžadována – data jsou veřejná.
 * Rate limit: 60 requestů/minutu per IP (throttle:api).
 */
final class VysledkyApiController extends Controller
{
    public function __construct(private readonly ScoringService $scoring) {}

    /**
     * Seznam závodních kol (posledních 50, nejnovější první).
     *
     * GET /api/kola
     */
    public function kola(): JsonResponse
    {
        $kola = EdiRound::query()
            ->orderByDesc('starts_at')
            ->limit(50)
            ->get();

        return response()->json(['data' => KoloResource::collection($kola)]);
    }

    /**
     * Výsledková listina závodního kola (pouze schválené záznamy).
     *
     * GET /api/vysledky/{kolo}
     */
    public function kolo(EdiRound $kolo): JsonResponse
    {
        $vysledky = EdiEntry::query()
            ->where('round_id', $kolo->id)
            ->approved()
            ->with('category')
            ->orderBy('category_id')
            ->orderBy('rank')
            ->orderByDesc('points')
            ->get();

        return response()->json([
            'kolo' => new KoloResource($kolo),
            'data' => VysledkaResource::collection($vysledky),
        ]);
    }

    /**
     * Kumulativní roční výsledky – součet bodů za všechna kola roku.
     *
     * GET /api/vysledky/rocni/{rok}
     */
    public function rocni(int $rok): JsonResponse
    {
        $vysledky = $this->scoring->yearlyResults($rok);
        $kategorie = EdiCategory::nazevMap();

        $items = [];
        foreach ($vysledky as $row) {
            $items[] = [
                'znacka' => $row->callsign,
                'jmeno' => $row->name,
                'kategorie_id' => $row->kategorie_id,
                'kategorie' => $kategorie->get($row->kategorie_id, '—'),
                'celkem' => (int) $row->celkem,
            ];
        }

        return response()->json([
            'rok' => $rok,
            'data' => $items,
        ]);
    }
}
