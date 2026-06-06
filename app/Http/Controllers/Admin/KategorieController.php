<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\VkvpaKategorie;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
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
            'dxid' => ['required', 'integer', 'min:0'], // 0 = tuzemská; nenulové = id odpovídající tuzemské kategorie
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

    public function edit(VkvpaKategorie $kategorie): View
    {
        return view('pages.admin.kategorie', [
            'active' => 'kategorie.index',
            'kategorie' => VkvpaKategorie::query()->orderBy('id')->get(),
            'editKategorie' => $kategorie,
        ]);
    }

    public function update(Request $request, VkvpaKategorie $kategorie): RedirectResponse
    {
        $request->validate([
            'nazev' => ['required', 'string', 'max:50'],
            'popis' => ['nullable', 'string', 'max:250'],
            'zkratka' => ['required', 'string', 'max:20'],
            'dxid' => ['required', 'integer', 'min:0'],
        ]);

        $kategorie->update([
            'nazev' => $request->string('nazev')->value(),
            'popis' => $request->string('popis')->value(),
            'zkratka' => $request->string('zkratka')->value(),
            'dxid' => $request->integer('dxid'),
        ]);

        Log::info('admin.kategorie.update', [
            'kategorie_id' => $kategorie->id,
            'nazev' => $kategorie->nazev,
            'admin' => Auth::user()?->name,
        ]);

        return redirect()
            ->route('kategorie.index')
            ->with('announcement', 'Kategorie „'.$kategorie->nazev.'" byla aktualizována.');
    }
}
