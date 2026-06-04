<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

/** Administrace – Hromadný import. */
class ImportController extends Controller
{
    public function index(): View
    {
        return view('pages.admin.placeholder', [
            'active' => '',
            'nazev' => 'Hromadný import',
            'popis' => 'Hromadný import EDI deníků ze ZIP archivu nebo adresáře. Jednotlivé deníky lze nahrát přes stránku Hlášení.',
        ]);
    }
}
