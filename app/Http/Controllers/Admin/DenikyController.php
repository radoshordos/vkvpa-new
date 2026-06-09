<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Edihead;
use App\Models\VkvpaKola;
use Illuminate\View\View;

/** Administrace – Deníky. */
class DenikyController extends Controller
{
    public function index(): View
    {
        $deniky = Edihead::withCount('lines')
            ->orderByDesc('stamp')
            ->paginate(50);

        $kola = VkvpaKola::query()->orderByDesc('datum_konani')->limit(200)->pluck('nazev', 'id');

        return view('pages.admin.deniky', [
            'active' => 'deniky.index',
            'deniky' => $deniky,
            'kola' => $kola,
        ]);
    }
}
