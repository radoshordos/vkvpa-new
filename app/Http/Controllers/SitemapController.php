<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Edihead;
use App\Models\VkvpaData;
use App\Models\VkvpaKola;
use Illuminate\Http\Response;

/**
 * robots.txt a sitemap.xml generované dynamicky, aby používaly správnou doménu
 * z requestu (dev i produkce) – statický soubor by musel mít zadrátovaný host.
 */
class SitemapController extends Controller
{
    public function robots(): Response
    {
        $lines = [
            'User-agent: *',
            'Disallow: /admin',
            'Disallow: /adminer',
            'Disallow: /login',
            '',
            'Sitemap: '.url('/sitemap.xml'),
            '',
        ];

        return response(implode("\n", $lines), 200)
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    public function sitemap(): Response
    {
        /** @var list<array{loc: string, lastmod: ?string, priority: string}> $urls */
        $urls = [];

        // Statické veřejné rozcestníky.
        $urls[] = ['loc' => route('home'), 'lastmod' => null, 'priority' => '1.0'];
        $urls[] = ['loc' => route('hlaseni.index'), 'lastmod' => null, 'priority' => '0.8'];
        $urls[] = ['loc' => route('pribezne_vysledky'), 'lastmod' => null, 'priority' => '0.6'];
        $urls[] = ['loc' => route('rocni_vysledky'), 'lastmod' => null, 'priority' => '0.7'];
        $urls[] = ['loc' => route('statistiky.index'), 'lastmod' => null, 'priority' => '0.6'];
        $urls[] = ['loc' => route('diskuse.index'), 'lastmod' => null, 'priority' => '0.5'];

        $kola = VkvpaKola::query()
            ->orderByDesc('datum_konani')
            ->get(['id', 'datum_konani', 'datum_uzaverky', 'vyhodnoceno']);

        foreach ($kola as $kolo) {
            // Výsledková listina jen u uzavřených kol (= kanonická adresa kola);
            // u otevřených by ukazovala neúplná, měnící se data.
            if ($kolo->datum_uzaverky !== null && $kolo->datum_uzaverky->isPast()) {
                $urls[] = [
                    'loc' => route('vysledkova_listina', ['kolo' => $kolo->id]),
                    'lastmod' => ($kolo->vyhodnoceno ?? $kolo->datum_uzaverky)->toAtomString(),
                    'priority' => '0.7',
                ];
            }

            // Statistiky kola – jen vyhodnocená (detail je jinak 404), bohatý
            // unikátní obsah (mapy, grafy, rekordy).
            if ($kolo->vyhodnoceno !== null) {
                $urls[] = [
                    'loc' => route('statistiky.kolo', ['kolo' => $kolo->id]),
                    'lastmod' => $kolo->vyhodnoceno->toAtomString(),
                    'priority' => '0.6',
                ];
            }

            // Diskuse jednotlivých kol – unikátní obsah, čistá URL (canonical sedí).
            $urls[] = [
                'loc' => route('diskuse.show', $kolo->id),
                'lastmod' => $kolo->datum_konani->toAtomString(),
                'priority' => '0.4',
            ];
        }

        // Veřejné vizualizace a porovnání jednotlivých deníků z uzavřených kol –
        // nejbohatší unikátní obsah (mapa, grafy a statistiky stanice). Vydáváme
        // až po uzávěrce, aby se během příjmu nezveřejňovaly deníky soupeřů.
        $uzavrenaKolaIds = $kola
            ->filter(fn (VkvpaKola $k): bool => $k->datum_uzaverky !== null && $k->datum_uzaverky->isPast())
            ->pluck('id');

        if ($uzavrenaKolaIds->isNotEmpty()) {
            Edihead::query()
                ->whereIn('id_kola', $uzavrenaKolaIds)
                ->orderByDesc('id')
                ->get(['id'])
                ->each(function (Edihead $head) use (&$urls): void {
                    $urls[] = ['loc' => route('edi.vizualizace', $head->id), 'lastmod' => null, 'priority' => '0.5'];
                    $urls[] = ['loc' => route('edi.porovnani', $head->id), 'lastmod' => null, 'priority' => '0.3'];
                });
        }

        // Profily stanic – jedna URL na značku se záznamem ve vyhodnoceném kole
        // (jen alfanumerické značky, shodně s routou statistiky.stanice).
        $znacky = VkvpaData::query()
            ->join('vkvpa_kola', 'vkvpa_data.id_kola', '=', 'vkvpa_kola.id')
            ->where('vkvpa_data.schvaleno', true)
            ->whereNotNull('vkvpa_kola.vyhodnoceno')
            ->distinct()
            ->pluck('vkvpa_data.znacka');

        foreach ($znacky as $znacka) {
            if (! is_string($znacka) || preg_match('/^[A-Za-z0-9]+$/', $znacka) !== 1) {
                continue;
            }
            $urls[] = ['loc' => route('statistiky.stanice', ['znacka' => $znacka]), 'lastmod' => null, 'priority' => '0.4'];
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
        foreach ($urls as $u) {
            $xml .= '  <url>'."\n";
            $xml .= '    <loc>'.htmlspecialchars($u['loc'], ENT_XML1).'</loc>'."\n";
            if ($u['lastmod'] !== null) {
                $xml .= '    <lastmod>'.$u['lastmod'].'</lastmod>'."\n";
            }
            $xml .= '    <priority>'.$u['priority'].'</priority>'."\n";
            $xml .= '  </url>'."\n";
        }
        $xml .= '</urlset>'."\n";

        return response($xml, 200)
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
