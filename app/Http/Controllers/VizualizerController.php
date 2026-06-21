<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\QsoMode;
use App\Exceptions\EdiParseException;
use App\Services\Edi\EdiLog;
use App\Services\Edi\EdiParser;
use App\Services\Scoring\ScoringService;
use App\Support\Maidenhead;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Samostatný „EDI Visualizer" – veřejný nástroj inspirovaný původním
 * opencontest.org/edi (Vlad Melnichenko, UT4UKW). Kdokoli nahraje svůj EDI
 * deník a dostane trvalý sdílecí odkaz s mapou spojení (paprsky z domácího
 * QTH, barevné špendlíky podle druhu provozu, popup se vzdáleností/azimutem
 * a odkazem na QRZ) a souhrnem.
 *
 * Na rozdíl od běžného uploadu ({@see EdiController}) se nic nepřidává do
 * závodních tabulek – nahraný soubor se jen uloží pod náhodným tokenem do
 * `storage/app/vizualizer/{token}.edi` a při zobrazení se naparsuje znovu.
 * Geometrii dopočítá čistě {@see Maidenhead}, bez databáze.
 */
class VizualizerController extends Controller
{
    /** Adresář úložiště nahraných deníků (disk `local`). */
    private const string DIR = 'vizualizer';

    public function __construct(private readonly EdiParser $parser) {}

    /** Prázdný nahrávací formulář. */
    public function create(): View
    {
        return view('pages.vizualizer.upload', ['active' => '']);
    }

    /** Zobrazí uloženou mapu podle tokenu. */
    public function show(string $token): View|RedirectResponse
    {
        $path = self::DIR.'/'.$token.'.edi';

        if (! Storage::disk('local')->exists($path)) {
            return redirect()
                ->route('vizualizer.create')
                ->withErrors(['upload' => __('pages.vizualizer.err_not_found')]);
        }

        try {
            $log = $this->parser->parse((string) Storage::disk('local')->get($path));
        } catch (EdiParseException $e) {
            return redirect()
                ->route('vizualizer.create')
                ->withErrors(['upload' => $e->getMessage()]);
        }

        $map = $this->buildMap($log);

        return view('pages.vizualizer.show', array_merge($map, [
            'active' => '',
            'token' => $token,
        ]));
    }

    /**
     * Sestaví data mapy a souhrn z naparsovaného deníku – jen z lokátorů,
     * bez databáze. Body za spojení a násobiče počítají shodně se
     * {@see ScoringService} (z velkých čtverců).
     *
     * @return array{
     *     pcall: string, homeLoc: string, band: string,
     *     home: array{lat: float, lon: float}|null,
     *     points: list<array{lat: float, lon: float, call: string, wwl: string, mode: int, dist: int|null, azimut: int|null, points: int}>,
     *     summary: array{qso: int, avgDist: int, maxDist: int, uniqueLoc: int, uniqueSq: int}
     * }
     */
    private function buildMap(EdiLog $log): array
    {
        $homeLoc = strtoupper(trim($log->header->pWWLo()));
        $home = Maidenhead::toLatLon($homeLoc);
        $homeSq = Maidenhead::bigSquare($homeLoc);

        $points = [];
        $locators = [];
        $squares = [];
        $dists = [];

        foreach ($log->qsos as $qso) {
            $wwl = strtoupper(trim($qso->receivedWwl));
            $coord = Maidenhead::toLatLon($wwl);

            if ($coord === null) {
                continue;
            }

            $dist = $home === null ? null
                : (int) round(Maidenhead::distanceKm($home['lat'], $home['lon'], $coord['lat'], $coord['lon']));
            $azimut = $home === null ? null
                : (int) round(Maidenhead::bearingDeg($home['lat'], $home['lon'], $coord['lat'], $coord['lon']));
            $sq = Maidenhead::bigSquare($wwl);

            $points[] = [
                'lat' => $coord['lat'],
                'lon' => $coord['lon'],
                'call' => $qso->callSign,
                'wwl' => $wwl,
                'mode' => QsoMode::fromCode((int) $qso->modeCode)->value,
                'dist' => $dist,
                'azimut' => $azimut,
                'points' => Maidenhead::qsoPoints($homeSq, $sq),
            ];

            $locators[$wwl] = true;
            if (Maidenhead::isValidBigSquare($sq)) {
                $squares[$sq] = true;
            }
            if ($dist !== null) {
                $dists[] = $dist;
            }
        }

        return [
            'pcall' => $log->header->pCall(),
            'homeLoc' => $homeLoc,
            'band' => $log->header->pBand(),
            'home' => $home,
            'points' => $points,
            'summary' => [
                'qso' => count($points),
                'avgDist' => $dists === [] ? 0 : (int) round(array_sum($dists) / count($dists)),
                'maxDist' => $dists === [] ? 0 : max($dists),
                'uniqueLoc' => count($locators),
                'uniqueSq' => count($squares),
            ],
        ];
    }
}
