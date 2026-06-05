<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StorePrispevekRequest;
use App\Models\Prispevek;
use App\Models\VkvpaKola;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class DiskuseController extends Controller
{
    public function index(\Illuminate\Http\Request $request): RedirectResponse
    {
        $id = $request->integer('kolo', 0);

        $kolo = ($id > 0 ? VkvpaKola::query()->find($id) : null)
            ?? VkvpaKola::query()->orderByDesc('datum_konani')->first();

        if (! $kolo) {
            return redirect()->route('kola.index');
        }

        return redirect()->route('diskuse.show', $kolo->id);
    }

    public function show(VkvpaKola $kolo): View
    {
        $prispevky = Prispevek::where('kolo_id', $kolo->id)
            ->orderBy('created_at')
            ->get();

        $kola = VkvpaKola::query()->orderByDesc('datum_konani')->get();

        return view('pages.diskuse', [
            'active' => 'diskuse.index',
            'kolo' => $kolo,
            'prispevky' => $prispevky,
            'kola' => $kola,
        ]);
    }

    public function store(StorePrispevekRequest $request, VkvpaKola $kolo): RedirectResponse
    {
        $foto = null;
        if ($request->hasFile('foto') && $request->file('foto')?->isValid()) {
            $file = $request->file('foto');
            $nazev = time().'_'.$request->string('znacka')->value().'.'.$file->getClientOriginalExtension();
            $foto = $file->storeAs('diskuse/'.$kolo->id, $nazev, 'public');
        }

        Prispevek::create([
            'kolo_id' => $kolo->id,
            'znacka' => $request->string('znacka')->value(),
            'jmeno' => $request->filled('jmeno') ? $request->string('jmeno')->trim()->value() : null,
            'text' => $request->string('text')->trim()->value(),
            'foto' => $foto,
            'ip' => $request->ip(),
        ]);

        return redirect()
            ->route('diskuse.show', $kolo->id)
            ->with('success', 'Příspěvek byl přidán.');
    }

    public function destroy(Prispevek $prispevek): RedirectResponse
    {
        $koloId = $prispevek->kolo_id;

        if ($prispevek->foto) {
            Storage::disk('public')->delete($prispevek->foto);
        }

        $prispevek->delete();

        return redirect()
            ->route('diskuse.show', $koloId)
            ->with('success', 'Příspěvek byl smazán.');
    }
}
