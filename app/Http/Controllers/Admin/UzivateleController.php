<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EdiEntry;
use App\Models\EdiRound;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Administrace – kontaktní a osobní údaje závodníků z tabulky `edi_entries`.
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

        $zaznamy = EdiEntry::query()
            ->when($koloId > 0, fn (Builder $query): Builder => $query->where('round_id', $koloId))
            ->when($q !== '', function (Builder $query) use ($q): Builder {
                $like = '%'.$q.'%';

                return $query->where(function (Builder $sub) use ($like): void {
                    $sub->where('callsign', 'like', $like)
                        ->orWhere('name', 'like', $like)
                        ->orWhere('email', 'like', $like)
                        ->orWhere('phone', 'like', $like);
                });
            })
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        $kola = EdiRound::query()->orderByDesc('starts_at')->limit(200)->pluck('name', 'id');

        return view('pages.admin.uzivatele', [
            'active' => 'uzivatele.index',
            'zaznamy' => $zaznamy,
            'kola' => $kola,
            'koloId' => $koloId,
            'q' => $q,
        ]);
    }
}
