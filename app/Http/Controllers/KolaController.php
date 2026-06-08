<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\VkvpaKola;
use App\Support\IcalFeed;
use Illuminate\Http\Response;
use Illuminate\View\View;

/** Kola závodu – veřejný výpis. */
class KolaController extends Controller
{
    public function index(): View
    {
        return view('pages.kola', [
            'active'  => 'kola.index',
            'isAdmin' => false,
            'kola'    => VkvpaKola::query()->withCount('hlaseni')->orderByDesc('datum_konani')->get(),
        ]);
    }

    /** iCalendar feed termínů kol (.ics) k přidání do kalendáře. */
    public function ical(): Response
    {
        $kola = VkvpaKola::query()->orderBy('datum_konani')->get();

        return response(IcalFeed::build($kola), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="vkvpa-kola.ics"',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
