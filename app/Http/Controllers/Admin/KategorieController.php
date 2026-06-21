<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\KategorieRequest;
use App\Models\VkvpaKategorie;
use App\Support\AdminLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/** Administrace – Kategorie. */
class KategorieController extends Controller
{
    public function index(): View
    {
        $kategorie = VkvpaKategorie::query()
            ->withCount('hlaseni')
            ->orderBy('id')
            ->get();

        return view('pages.admin.kategorie', [
            'active' => 'kategorie.index',
            'kategorie' => $kategorie,
        ]);
    }

    public function create(): View
    {
        return view('pages.admin.kategorie-create', [
            'active' => 'kategorie.index',
        ]);
    }

    public function store(KategorieRequest $request): RedirectResponse
    {
        VkvpaKategorie::create($request->toModel());

        return redirect()
            ->route('kategorie.index')
            ->with('announcement', 'Kategorie byla přidána.');
    }

    public function edit(VkvpaKategorie $kategorie): View
    {
        return view('pages.admin.kategorie-edit', [
            'active' => 'kategorie.index',
            'editKategorie' => $kategorie,
        ]);
    }

    public function update(KategorieRequest $request, VkvpaKategorie $kategorie): RedirectResponse
    {
        $kategorie->update($request->toModel());

        AdminLogger::log('admin.kategorie.update', [
            'kategorie_id' => $kategorie->id,
            'nazev' => $kategorie->nazev,
        ]);

        return redirect()
            ->route('kategorie.index')
            ->with('announcement', 'Kategorie „'.$kategorie->nazev.'" byla aktualizována.');
    }
}
