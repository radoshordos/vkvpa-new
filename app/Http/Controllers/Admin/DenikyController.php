<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

/** Administrace – Deníky. */
class DenikyController extends Controller
{
    public function index(): View
    {
        return view('pages.admin.placeholder', [
            'active' => '',
            'nazev' => 'Deníky',
            'popis' => 'Přehled nahraných EDI deníků. Jednotlivé deníky lze prohlížet přes výsledkovou listinu (sloupec Akce / EDI).',
        ]);
    }
}
