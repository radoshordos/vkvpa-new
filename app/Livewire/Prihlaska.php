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
use App\Models\EdiCategory;
use App\Models\EdiEntry;
use App\Models\EdiRound;
use App\Rules\ValidMaidenhead;
use App\Rules\ValidPhone;
use App\Services\Edi\EdiLog;
use App\Services\Edi\EdiParser;
use App\Services\Edi\EdiReducer;
use App\Services\Edi\EdiValidator;
use App\Services\Scoring\EdiScoreDebugger;
use App\Services\Scoring\ScoringService;
use App\Support\VkvpaSettings;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
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

    public mixed $upload = null;

    public string $errorMessage = '';

    /** @var list<string> */
    public array $lineErrors = [];

    /** @var list<string> */
    public array $warnings = [];

    // ── Náhled EDI (read-only) ───────────────────────────────────────────────
    public string $pcall = '';

    public string $band = '';

    public int $qsoCountView = 0;

    public int $multiplierView = 0;

    public int $pointsView = 0;

    public string $roundName = '';

    public string $categoryName = '';

    // ── Editovatelná pole ────────────────────────────────────────────────────
    // Ruční režim: značka/lokátor/kolo/kategorie + počty. EDI režim: jen kontakt.
    public int $round = 0;

    public int $category = 0;

    public string $callsign = '';

    public string $locator = '';

    public bool $qrp = false;

    public bool $lp = false;

    public int $qsoCount = 0;

    public int $qso_points = 0;

    public int $multiplier = 0;

    public int $points = 0;

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $note = '';

    public string $soapbox = '';

    /**
     * Pravidla pro nahraný soubor. Max velikost se odvozuje z konfigurace
     * (vkvpa.edi_max_size_kb, default 500 KB) – sjednoceno s ostatními upload
     * cestami (ZIP import, EDI debug), aby veřejné nahrání nebylo benevolentnější
     * než admin. validateOnly('upload') v updatedUpload() z toho čerpá.
     *
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'upload' => ['required', 'file', 'max:'.VkvpaSettings::ediMaxSizeKb(), 'extensions:edi,txt'],
        ];
    }

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

        // Veřejnost podává jen do aktuálního průběžného kola – předvyplníme je
        // a selektor kola nezobrazujeme (výběr má jen admin pro backfill).
        if (! $this->isAdmin()) {
            $aktualni = EdiRound::currentForStandings();
            if ($aktualni !== null) {
                $this->round = $aktualni->id;
                $this->roundName = $aktualni->name;
            }
        }
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
        $this->round = $preview->idKola;
        $this->category = $preview->idKategorie;
        $this->pcall = $h->pCall();
        $this->band = $h->pBand();
        $this->callsign = $h->pCall();
        $this->locator = $h->pWWLo();
        $this->qrp = $h->isQrp();
        $this->lp = $h->isLp();
        $this->qsoCountView = $preview->score->qsoCount;
        $this->multiplierView = $preview->score->multiplier;
        $this->pointsView = $preview->score->points;
        // Název kola/kategorie je jen informativní popisek do náhledu – pokud
        // řádek chybí (kategorie může být statické id bez DB záznamu), necháme prázdno.
        $roundName = EdiRound::query()->whereKey($preview->idKola)->value('name');
        $categoryName = EdiCategory::query()->whereKey($preview->idKategorie)->value('name');
        $this->roundName = is_string($roundName) ? $roundName : '';
        $this->categoryName = is_string($categoryName) ? $categoryName : '';

        // Předvyplníme kontaktní pole z hlavičky – závodník je může upravit.
        $this->name = $h->rName();
        $this->email = $h->rEmail();
        $this->phone = $h->rPhon();
        $this->soapbox = $h->get('RSoap');

        $this->warnings = app(EdiValidator::class)->validate($log, $this->ediContestDay($log))->messages();

        $this->mode = 'edi-review';
    }

    /**
     * Den závodu „YYMMDD" pro validaci/rozpad – datum konání kola dle TDate, aby
     * počty QSO „mimo den" odpovídaly skutečnému skóre ({@see ScoringService::scoreEdi()}).
     * Null, když kolo neznáme (validátor/debugger pak použijí den z TDate).
     */
    private function ediContestDay(EdiLog $log): ?string
    {
        return app(ScoringService::class)->contestDay(null, $log->header->tDate())?->format('ymd');
    }

    /** Memoizace naparsovaného deníku pro aktuální request ({@see parseUpload()}). */
    private ?EdiLog $parsedUpload = null;

    private ?string $parsedUploadKey = null;

    /**
     * Naparsuje obsah dočasně nahraného souboru (bez DB). Výsledek se v rámci
     * jednoho requestu memoizuje podle cesty k dočasnému souboru: v edi-review
     * režimu se komponenta překresluje opakovaně (odeslat()/render() volají
     * parseUpload() vícekrát za request) a bez cache by se týž soubor pokaždé
     * znovu četl, překódoval (iconv) a regexem rozparsoval. Privátní vlastnosti
     * se mezi Livewire requesty neserializují, takže cache je vždy čerstvá.
     */
    private function parseUpload(): EdiLog
    {
        /** @var TemporaryUploadedFile $file */
        $file = $this->upload;
        $key = (string) $file->getRealPath();

        if ($this->parsedUpload !== null && $this->parsedUploadKey === $key) {
            return $this->parsedUpload;
        }

        $content = (string) file_get_contents($key);
        $log = app(EdiParser::class)->parse($content);

        $this->parsedUploadKey = $key;

        return $this->parsedUpload = $log;
    }

    public function odeslat(): mixed
    {
        return $this->mode === 'edi-review' ? $this->odeslatEdi() : $this->odeslatRucne();
    }

    private function odeslatEdi(): mixed
    {
        $this->resetEdiState();

        $this->name = trim($this->name);
        $this->phone = trim($this->phone);

        $this->validate([
            'name' => ['required', 'string', 'max:60'],
            'email' => ['required', 'email', 'max:250'],
            'phone' => ['required', 'string', 'max:20', new ValidPhone],
            'note' => ['nullable', 'string', 'max:250'],
            'soapbox' => ['nullable', 'string', 'max:250'],
        ], attributes: [
            'name' => 'jméno',
            'email' => 'e-mail',
            'phone' => 'telefon',
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
                    'name' => $this->name,
                    'email' => $this->email,
                    'phone' => $this->phone,
                    'soapbox' => $this->soapbox,
                    'note' => $this->note,
                    'qrp' => $this->qrp,
                    'lp' => $this->lp,
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
            Log::error('prihlaska.edi_store', ['exception' => $e]);
            $this->errorMessage = 'Neočekávaná chyba při ukládání deníku. Zkuste znovu nebo kontaktujte správce.';

            return null;
        }

        // Pořadí kola přepočítat hned (u nepřevzatého řádku neškodné).
        RankRoundJob::dispatchSync($row->round_id);

        return $this->dokonceno($row->round_id);
    }

    private function odeslatRucne(): mixed
    {
        $this->callsign = mb_strtoupper(trim($this->callsign));
        $this->locator = mb_strtoupper(trim($this->locator));
        $this->name = trim($this->name);
        $this->phone = trim($this->phone);

        $this->validate([
            'round' => ['required', 'integer', 'exists:edi_rounds,id'],
            'category' => ['required', 'integer', 'exists:edi_category,id'],
            'callsign' => ['required', 'string', 'max:10'],
            'locator' => ['required', 'string', 'max:6', new ValidMaidenhead],
            'name' => ['required', 'string', 'max:60'],
            // E-mail je u ručního hlášení nepovinný (EDI hlavička ho obvykle nese).
            'email' => ['nullable', 'email', 'max:250'],
            'phone' => ['required', 'string', 'max:20', new ValidPhone],
            'qsoCount' => ['nullable', 'integer', 'min:0'],
            'qso_points' => ['nullable', 'integer', 'min:0'],
            'multiplier' => ['nullable', 'integer', 'min:0'],
            'points' => ['nullable', 'integer', 'min:0'],
            'note' => ['nullable', 'string', 'max:250'],
            'soapbox' => ['nullable', 'string', 'max:250'],
        ], attributes: [
            'round' => 'kolo',
            'name' => 'jméno',
            'email' => 'e-mail',
            'phone' => 'telefon',
            'callsign' => 'volací znak',
            'locator' => 'lokátor',
        ]);

        // Hlášení od veřejnosti lze odeslat jen v otevřeném okně příjmu kola.
        if (! $this->isAdmin()) {
            $kolo = EdiRound::find($this->round);
            if ($kolo === null || ! $kolo->acceptsReports()) {
                $this->addError('round', 'Kolo právě nepřijímá hlášení – odeslat je lze jen v otevřeném upload okně (od dne závodu 08:00 UTC do uzávěrky).');

                return null;
            }
        }

        if (EdiEntry::query()
            ->where('round_id', $this->round)
            ->where('callsign', $this->callsign)
            ->where('category_id', $this->category)
            ->exists()
        ) {
            $this->addError('callsign', 'Pro toto kolo a kategorii již existuje hlášení pro značku '.$this->callsign.'.');

            return null;
        }

        $row = EdiEntry::create([
            'round_id' => $this->round,
            'category_id' => $this->category,
            'callsign' => $this->callsign,
            'locator' => $this->locator,
            'qso_count' => $this->qsoCount,
            'qso_points' => $this->qso_points,
            'multiplier' => $this->multiplier,
            'points' => $this->points,
            'qrp' => $this->qrp,
            'lp' => $this->lp,
            'email' => $this->email,
            'name' => $this->name,
            'phone' => $this->phone,
            'soapbox' => $this->soapbox,
            'note' => $this->note,
            'edi_head_id' => null,
            // Veřejné hlášení čeká na převzetí vyhodnocovatelem; admin smí rovnou převzít.
            'approved' => $this->isAdmin(),
        ]);

        RankRoundJob::dispatchSync($row->round_id);

        return $this->dokonceno($row->round_id);
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
                $report = app(EdiScoreDebugger::class)->analyze($log, $this->ediContestDay($log));
                // Řádky původního EDI s příznakem, zda je EDIR (ořez na okno) zahodí.
                $ediLines = $reducer->annotate($log->rawSource);
                $ediReduced = $reducer->reduce($log->rawSource);
            } catch (Throwable) {
                // Náhled bez rozpadu – chybu nahrání řeší updatedUpload/odeslat.
            }
        }

        return view('livewire.prihlaska', [
            'isAdmin' => $this->isAdmin(),
            'kola' => EdiRound::query()->orderByDesc('starts_at')->limit(36)->get(),
            'kategorieList' => EdiCategory::query()->orderBy('id')->get(),
            'report' => $report,
            'ediLines' => $ediLines,
            'ediReduced' => $ediReduced,
        ]);
    }
}
