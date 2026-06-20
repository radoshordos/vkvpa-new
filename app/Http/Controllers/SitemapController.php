<?php

declare(strict_types=1);

namespace App\Http\Controllers;

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
        $urls[] = ['loc' => route('vysledkova_listina'), 'lastmod' => null, 'priority' => '0.8'];
        $urls[] = ['loc' => route('pribezne_vysledky'), 'lastmod' => null, 'priority' => '0.6'];
        $urls[] = ['loc' => route('rocni_vysledky'), 'lastmod' => null, 'priority' => '0.7'];
        $urls[] = ['loc' => route('diskuse.index'), 'lastmod' => null, 'priority' => '0.5'];

        // Diskuse jednotlivých kol – unikátní obsah, čistá URL (canonical sedí).
        VkvpaKola::query()
            ->orderByDesc('datum_konani')
            ->get(['id', 'datum_konani'])
            ->each(function (VkvpaKola $kolo) use (&$urls): void {
                $urls[] = [
                    'loc' => route('diskuse.show', $kolo->id),
                    'lastmod' => $kolo->datum_konani->toAtomString(),
                    'priority' => '0.4',
                ];
            });

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
