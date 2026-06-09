<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\VkvpaKola;
use Illuminate\View\View;

/** Kola závodu – veřejný výpis. */
class KolaController extends Controller
{
    public function index(): View
    {
        return view('pages.kola', [
            'active' => 'kola.index',
            'isAdmin' => false,
            'kola' => VkvpaKola::query()->withCount('hlaseni')->orderByDesc('datum_konani')->get(),
        ]);
    }
}
