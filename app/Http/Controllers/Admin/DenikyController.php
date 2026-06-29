<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EdiHead;
use App\Models\EdiRound;
use Illuminate\View\View;

/** Administrace – Deníky. */
class DenikyController extends Controller
{
    public function index(): View
    {
        $deniky = EdiHead::withCount('lines')
            ->orderByDesc('stamp')
            ->paginate(50);

        $kola = EdiRound::query()->orderByDesc('starts_at')->limit(200)->pluck('name', 'id');

        return view('pages.admin.deniky', [
            'active' => 'deniky.index',
            'deniky' => $deniky,
            'kola' => $kola,
        ]);
    }
}
