<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

/** Administrace – Kategorie. */
class KategorieController extends Controller
{
    public function index(): View
    {
        return view('pages.admin.placeholder', [
            'active' => '',
            'nazev' => 'Kategorie',
            'popis' => 'Správa soutěžních kategorií (pásmo, sekce, DX). Kategorie se nyní definují přímo v databázi přes tabulku vkvpa_kategorie.',
        ]);
    }
}
