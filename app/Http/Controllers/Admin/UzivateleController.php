<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\VkvpaData;
use App\Models\VkvpaKola;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Administrace – kontaktní a osobní údaje závodníků z tabulky `vkvpa_data`.
 *
 * Citlivá data (jméno, e-mail, telefon, IP) – routa je za `admin` middleware.
 * Filtruje se volitelně podle kola (`kolo`) a fulltextově (`q` přes značku,
 * jméno, e-mail a telefon).
 */
class UzivateleController extends Controller
{
    public function index(Request $request): View
    {
        $koloId = $request->integer('kolo');
        $q = trim((string) $request->query('q', ''));

        $zaznamy = VkvpaData::query()
            ->when($koloId > 0, fn (Builder $query): Builder => $query->where('id_kola', $koloId))
            ->when($q !== '', function (Builder $query) use ($q): Builder {
                $like = '%'.$q.'%';

                return $query->where(function (Builder $sub) use ($like): void {
                    $sub->where('znacka', 'like', $like)
                        ->orWhere('jmeno', 'like', $like)
                        ->orWhere('mail', 'like', $like)
                        ->orWhere('telefon', 'like', $like);
                });
            })
            ->orderByDesc('timestamp')
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        $kola = VkvpaKola::query()->orderByDesc('datum_konani')->limit(200)->pluck('nazev', 'id');

        return view('pages.admin.uzivatele', [
            'active' => 'uzivatele.index',
            'zaznamy' => $zaznamy,
            'kola' => $kola,
            'koloId' => $koloId,
            'q' => $q,
        ]);
    }
}
