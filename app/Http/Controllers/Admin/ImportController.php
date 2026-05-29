<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

/** Administrace – Import (kostra, plná implementace ve Fázi 6b). */
class ImportController extends Controller
{
    public function index(): View
    {
        return view('pages.admin.placeholder', [
            'active' => '',
            'nazev' => 'Import',
        ]);
    }
}
