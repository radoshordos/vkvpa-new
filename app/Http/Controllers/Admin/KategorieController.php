<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\KategorieRequest;
use App\Models\VkvpaKategorie;
use Illuminate\Http\RedirectResponse;
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

    public function store(KategorieRequest $request): RedirectResponse
    {
        VkvpaKategorie::create($request->toModel());

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

    public function update(KategorieRequest $request, VkvpaKategorie $kategorie): RedirectResponse
    {
        $kategorie->update($request->toModel());

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
