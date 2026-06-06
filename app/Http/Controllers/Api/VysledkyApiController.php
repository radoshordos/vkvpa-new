<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\KoloResource;
use App\Http\Resources\Api\VysledkaResource;
use App\Models\VkvpaData;
use App\Models\VkvpaKategorie;
use App\Models\VkvpaKola;
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
        $kola = VkvpaKola::query()
            ->orderByDesc('datum_konani')
            ->limit(50)
            ->get();

        return response()->json(['data' => KoloResource::collection($kola)]);
    }

    /**
     * Výsledková listina závodního kola (pouze schválené záznamy).
     *
     * GET /api/vysledky/{kolo}
     */
    public function kolo(VkvpaKola $kolo): JsonResponse
    {
        $vysledky = VkvpaData::query()
            ->where('id_kola', $kolo->id)
            ->approved()
            ->with('kategorie')
            ->orderBy('id_kategorie')
            ->orderBy('poradi')
            ->orderByDesc('body')
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
        $kategorie = VkvpaKategorie::query()->pluck('nazev', 'id');

        $items = [];
        foreach ($vysledky as $row) {
            $items[] = [
                'znacka' => $row->znacka,
                'jmeno' => $row->jmeno,
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
