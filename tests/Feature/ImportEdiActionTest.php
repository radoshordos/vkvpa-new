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
use App\Models\EdiEntry;
use App\Models\Edihead;
use App\Models\Ediline;
use App\Models\EdiRound;
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

    private EdiRound $round;

    protected function setUp(): void
    {
        parent::setUp();

        $this->round = EdiRound::create([
            'starts_at' => '2026-03-15 08:00:00',
            'closes_at' => now()->addDay(),
            'name' => '1. kolo 2026',
            'note' => '',
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

    private function execute(EdiLog $log): EdiEntry
    {
        return $this->action()->execute($log, notify: false, enforceUploadWindow: false);
    }

    /** Tentýž vzorový deník, ale s jiným pásmem v hlavičce (→ jiná kategorie). */
    private function sampleLogOnBand(string $pBand): EdiLog
    {
        $edi = (string) file_get_contents(__DIR__.'/../fixtures/sample.edi');
        $edi = (string) preg_replace('/^PBand=.*$/m', 'PBand='.$pBand, $edi);

        return (new EdiParser)->parse($edi);
    }

    // ---- happy path -----------------------------------------------------

    public function test_execute_creates_edihead_edilines_and_edi_entries(): void
    {
        $data = $this->execute($this->sampleLog());

        $this->assertInstanceOf(EdiEntry::class, $data);
        $this->assertSame('OK2KJT', $data->callsign);
        $this->assertSame($this->round->id, $data->round_id);
        $this->assertNotNull($data->edi_head_id);

        $this->assertSame(1, Edihead::count());
        $this->assertSame(2, Ediline::count());
        $this->assertSame(1, EdiEntry::count());
    }

    public function test_execute_computes_correct_score(): void
    {
        $data = $this->execute($this->sampleLog());

        // sample.edi: home JN99, QSO do JN99BP (vlastní, 2 b.) + JN89PV (soused, 3 b.)
        // pocet=2, boduZaQso=5, multiplier=2, body=10
        $this->assertSame(2, $data->qso_count);
        $this->assertSame(5, $data->qso_points);
        $this->assertSame(2, $data->multiplier);
        $this->assertSame(10, $data->points);
    }

    public function test_execute_sets_schvaleno_false(): void
    {
        $data = $this->execute($this->sampleLog());

        $this->assertFalse((bool) $data->approved);
    }

    public function test_execute_applies_overrides(): void
    {
        $data = $this->action()->execute(
            $this->sampleLog(),
            notify: false,
            enforceUploadWindow: false,
            overrides: ['email' => 'override@example.com'],
        );

        $this->assertSame('override@example.com', $data->email);
    }

    // ---- preview --------------------------------------------------------

    public function test_preview_returns_preview_without_writing_to_db(): void
    {
        $preview = $this->action()->preview($this->sampleLog(), enforceUploadWindow: false);

        $this->assertInstanceOf(ImportEdiPreview::class, $preview);
        $this->assertSame($this->round->id, $preview->idKola);
        $this->assertGreaterThan(0, $preview->idKategorie);

        $this->assertSame(0, Edihead::count(), 'Náhled nesmí zapisovat do DB');
        $this->assertSame(0, EdiEntry::count());
    }

    public function test_preview_score_matches_execute_score(): void
    {
        $log = $this->sampleLog();

        $preview = $this->action()->preview($log, enforceUploadWindow: false);
        $data = $this->execute($log);

        $this->assertSame($preview->score->qsoCount, $data->qso_count);
        $this->assertSame($preview->score->qsoPoints, $data->qso_points);
        $this->assertSame($preview->score->multiplier, $data->multiplier);
        $this->assertSame($preview->score->points, $data->points);
    }

    // ---- výjimky --------------------------------------------------------

    public function test_throws_round_not_found_when_no_round_for_tdate(): void
    {
        // Přesuneme kolo na duben → pro TDate=20260315 (březen) žádné kolo neexistuje.
        $this->round->update(['starts_at' => '2026-04-20 08:00:00']);

        $this->expectException(RoundNotFoundException::class);
        $this->execute($this->sampleLog());
    }

    public function test_throws_tdate_not_contest_day_when_round_date_differs(): void
    {
        // Kolo je v březnu, ale jiný den – TDate=20260315, starts_at=20260322.
        $this->round->update(['starts_at' => '2026-03-22 08:00:00']);

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

    public function test_allows_same_station_in_different_category_of_same_round(): void
    {
        $action = $this->action();
        $first = $action->execute($this->sampleLog(), notify: false, enforceUploadWindow: false);

        // Tatáž stanice, totéž kolo, ale jiné pásmo → jiná kategorie. Duplicita
        // se hlídá na trojici kolo+značka+kategorie, takže druhý deník projde.
        $second = $action->execute($this->sampleLogOnBand('432 MHz'), notify: false, enforceUploadWindow: false);

        $this->assertNotSame($first->category_id, $second->category_id);
        $this->assertSame(2, EdiEntry::where('callsign', 'OK2KJT')->where('round_id', $this->round->id)->count());
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
        $this->round->update(['evaluated_at' => now()->subDay()]);

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
