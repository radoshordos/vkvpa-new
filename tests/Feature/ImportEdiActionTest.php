<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\ImportEdiAction;
use App\Actions\ImportEdiPreview;
use App\Exceptions\DuplicateEdiException;
use App\Exceptions\EmptyPCallException;
use App\Exceptions\RoundNotFoundException;
use App\Exceptions\TDateMismatchException;
use App\Exceptions\TDateNotContestDayException;
use App\Exceptions\UnknownBandException;
use App\Exceptions\UploadWindowClosedException;
use App\Models\Edihead;
use App\Models\Ediline;
use App\Models\VkvpaData;
use App\Models\VkvpaKola;
use App\Services\Edi\EdiLog;
use App\Services\Edi\EdiParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Přímé testy ImportEdiAction: business validace, výjimky a výsledky
 * operace bez průchodu Livewire vrstvou.
 */
class ImportEdiActionTest extends TestCase
{
    use RefreshDatabase;

    private VkvpaKola $kolo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->kolo = VkvpaKola::create([
            'datum_konani' => '2026-03-15 08:00:00',
            'datum_uzaverky' => now()->addDay(),
            'nazev' => '1. kolo 2026',
            'poznamka' => '',
        ]);
    }

    // ---- helpers --------------------------------------------------------

    private function action(): ImportEdiAction
    {
        return app(ImportEdiAction::class);
    }

    private function sampleLog(): EdiLog
    {
        $edi = (string) file_get_contents(__DIR__.'/../fixtures/sample.edi');

        return (new EdiParser)->parse($edi);
    }

    private function execute(EdiLog $log): VkvpaData
    {
        return $this->action()->execute($log, notify: false, enforceUploadWindow: false);
    }

    // ---- happy path -----------------------------------------------------

    public function test_execute_creates_edihead_edilines_and_vkvpa_data(): void
    {
        $data = $this->execute($this->sampleLog());

        $this->assertInstanceOf(VkvpaData::class, $data);
        $this->assertSame('OK2KJT', $data->znacka);
        $this->assertSame($this->kolo->id, $data->id_kola);
        $this->assertNotNull($data->edihead_id);

        $this->assertSame(1, Edihead::count());
        $this->assertSame(2, Ediline::count());
        $this->assertSame(1, VkvpaData::count());
    }

    public function test_execute_computes_correct_score(): void
    {
        $data = $this->execute($this->sampleLog());

        // sample.edi: home JN99, QSO do JN99BP (vlastní, 2 b.) + JN89PV (soused, 3 b.)
        // pocet=2, boduZaQso=5, nasobice=2, body=10
        $this->assertSame(2, $data->pocet);
        $this->assertSame(5, $data->bodu_za_qso);
        $this->assertSame(2, $data->nasobice);
        $this->assertSame(10, $data->body);
    }

    public function test_execute_sets_schvaleno_false(): void
    {
        $data = $this->execute($this->sampleLog());

        $this->assertFalse((bool) $data->schvaleno);
    }

    public function test_execute_applies_overrides(): void
    {
        $data = $this->action()->execute(
            $this->sampleLog(),
            notify: false,
            enforceUploadWindow: false,
            overrides: ['mail' => 'override@example.com'],
        );

        $this->assertSame('override@example.com', $data->mail);
    }

    // ---- preview --------------------------------------------------------

    public function test_preview_returns_preview_without_writing_to_db(): void
    {
        $preview = $this->action()->preview($this->sampleLog(), enforceUploadWindow: false);

        $this->assertInstanceOf(ImportEdiPreview::class, $preview);
        $this->assertSame($this->kolo->id, $preview->idKola);
        $this->assertGreaterThan(0, $preview->idKategorie);

        $this->assertSame(0, Edihead::count(), 'Náhled nesmí zapisovat do DB');
        $this->assertSame(0, VkvpaData::count());
    }

    public function test_preview_score_matches_execute_score(): void
    {
        $log = $this->sampleLog();

        $preview = $this->action()->preview($log, enforceUploadWindow: false);
        $data = $this->execute($log);

        $this->assertSame($preview->score->pocet, $data->pocet);
        $this->assertSame($preview->score->boduZaQso, $data->bodu_za_qso);
        $this->assertSame($preview->score->nasobice, $data->nasobice);
        $this->assertSame($preview->score->body, $data->body);
    }

    // ---- výjimky --------------------------------------------------------

    public function test_throws_round_not_found_when_no_round_for_tdate(): void
    {
        // Přesuneme kolo na duben → pro TDate=20260315 (březen) žádné kolo neexistuje.
        $this->kolo->update(['datum_konani' => '2026-04-20 08:00:00']);

        $this->expectException(RoundNotFoundException::class);
        $this->execute($this->sampleLog());
    }

    public function test_throws_tdate_not_contest_day_when_round_date_differs(): void
    {
        // Kolo je v březnu, ale jiný den – TDate=20260315, datum_konani=20260322.
        $this->kolo->update(['datum_konani' => '2026-03-22 08:00:00']);

        $this->expectException(TDateNotContestDayException::class);
        $this->execute($this->sampleLog());
    }

    public function test_throws_duplicate_when_entry_already_exists(): void
    {
        $action = $this->action();
        $action->execute($this->sampleLog(), notify: false, enforceUploadWindow: false);

        $this->expectException(DuplicateEdiException::class);
        $action->execute($this->sampleLog(), notify: false, enforceUploadWindow: false);
    }

    public function test_throws_tdate_mismatch_when_qso_date_differs_from_tdate(): void
    {
        // TDate říká 20260315, ale QSO jsou datována 260420 (duben).
        $edi = implode("\n", [
            '[REG1TEST;1]',
            'TDate=20260315;20260315',
            'PCall=OK2KJT',
            'PWWLo=JN99AJ',
            'PSect=MULTI',
            'PBand=144 MHz',
            'RName=Test',
            'RPhon=',
            'RHBBS=',
            'SPowe=5',
            '[QSORecords;1]',
            '260420;0800;OK1XYZ;1;59;001;59;001;;JN79VS;3;;;;',
            '[END;]',
        ])."\n";

        $this->expectException(TDateMismatchException::class);
        $this->execute((new EdiParser)->parse($edi));
    }

    public function test_throws_unknown_band_for_unrecognised_pband(): void
    {
        $edi = implode("\n", [
            '[REG1TEST;1]',
            'TDate=20260315;20260315',
            'PCall=OK2KJT',
            'PWWLo=JN99AJ',
            'PSect=SINGLE',
            'PBand=999 MHz',
            'RName=Test',
            'RPhon=',
            'RHBBS=',
            'SPowe=5',
            '[QSORecords;0]',
            '[END;]',
        ])."\n";

        $this->expectException(UnknownBandException::class);
        $this->execute((new EdiParser)->parse($edi));
    }

    public function test_throws_upload_window_closed_for_evaluated_round(): void
    {
        $this->kolo->update(['vyhodnoceno' => now()->subDay()]);

        $this->expectException(UploadWindowClosedException::class);

        // Tentokrát s enforceUploadWindow=true (výchozí chování veřejného uploadu).
        $this->action()->execute($this->sampleLog(), notify: false, enforceUploadWindow: true);
    }

    public function test_throws_empty_pcall_when_pcall_is_missing(): void
    {
        $edi = implode("\n", [
            '[REG1TEST;1]',
            'TDate=20260315;20260315',
            'PCall=',
            'PWWLo=JN99AJ',
            'PSect=SINGLE',
            'PBand=144 MHz',
            'RName=Test',
            'RPhon=',
            'RHBBS=',
            'SPowe=5',
            '[QSORecords;0]',
            '[END;]',
        ])."\n";

        $this->expectException(EmptyPCallException::class);
        $this->execute((new EdiParser)->parse($edi));
    }
}
