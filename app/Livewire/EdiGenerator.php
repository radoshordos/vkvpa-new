<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Actions\ImportEdiAction;
use App\Enums\QsoMode;
use App\Exceptions\DuplicateEdiException;
use App\Exceptions\EdiParseException;
use App\Exceptions\EmptyPCallException;
use App\Exceptions\RoundNotFoundException;
use App\Exceptions\TDateMismatchException;
use App\Exceptions\TDateNotContestDayException;
use App\Exceptions\UnknownBandException;
use App\Exceptions\UnknownSectionException;
use App\Exceptions\UploadWindowClosedException;
use App\Http\Requests\StoreHlaseniRequest;
use App\Jobs\RankRoundJob;
use App\Models\EdiRound;
use App\Rules\ValidMaidenhead;
use App\Rules\ValidPhone;
use App\Services\Edi\EdiComposer;
use App\Services\Edi\EdiHeader;
use App\Services\Edi\EdiLog;
use App\Services\Edi\EdiParser;
use App\Services\Edi\EdiQso;
use App\Services\Scoring\ScoringService;
use App\Support\Maidenhead;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Ruční generátor EDI deníku (REG1TEST) – veřejný nástroj inspirovaný
 * ok2kjt.net/edi. Závodník zapíše hlavičku a spojení ručně a v reálném čase
 * vidí složený `.edi` text, průběžné skóre (body × násobiče) a mapu spojení.
 *
 * Komponenta je zdrojem pravdy; živý náhled počítá server (skóre z {@see
 * ScoringService::scoreLog()} nad deníkem sestaveným v paměti, text z {@see
 * EdiComposer}). Hotový deník lze stáhnout, nebo – jen na 144 MHz v otevřeném
 * okně příjmu – rovnou podat jako hlášení přes {@see ImportEdiAction}.
 * Rozpracovaný stav drží JS v `localStorage` (viz resources/js/edi-generator.js).
 */
class EdiGenerator extends Component
{
    public string $tname = 'Provozní aktiv';

    public string $pcall = '';

    public string $pwwlo = '';

    /** SINGLE | MULTI */
    public string $psect = 'SINGLE';

    public string $pband = '144 MHz';

    /** Den závodu ve formátu Y-m-d (HTML date input). */
    public string $tdate = '';

    public string $rname = '';

    public string $rphon = '';

    public string $rhbbs = '';

    public string $spowe = '';

    public string $stxeq = '';

    public string $sante = '';

    public string $remarks = '';

    /**
     * Spojení. Každý řádek: time(HHMM), call, mode(1=SSB,2=CW), rst_s, rst_r, wwl.
     * Pořadová čísla se přidělují automaticky podle pořadí řádku.
     *
     * @var array<int, array{time: string, call: string, mode: int, rst_s: string, rst_r: string, wwl: string}>
     */
    public array $qsos = [];

    public string $errorMessage = '';

    public function mount(): void
    {
        // Předvyplníme den aktuálního průběžného kola (nejčastější případ).
        $aktualni = EdiRound::currentForStandings();
        if ($aktualni !== null) {
            $this->tdate = $aktualni->starts_at->format('Y-m-d');
        }

        $this->qsos = [$this->prazdneQso()];
    }

    /** @return array{time: string, call: string, mode: int, rst_s: string, rst_r: string, wwl: string} */
    private function prazdneQso(): array
    {
        return ['time' => '', 'call' => '', 'mode' => 2, 'rst_s' => '59', 'rst_r' => '59', 'wwl' => ''];
    }

    public function addQso(): void
    {
        $this->qsos[] = $this->prazdneQso();
    }

    public function removeQso(int $index): void
    {
        unset($this->qsos[$index]);
        $this->qsos = array_values($this->qsos);

        if ($this->qsos === []) {
            $this->qsos = [$this->prazdneQso()];
        }
    }

    /**
     * Obnoví rozpracovaný stav z localStorage (volá JS jen na čisté stránce).
     * Bere jen známé skalární/maticové klíče – cizí pole se ignorují.
     *
     * @param  array<string, mixed>  $state
     */
    public function restoreState(array $state): void
    {
        $str = static fn (string $key): string => isset($state[$key]) && is_scalar($state[$key]) ? (string) $state[$key] : '';

        $this->tname = $str('tname') !== '' ? $str('tname') : $this->tname;
        $this->pcall = $str('pcall');
        $this->pwwlo = $str('pwwlo');
        $this->psect = $str('psect') !== '' ? $str('psect') : $this->psect;
        $this->pband = $str('pband') !== '' ? $str('pband') : $this->pband;
        $this->tdate = $str('tdate') !== '' ? $str('tdate') : $this->tdate;
        $this->rname = $str('rname');
        $this->rphon = $str('rphon');
        $this->rhbbs = $str('rhbbs');
        $this->spowe = $str('spowe');
        $this->stxeq = $str('stxeq');
        $this->sante = $str('sante');
        $this->remarks = $str('remarks');

        if (isset($state['qsos']) && is_array($state['qsos'])) {
            $rows = [];
            foreach ($state['qsos'] as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $rows[] = [
                    'time' => $this->rowStr($row, 'time', ''),
                    'call' => $this->rowStr($row, 'call', ''),
                    'mode' => $this->rowInt($row, 'mode', 2),
                    'rst_s' => $this->rowStr($row, 'rst_s', '59'),
                    'rst_r' => $this->rowStr($row, 'rst_r', '59'),
                    'wwl' => $this->rowStr($row, 'wwl', ''),
                ];
            }
            $this->qsos = $rows === [] ? [$this->prazdneQso()] : $rows;
        }
    }

    /**
     * Skalární hodnota řádku jako řetězec, jinak default.
     *
     * @param  array<array-key, mixed>  $row
     */
    private function rowStr(array $row, string $key, string $default): string
    {
        $value = $row[$key] ?? null;

        return is_scalar($value) ? (string) $value : $default;
    }

    /**
     * Číselná hodnota řádku jako int, jinak default.
     *
     * @param  array<array-key, mixed>  $row
     */
    private function rowInt(array $row, string $key, int $default): int
    {
        $value = $row[$key] ?? null;

        return is_numeric($value) ? (int) $value : $default;
    }

    /**
     * Hlavička pro EdiComposer / EdiHeader z aktuálního stavu.
     *
     * @return array<string, string>
     */
    private function headerArray(): array
    {
        return [
            'tname' => $this->tname,
            'tdate' => $this->tdate,
            'pcall' => $this->pcall,
            'pwwlo' => $this->pwwlo,
            'psect' => $this->psect,
            'pband' => $this->pband,
            'rname' => $this->rname,
            'rphon' => $this->rphon,
            'rhbbs' => $this->rhbbs,
            'spowe' => $this->spowe,
            'stxeq' => $this->stxeq,
            'sante' => $this->sante,
            'remarks' => $this->remarks,
        ];
    }

    /**
     * QSO řádky doplněné o datum (z TDate) – vstup pro EdiComposer.
     *
     * @return list<array<string, mixed>>
     */
    private function qsoRows(): array
    {
        $date = $this->tdate !== '' ? str_replace('-', '', $this->tdate) : '';
        $date = $date !== '' ? substr($date, 2) : '';

        $rows = [];
        foreach ($this->qsos as $q) {
            $rows[] = [
                'date' => $date,
                'time' => (string) $q['time'],
                'call' => (string) $q['call'],
                'mode' => (int) $q['mode'],
                'rst_s' => (string) $q['rst_s'],
                'rst_r' => (string) $q['rst_r'],
                'wwl' => (string) $q['wwl'],
            ];
        }

        return $rows;
    }

    /** Sestaví EdiLog v paměti pro skórování (jen kompletní řádky). */
    private function buildLog(): EdiLog
    {
        $header = new EdiHeader([
            'PCall' => strtoupper(trim($this->pcall)),
            'PWWLo' => strtoupper(trim($this->pwwlo)),
            'TDate' => $this->tdate !== '' ? str_replace('-', '', $this->tdate) : '',
            'PBand' => $this->pband,
            'PSect' => $this->psect,
        ]);

        $date = $this->tdate !== '' ? substr(str_replace('-', '', $this->tdate), 2) : '';

        $qsos = [];
        $counter = 0;
        foreach ($this->qsos as $q) {
            $call = strtoupper(trim((string) $q['call']));
            $wwl = strtoupper(trim((string) $q['wwl']));
            if ($call === '' || $wwl === '') {
                continue;
            }
            $counter++;
            $nr = str_pad((string) $counter, 3, '0', STR_PAD_LEFT);
            $qsos[] = new EdiQso(
                date: $date,
                time: preg_replace('/[^0-9]/', '', (string) $q['time']) ?? '',
                callSign: $call,
                modeCode: (string) (int) $q['mode'],
                sentRst: (string) $q['rst_s'],
                sentQsoNumber: $nr,
                receivedRst: (string) $q['rst_r'],
                receivedQsoNumber: $nr,
                receivedExchange: '',
                receivedWwl: $wwl,
                qsoPoints: '0',
                newExchange: '',
                newWwl: '',
                newDxcc: '',
                duplicate: '',
            );
        }

        return new EdiLog($header, $qsos, '', $counter);
    }

    /**
     * Body mapy z kompletních spojení (paprsky z domácího QTH). Počítá se
     * čistě z lokátorů – stejně jako VizualizerController::buildMap().
     *
     * @param  array{lat: float, lon: float}|null  $home
     * @return list<array{lat: float, lon: float, call: string, wwl: string, mode: int, dist: int|null, azimut: int|null, points: int}>
     */
    private function mapPoints(?array $home, string $homeSq): array
    {
        $points = [];
        foreach ($this->qsos as $q) {
            $wwl = strtoupper(trim((string) $q['wwl']));
            $call = strtoupper(trim((string) $q['call']));
            $coord = Maidenhead::toLatLon($wwl);
            if ($call === '' || $coord === null) {
                continue;
            }

            $dist = $home === null ? null
                : (int) round(Maidenhead::distanceKm($home['lat'], $home['lon'], $coord['lat'], $coord['lon']));
            $azimut = $home === null ? null
                : (int) round(Maidenhead::bearingDeg($home['lat'], $home['lon'], $coord['lat'], $coord['lon']));

            $points[] = [
                'lat' => $coord['lat'],
                'lon' => $coord['lon'],
                'call' => $call,
                'wwl' => $wwl,
                'mode' => (int) $q['mode'],
                'dist' => $dist,
                'azimut' => $azimut,
                'points' => Maidenhead::qsoPoints($homeSq, Maidenhead::bigSquare($wwl)),
            ];
        }

        return $points;
    }

    /** Smí se deník rovnou podat jako hlášení (jen 144 MHz)? */
    private function isSubmittable(): bool
    {
        return str_starts_with(strtoupper(trim($this->pband)), '144');
    }

    /** Stáhne hotový deník jako .edi soubor. */
    public function download(EdiComposer $composer): StreamedResponse
    {
        $this->errorMessage = '';
        $text = $composer->compose($this->headerArray(), $this->qsoRows());
        $call = strtoupper(trim($this->pcall));
        $filename = ($call !== '' ? preg_replace('/[^A-Z0-9]/', '', $call) : 'denik').'.edi';

        return response()->streamDownload(static function () use ($text): void {
            echo $text;
        }, $filename, ['Content-Type' => 'text/plain; charset=utf-8']);
    }

    /** Rovnou podá vygenerovaný deník jako hlášení (jen 144 MHz). */
    public function odeslat(EdiComposer $composer): mixed
    {
        $this->errorMessage = '';

        $this->pcall = strtoupper(trim($this->pcall));
        $this->pwwlo = strtoupper(trim($this->pwwlo));
        $this->rname = trim($this->rname);
        $this->rhbbs = trim($this->rhbbs);
        $this->rphon = trim($this->rphon);

        // U podání EDI deníku stačí jeden kontakt (telefon NEBO e-mail) – formát
        // se ověřuje jen u vyplněného pole, „alespoň jeden" se kontroluje níže.
        $rules = [
            'pcall' => ['required', 'string', 'max:10'],
            'pwwlo' => ['required', 'string', 'max:6', new ValidMaidenhead],
            'tdate' => ['required', 'date'],
            'rname' => ['required', 'string', 'max:60'],
        ];
        if ($this->rhbbs !== '') {
            $rules['rhbbs'] = ['email', 'max:250'];
        }
        if ($this->rphon !== '') {
            $rules['rphon'] = ['string', 'max:20', new ValidPhone];
        }

        $this->validate($rules, attributes: [
            'pcall' => 'volací značka',
            'pwwlo' => 'lokátor',
            'tdate' => 'datum závodu',
            'rname' => 'jméno',
            'rhbbs' => 'e-mail',
            'rphon' => 'telefon',
        ]);

        if ($this->rhbbs === '' && $this->rphon === '') {
            $this->addError('rphon', StoreHlaseniRequest::CHYBI_KONTAKT);

            return null;
        }

        if (! $this->isSubmittable()) {
            $this->errorMessage = 'Rovnou podat jako hlášení lze jen deník na pásmu 144 MHz. Pro ostatní pásma deník stáhněte a nahrajte přes Odeslat deník.';

            return null;
        }

        // Musí existovat aspoň jedno kompletní spojení.
        if ($this->buildLog()->qsoCount() === 0) {
            $this->errorMessage = 'Deník neobsahuje žádné kompletní spojení (vyplň čas, volačku a lokátor).';

            return null;
        }

        try {
            $log = app(EdiParser::class)->parse($composer->compose($this->headerArray(), $this->qsoRows()));
        } catch (EdiParseException $e) {
            $this->errorMessage = $e->getMessage();

            return null;
        }

        try {
            $row = app(ImportEdiAction::class)->execute(
                $log,
                enforceUploadWindow: ! $this->isAdmin(),
                overrides: [
                    'jmeno' => $this->rname,
                    'mail' => $this->rhbbs,
                    'telefon' => $this->rphon,
                    'soapbox' => mb_substr($this->remarks, 0, 250),
                    'approved' => $this->isAdmin(),
                ],
            );
        } catch (
            EmptyPCallException|TDateNotContestDayException|RoundNotFoundException|TDateMismatchException|
            DuplicateEdiException|UnknownBandException|UnknownSectionException|
            UploadWindowClosedException $e
        ) {
            $this->errorMessage = $e->getMessage();

            return null;
        } catch (Throwable $e) {
            Log::error('edi_generator.submit', ['exception' => $e]);
            $this->errorMessage = 'Neočekávaná chyba při ukládání deníku. Zkuste znovu nebo kontaktujte správce.';

            return null;
        }

        RankRoundJob::dispatchSync($row->round_id);

        session()->flash('announcement', 'Hlášení bylo uloženo.');

        return $this->redirectRoute('pribezne_vysledky', $this->isAdmin() ? ['kolo' => $row->round_id] : [], navigate: false);
    }

    private function isAdmin(): bool
    {
        return (bool) auth()->user()?->is_admin;
    }

    public function render(EdiComposer $composer): View
    {
        $homeLoc = strtoupper(trim($this->pwwlo));
        $home = Maidenhead::toLatLon($homeLoc);
        $homeSq = Maidenhead::bigSquare($homeLoc);

        $score = app(ScoringService::class)->scoreLog($this->buildLog());
        $points = $this->mapPoints($home, $homeSq);

        return view('livewire.edi-generator', [
            'ediText' => $composer->compose($this->headerArray(), $this->qsoRows()),
            'score' => $score,
            'mapPoints' => $points,
            'home' => $home,
            'homeLoc' => $homeLoc,
            'bands' => EdiComposer::BANDS,
            'modes' => [2 => QsoMode::Cw->label(), 1 => QsoMode::Ssb->label()],
            'submittable' => $this->isSubmittable(),
        ]);
    }
}
