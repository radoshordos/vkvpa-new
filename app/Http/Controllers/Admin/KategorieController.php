<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

/** Administrace – Kategorie (kostra, plná implementace ve Fázi 6b). */
class KategorieController extends Controller
{
    public function index(): View
    {
        return view('pages.admin.placeholder', [
            'active' => '',
            'nazev' => 'Kategorie',
        ]);
    }
}
