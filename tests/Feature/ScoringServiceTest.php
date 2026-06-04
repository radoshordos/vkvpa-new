<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Edihead;
use App\Models\Ediline;
use App\Models\VkvpaData;
use App\Models\VkvpaKategorie;
use App\Models\VkvpaKola;
use App\Services\Edi\EdiImportService;
use App\Services\Edi\EdiParser;
use App\Services\Scoring\ScoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScoringServiceTest extends TestCase
{
    use RefreshDatabase;

    private function kolo(string $nazev = 'Kolo 2026'): VkvpaKola
    {
        return VkvpaKola::create([
            'datum_konani' => now()->subDay(),
            'datum_uzaverky' => now()->addDays(5),
            'nazev' => $nazev,
            'poznamka' => '',
        ]);
    }

    private function kategorie(): VkvpaKategorie
    {
        return VkvpaKategorie::create(['nazev' => 'A', 'popis' => '', 'zkratka' => 'A', 'dxid' => 0]);
    }

    private function entry(int $kolo, int $kat, string $znacka, int $body): VkvpaData
    {
        return VkvpaData::create([
            'id_kola' => $kolo, 'id_kategorie' => $kat, 'znacka' => $znacka,
            'locator' => 'JN99AJ', 'pocet' => 10, 'bodu_za_qso' => 1, 'nasobice' => $body,
            'body' => $body, 'schvaleno' => true, 'odeslano' => false,
        ]);
    }

    public function test_rank_round_uses_dense_ranking_with_ties(): void
    {
        $kolo = $this->kolo();
        $kat = $this->kategorie();
        $a = $this->entry($kolo->id, $kat->id, 'OK1A', 500);
        $b = $this->entry($kolo->id, $kat->id, 'OK1B', 300);
        $c = $this->entry($kolo->id, $kat->id, 'OK1C', 300); // shoda s B
        $d = $this->entry($kolo->id, $kat->id, 'OK1D', 100);

        app(ScoringService::class)->rankRound($kolo->id);

        $this->assertSame(1, $a->refresh()->poradi);
        $this->assertSame(2, $b->refresh()->poradi);
        $this->assertSame(2, $c->refresh()->poradi); // stejné pořadí jako B
        $this->assertSame(3, $d->refresh()->poradi);
    }

    public function test_close_round_sets_vyhodnoceno(): void
    {
        $kolo = $this->kolo();
        $this->assertNull($kolo->vyhodnoceno);

        app(ScoringService::class)->closeRound($kolo->id);

        $this->assertNotNull($kolo->refresh()->vyhodnoceno);
    }

    public function test_score_edi_from_fixture(): void
    {
        $edi = (string) file_get_contents(__DIR__.'/../fixtures/sample.edi');
        $head = new EdiImportService()->import(new EdiParser()->parse($edi));

        $score = app(ScoringService::class)->scoreEdi($head);

        // Domácí PWWLo=JN99AJ → domácí čtverec JN99. Obě QSO se počítají:
        // JN99BP (vlastní čtverec, QSO-Points=2) + JN89PV (cizí, QSO-Points=3).
        // pocet=2, body za spojení = 2+3 = 5,
        // nasobice = 1 cizí čtverec (JN89) + 1 vlastní (JN99) = 2, body = 5 × 2 = 10.
        $this->assertSame(2, $score->pocet);
        $this->assertSame(5, $score->boduZaQso);
        $this->assertSame(2, $score->nasobice);
        $this->assertSame(10, $score->body);
    }

    public function test_score_edi_ignores_qso_outside_window(): void
    {
        $head = Edihead::create([
            'TDate' => '20260118;20260118', 'PCall' => 'OK1TEST', 'PWWLo' => 'JN99AA',
            'PSect' => '', 'PBand' => '', 'RName' => '', 'RPhon' => '', 'RHBBS' => '', 'SPowe' => 100,
        ]);
        Ediline::insert([
            // 2 QSO uvnitř okna (cizí čtverce JN89, JO70) → počítají se.
            // QSO-Points v deníku jsou schválně chybné – přepočítáme je z lokátorů.
            ['IDS' => $head->ID, 'Date' => '260118', 'Time' => '0830', 'CallSign' => 'A', 'Received-WWL' => 'JN89AA', 'QSO-Points' => 99],
            ['IDS' => $head->ID, 'Date' => '260118', 'Time' => '0930', 'CallSign' => 'B', 'Received-WWL' => 'JO70AA', 'QSO-Points' => 99],
            // mimo čas (12:30) a mimo den (17.) → nezapočítají se.
            ['IDS' => $head->ID, 'Date' => '260118', 'Time' => '1230', 'CallSign' => 'C', 'Received-WWL' => 'JN88AA', 'QSO-Points' => 1],
            ['IDS' => $head->ID, 'Date' => '260117', 'Time' => '0900', 'CallSign' => 'D', 'Received-WWL' => 'JN77AA', 'QSO-Points' => 1],
        ]);

        $score = app(ScoringService::class)->scoreEdi($head);

        // Domácí JN99. Body z lokátorů: JN89 (soused) = 3, JO70 (2 pásy) = 4.
        // pocet=2, body za spojení = 3+4 = 7, nasobice = 2+1 = 3, body = 7 × 3 = 21.
        $this->assertSame(2, $score->pocet);
        $this->assertSame(7, $score->boduZaQso);
        $this->assertSame(3, $score->nasobice);
        $this->assertSame(21, $score->body);
    }

    public function test_score_edi_counts_own_square_qso(): void
    {
        // Spojení ve vlastním velkém čtverci se počítá (2 body) a vlastní čtverec
        // je vždy násobičem – právě jednou, i když se v něm pracovalo.
        $head = Edihead::create([
            'TDate' => '20260118;20260118', 'PCall' => 'OK1TEST', 'PWWLo' => 'JN99AJ',
            'PSect' => '', 'PBand' => '', 'RName' => '', 'RPhon' => '', 'RHBBS' => '', 'SPowe' => 100,
        ]);
        Ediline::insert([
            // vlastní čtverec JN99 (2 body) + 2× cizí JN89 (3 body) ve stejném čtverci.
            // QSO-Points v deníku jsou schválně 0 – přepočítáme je z lokátorů.
            ['IDS' => $head->ID, 'Date' => '260118', 'Time' => '0830', 'CallSign' => 'A', 'Received-WWL' => 'JN99XX', 'QSO-Points' => 0],
            ['IDS' => $head->ID, 'Date' => '260118', 'Time' => '0900', 'CallSign' => 'B', 'Received-WWL' => 'JN89AA', 'QSO-Points' => 0],
            ['IDS' => $head->ID, 'Date' => '260118', 'Time' => '0930', 'CallSign' => 'C', 'Received-WWL' => 'JN89BB', 'QSO-Points' => 0],
        ]);

        $score = app(ScoringService::class)->scoreEdi($head);

        // pocet=3, body za spojení = 2 (vlastní JN99) + 3 + 3 (soused JN89) = 8,
        // nasobice = 1 cizí čtverec (JN89) + 1 vlastní (JN99) = 2, body = 8 × 2 = 16.
        $this->assertSame(3, $score->pocet);
        $this->assertSame(8, $score->boduZaQso);
        $this->assertSame(2, $score->nasobice);
        $this->assertSame(16, $score->body);
    }

    public function test_yearly_results_nullifies_non_edi_for_kola_above_threshold(): void
    {
        config(['vkvpa.non_edi_nullify_from_kolo' => 2]); // práh = ID kola 2

        $kat = $this->kategorie();
        $k1 = $this->kolo('1. kolo 2026'); // ID=1, pod prahem → body se počítají
        $k2 = $this->kolo('2. kolo 2026'); // ID=2, na prahu → non-EDI nulifikace

        $e1 = $this->entry($k1->id, $kat->id, 'OK1A', 100);
        $e1->update(['poradi' => 1]); // EDI_ID=0 (default) → pod prahem, počítá se

        $e2 = $this->entry($k2->id, $kat->id, 'OK1A', 200);
        $e2->update(['poradi' => 1]); // EDI_ID=0 (default) → na/nad prahem → 0 bodů

        $res = app(ScoringService::class)->yearlyResults(2026);

        $row = $res->firstWhere('znacka', 'OK1A');
        $this->assertNotNull($row);
        $this->assertSame(100, (int) $row->celkem); // 100 + 0 (nulifikováno) = 100
    }

    public function test_yearly_results_aggregates_by_callsign(): void
    {
        $kat = $this->kategorie();
        $k1 = $this->kolo('1. kolo 2026');
        $k2 = $this->kolo('2. kolo 2026');
        $e1 = $this->entry($k1->id, $kat->id, 'OK1A', 100);
        $e2 = $this->entry($k2->id, $kat->id, 'OK1A', 150);
        $e1->update(['poradi' => 1, 'EDI_ID' => 1]);
        $e2->update(['poradi' => 1, 'EDI_ID' => 1]);

        $res = app(ScoringService::class)->yearlyResults(2026);

        $row = $res->firstWhere('znacka', 'OK1A');
        $this->assertNotNull($row);
        $this->assertSame(250, (int) $row->celkem);
    }
}
