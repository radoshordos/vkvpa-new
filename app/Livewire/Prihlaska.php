<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Actions\ImportEdiAction;
use App\Exceptions\DuplicateEdiException;
use App\Exceptions\EdiParseException;
use App\Exceptions\EmptyPCallException;
use App\Exceptions\RoundNotFoundException;
use App\Exceptions\TDateMismatchException;
use App\Exceptions\TDateNotContestDayException;
use App\Exceptions\UnknownBandException;
use App\Exceptions\UnknownSectionException;
use App\Exceptions\UploadWindowClosedException;
use App\Jobs\RankRoundJob;
use App\Models\VkvpaData;
use App\Models\VkvpaKategorie;
use App\Models\VkvpaKola;
use App\Rules\ValidMaidenhead;
use App\Rules\ValidPhone;
use App\Services\Edi\EdiLog;
use App\Services\Edi\EdiParser;
use App\Services\Edi\EdiReducer;
use App\Services\Edi\EdiValidator;
use App\Services\Scoring\EdiScoreDebugger;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Throwable;

/**
 * Jednotné podání hlášení (EDI i ruční) v jednom Livewire komponentu.
 *
 * Tok:
 *   choose      – výběr: nahrát EDI, nebo „nemám EDI soubor".
 *   edi-review  – EDI je naparsován a obodován JEN V PAMĚTI (do DB se nic nezapíše);
 *                 závodník zkontroluje a upraví kontaktní údaje.
 *   manual      – ruční formulář.
 * Teprve „Odeslat" ({@see odeslat()}) vše uloží a přesměruje na průběžné výsledky.
 */
class Prihlaska extends Component
{
    use WithFileUploads;

    /** choose | edi-review | manual */
    public string $mode = 'choose';

    #[Validate('required|file|max:10240|extensions:edi,txt')]
    public mixed $upload = null;

    public string $errorMessage = '';

    /** @var list<string> */
    public array $lineErrors = [];

    /** @var list<string> */
    public array $warnings = [];

    // ── Náhled EDI (read-only) ───────────────────────────────────────────────
    public string $pcall = '';

    public string $band = '';

    public int $pocetView = 0;

    public int $nasobiceView = 0;

    public int $bodyView = 0;

    public string $koloNazev = '';

    public string $kategorieNazev = '';

    // ── Editovatelná pole ────────────────────────────────────────────────────
    // Ruční režim: značka/lokátor/kolo/kategorie + počty. EDI režim: jen kontakt.
    public int $kolo = 0;

    public int $kategorie = 0;

    public string $znacka = '';

    public string $locator = '';

    public bool $qrp = false;

    public bool $lp = false;

    public int $pocet = 0;

    public int $bodu_za_qso = 0;

    public int $nasobice = 0;

    public int $body = 0;

    public string $jmeno = '';

    public string $email = '';

    public string $telefon = '';

    public string $poznamka = '';

    public string $soapbox = '';

    /** Po výběru souboru: naparsovat a obodovat v paměti, přejít do náhledu. */
    public function updatedUpload(): void
    {
        $this->validateOnly('upload');
        $this->nactiEdi();
    }

    /** Přepnutí na ruční formulář („nemám EDI soubor"). */
    public function rucne(): void
    {
        $this->mode = 'manual';
        $this->resetEdiState();
    }

    /** Zpět z náhledu/ručního formuláře na výběr. */
    public function zpet(): void
    {
        $this->mode = 'choose';
        $this->upload = null;
        $this->resetEdiState();
    }

    private function resetEdiState(): void
    {
        $this->errorMessage = '';
        $this->lineErrors = [];
        $this->warnings = [];
    }

    private function nactiEdi(): void
    {
        $this->resetEdiState();

        try {
            $log = $this->parseUpload();
        } catch (EdiParseException $e) {
            $this->errorMessage = $e->getMessage();
            $this->lineErrors = $e->lineErrors;

            return;
        }

        $action = app(ImportEdiAction::class);

        try {
            $preview = $action->preview($log, enforceUploadWindow: ! $this->isAdmin());
        } catch (
            EmptyPCallException|TDateNotContestDayException|RoundNotFoundException|TDateMismatchException|
            DuplicateEdiException|UnknownBandException|UnknownSectionException|
            UploadWindowClosedException $e
        ) {
            $this->errorMessage = $e->getMessage();

            return;
        } catch (Throwable $e) {
            Log::error('prihlaska.edi_preview', ['exception' => $e]);
            $this->errorMessage = 'Neočekávaná chyba při zpracování deníku. Zkuste znovu nebo kontaktujte správce.';

            return;
        }

        $h = $log->header;
        $this->kolo = $preview->idKola;
        $this->kategorie = $preview->idKategorie;
        $this->pcall = $h->pCall();
        $this->band = $h->pBand();
        $this->znacka = $h->pCall();
        $this->locator = $h->pWWLo();
        $this->qrp = $h->isQrp();
        $this->lp = $h->isLp();
        $this->pocetView = $preview->score->pocet;
        $this->nasobiceView = $preview->score->nasobice;
        $this->bodyView = $preview->score->body;
        // Název kola/kategorie je jen informativní popisek do náhledu – pokud
        // řádek chybí (kategorie může být statické id bez DB záznamu), necháme prázdno.
        $koloNazev = VkvpaKola::query()->whereKey($preview->idKola)->value('nazev');
        $kategorieNazev = VkvpaKategorie::query()->whereKey($preview->idKategorie)->value('nazev');
        $this->koloNazev = is_string($koloNazev) ? $koloNazev : '';
        $this->kategorieNazev = is_string($kategorieNazev) ? $kategorieNazev : '';

        // Předvyplníme kontaktní pole z hlavičky – závodník je může upravit.
        $this->jmeno = $h->rName();
        $this->email = $h->rEmail();
        $this->telefon = $h->rPhon();
        $this->soapbox = $h->get('RSoap');

        $this->warnings = app(EdiValidator::class)->validate($log)->messages();

        $this->mode = 'edi-review';
    }

    /** Naparsuje obsah dočasně nahraného souboru (bez DB). */
    private function parseUpload(): EdiLog
    {
        /** @var TemporaryUploadedFile $file */
        $file = $this->upload;
        $content = (string) file_get_contents($file->getRealPath());

        return app(EdiParser::class)->parse($content);
    }

    public function odeslat(): mixed
    {
        return $this->mode === 'edi-review' ? $this->odeslatEdi() : $this->odeslatRucne();
    }

    private function odeslatEdi(): mixed
    {
        $this->resetEdiState();

        $this->jmeno = trim($this->jmeno);
        $this->telefon = trim($this->telefon);

        $this->validate([
            'jmeno' => ['required', 'string', 'max:60'],
            'email' => ['required', 'email', 'max:250'],
            'telefon' => ['required', 'string', 'max:20', new ValidPhone],
            'poznamka' => ['nullable', 'string', 'max:250'],
            'soapbox' => ['nullable', 'string', 'max:250'],
        ], attributes: [
            'jmeno' => 'jméno',
            'email' => 'e-mail',
            'telefon' => 'telefon',
        ]);

        try {
            $log = $this->parseUpload();
        } catch (EdiParseException $e) {
            $this->errorMessage = $e->getMessage();
            $this->lineErrors = $e->lineErrors;

            return null;
        }

        try {
            $row = app(ImportEdiAction::class)->execute(
                $log,
                enforceUploadWindow: ! $this->isAdmin(),
                overrides: [
                    'jmeno' => $this->jmeno,
                    'mail' => $this->email,
                    'telefon' => $this->telefon,
                    'soapbox' => $this->soapbox,
                    'poznamka' => $this->poznamka,
                    'qrp' => $this->qrp,
                    'lp' => $this->lp,
                    'schvaleno' => $this->isAdmin(),
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
            Log::error('prihlaska.edi_store', ['exception' => $e]);
            $this->errorMessage = 'Neočekávaná chyba při ukládání deníku. Zkuste znovu nebo kontaktujte správce.';

            return null;
        }

        // Pořadí kola přepočítat hned (u nepřevzatého řádku neškodné).
        RankRoundJob::dispatchSync($row->id_kola);

        return $this->dokonceno($row->id_kola);
    }

    private function odeslatRucne(): mixed
    {
        $this->znacka = mb_strtoupper(trim($this->znacka));
        $this->locator = mb_strtoupper(trim($this->locator));
        $this->jmeno = trim($this->jmeno);
        $this->telefon = trim($this->telefon);

        $this->validate([
            'kolo' => ['required', 'integer', 'exists:vkvpa_kola,id'],
            'kategorie' => ['required', 'integer', 'exists:vkvpa_kategorie,id'],
            'znacka' => ['required', 'string', 'max:10'],
            'locator' => ['required', 'string', 'max:6', new ValidMaidenhead],
            'jmeno' => ['required', 'string', 'max:60'],
            'email' => ['required', 'email', 'max:250'],
            'telefon' => ['required', 'string', 'max:20', new ValidPhone],
            'pocet' => ['nullable', 'integer', 'min:0'],
            'bodu_za_qso' => ['nullable', 'integer', 'min:0'],
            'nasobice' => ['nullable', 'integer', 'min:0'],
            'body' => ['nullable', 'integer', 'min:0'],
            'poznamka' => ['nullable', 'string', 'max:250'],
            'soapbox' => ['nullable', 'string', 'max:250'],
        ], attributes: [
            'kolo' => 'kolo',
            'jmeno' => 'jméno',
            'email' => 'e-mail',
            'telefon' => 'telefon',
            'znacka' => 'volací znak',
            'locator' => 'lokátor',
        ]);

        // Hlášení od veřejnosti lze odeslat jen v otevřeném okně příjmu kola.
        if (! $this->isAdmin()) {
            $kolo = VkvpaKola::find($this->kolo);
            if ($kolo === null || ! $kolo->prijimaHlaseni()) {
                $this->addError('kolo', 'Kolo právě nepřijímá hlášení – odeslat je lze jen v otevřeném upload okně (od dne závodu 08:00 UTC do uzávěrky).');

                return null;
            }
        }

        if (VkvpaData::query()
            ->where('id_kola', $this->kolo)
            ->where('znacka', $this->znacka)
            ->where('id_kategorie', $this->kategorie)
            ->exists()
        ) {
            $this->addError('znacka', 'Pro toto kolo a kategorii již existuje hlášení pro značku '.$this->znacka.'.');

            return null;
        }

        $row = VkvpaData::create([
            'id_kola' => $this->kolo,
            'id_kategorie' => $this->kategorie,
            'znacka' => $this->znacka,
            'locator' => $this->locator,
            'pocet' => $this->pocet,
            'bodu_za_qso' => $this->bodu_za_qso,
            'nasobice' => $this->nasobice,
            'body' => $this->body,
            'qrp' => $this->qrp,
            'lp' => $this->lp,
            'mail' => $this->email,
            'jmeno' => $this->jmeno,
            'telefon' => $this->telefon,
            'soapbox' => $this->soapbox,
            'poznamka' => $this->poznamka,
            'edihead_id' => null,
            // Veřejné hlášení čeká na převzetí vyhodnocovatelem; admin smí rovnou převzít.
            'schvaleno' => $this->isAdmin(),
        ]);

        RankRoundJob::dispatchSync($row->id_kola);

        return $this->dokonceno($row->id_kola);
    }

    private function dokonceno(int $idKola): mixed
    {
        session()->flash('announcement', 'Hlášení bylo uloženo.');

        // Admin smí podat hlášení i pro jiné než aktuální průběžné kolo (backfill).
        // Aby na výsledcích viděl právě to kolo, předáme ?kolo= (výběr kola má jen
        // admin). Veřejnost vždy podává do aktuálního průběžného kola – bez parametru.
        $params = $this->isAdmin() ? ['kolo' => $idKola] : [];

        return $this->redirectRoute('pribezne_vysledky', $params, navigate: false);
    }

    private function isAdmin(): bool
    {
        return (bool) auth()->user()?->is_admin;
    }

    public function render(): View
    {
        // V náhledu EDI dopočítáme rozpad spojení (co se započítalo a proč)
        // a původní + redukovaný (EDIR) soubor – vše z paměti, bez zápisu do DB.
        // Předáváme jako data šablony (ne public property), takže nic neserializujeme.
        $report = null;
        $ediLines = [];
        $ediReduced = '';

        if ($this->mode === 'edi-review' && $this->upload !== null) {
            try {
                $log = $this->parseUpload();
                $reducer = app(EdiReducer::class);
                $report = app(EdiScoreDebugger::class)->analyze($log);
                // Řádky původního EDI s příznakem, zda je EDIR (ořez na okno) zahodí.
                $ediLines = $reducer->annotate($log->rawSource);
                $ediReduced = $reducer->reduce($log->rawSource);
            } catch (Throwable) {
                // Náhled bez rozpadu – chybu nahrání řeší updatedUpload/odeslat.
            }
        }

        return view('livewire.prihlaska', [
            'kola' => VkvpaKola::query()->orderByDesc('datum_konani')->limit(36)->get(),
            'kategorieList' => VkvpaKategorie::query()->orderBy('id')->get(),
            'report' => $report,
            'ediLines' => $ediLines,
            'ediReduced' => $ediReduced,
        ]);
    }
}
