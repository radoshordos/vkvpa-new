<?php

declare(strict_types=1);

namespace Tests\Feature;

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

        $this->assertSame(1, $a->fresh()->poradi);
        $this->assertSame(2, $b->fresh()->poradi);
        $this->assertSame(2, $c->fresh()->poradi); // stejné pořadí jako B
        $this->assertSame(3, $d->fresh()->poradi);
    }

    public function test_close_round_sets_vyhodnoceno(): void
    {
        $kolo = $this->kolo();
        $this->assertNull($kolo->vyhodnoceno);

        app(ScoringService::class)->closeRound($kolo->id);

        $this->assertNotNull($kolo->fresh()->vyhodnoceno);
    }

    public function test_score_edi_from_fixture(): void
    {
        $edi = (string) file_get_contents(__DIR__ . '/../fixtures/sample.edi');
        $head = new EdiImportService()->import(new EdiParser()->parse($edi));

        $score = app(ScoringService::class)->scoreEdi($head);

        // Domácí PWWLo=JN99AJ → domácí čtverec JN99. JN99BP (home) vyloučen,
        // JN89PV počítán: pocet=1, nasobice = 1 cizí čtverec + 1 = 2, body = 2.
        $this->assertSame(1, $score->pocet);
        $this->assertSame(2, $score->nasobice);
        $this->assertSame(2, $score->body);
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

        $this->assertSame(250, (int) $res->firstWhere('znacka', 'OK1A')->celkem);
    }
}
