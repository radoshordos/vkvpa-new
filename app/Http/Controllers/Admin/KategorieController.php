<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\VkvpaKategorie;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Administrace – Kategorie. */
class KategorieController extends Controller
{
    public function index(): View
    {
        $kategorie = VkvpaKategorie::query()->orderBy('id')->get();

        return view('pages.admin.kategorie', [
            'active' => 'kategorie.index',
            'kategorie' => $kategorie,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'nazev' => ['required', 'string', 'max:50'],
            'popis' => ['nullable', 'string', 'max:250'],
            'zkratka' => ['required', 'string', 'max:20'],
            'dxid' => ['required', 'integer', 'min:0'], // TODO: clarify dxid semantics
        ]);

        VkvpaKategorie::create([
            'nazev' => $request->string('nazev')->value(),
            'popis' => $request->string('popis')->value(),
            'zkratka' => $request->string('zkratka')->value(),
            'dxid' => $request->integer('dxid'),
        ]);

        return redirect()
            ->route('kategorie.index')
            ->with('announcement', 'Kategorie byla přidána.');
    }
}
