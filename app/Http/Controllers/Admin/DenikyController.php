<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

/** Administrace – Deniky (kostra, plná implementace ve Fázi 6b). */
class DenikyController extends Controller
{
    public function index(): View
    {
        return view('pages.admin.placeholder', [
            'active' => '',
            'nazev' => 'Deniky',
        ]);
    }
}
