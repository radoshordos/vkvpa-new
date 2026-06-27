<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\EdiCategory;
use App\Models\Edihead;
use App\Models\Ediline;
use App\Models\VkvpaData;
use App\Models\VkvpaKola;
use App\Services\Edi\EdiImportService;
use App\Services\Edi\EdiParser;
use App\Services\Scoring\ScoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScoringServiceTest extends TestCase
{
    use RefreshDatabase;

    /** Pořadí vytvořeného kola v rámci testu – datum_konani je v DB unikátní. */
    private int $koloSeq = 0;

    private function kolo(string $nazev = 'Kolo 2026'): VkvpaKola
    {
        return VkvpaKola::create([
            'datum_konani' => sprintf('2026-%02d-18', ($this->koloSeq++ % 12) + 1),
            'datum_uzaverky' => now()->addDays(5),
            'nazev' => $nazev,
            'poznamka' => '',
        ]);
    }

    private function kategorie(): EdiCategory
    {
        return EdiCategory::create(['name' => 'A', 'band' => 'A', 'section' => 'SO', 'variant' => 'domestic']);
    }

    /**
     * Doplní platný přijatý RST a pořadové číslo do QSO řádků, které je explicitně
     * neuvádějí – aby prošly filtrem completeExchange() (platné spojení musí mít
     * přijatý kód). Testy okna/dne/lokátoru tím nemusí ta pole opakovat.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function withExchange(array $rows): array
    {
        return array_map(static fn (array $r): array => $r + [
            'received_rst' => '59',
            'received_qso_number' => 1,
        ], $rows);
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

    public function test_score_edi_ok1io_real_log(): void
    {
        // Reálný deník OK1IO (VKV PA 2026/01, JO70NR) z resources/edi.
        $edi = (string) file_get_contents(resource_path('edi/012026/01ok1io.edi'));
        $head = new EdiImportService()->import(new EdiParser()->parse($edi));

        $score = app(ScoringService::class)->scoreEdi($head);

        // 43 QSO v okně 08–11 UTC dne 18. 1. Body za spojení z lokátorů:
        // 25× vlastní JO70 (2 b) + 16× sousední pás (3 b) + 2× JN99 (2. pás, 4 b)
        // = 50 + 48 + 8 = 106. Násobič = 7 různých velkých čtverců vč. vlastního
        // (JO70, JO80, JN79, JN89, JO60, JN69, JN99). Body = 106 × 7 = 742.
        $this->assertSame(43, $score->pocet);
        $this->assertSame(106, $score->boduZaQso);
        $this->assertSame(7, $score->nasobice);
        $this->assertSame(742, $score->body);
    }

    public function test_score_edi_ignores_qso_outside_window(): void
    {
        $head = Edihead::create([
            't_date' => '20260118;20260118', 'p_call' => 'OK1TEST', 'p_wwlo' => 'JN99AA',
            'p_sect' => '', 'p_band' => '', 'r_name' => '', 'r_phon' => '', 'r_emai' => '', 's_powe' => 100,
        ]);
        Ediline::insert($this->withExchange([
            // 2 QSO uvnitř okna (cizí čtverce JN89, JO70) → počítají se.
            // QSO-Points v deníku jsou schválně chybné – přepočítáme je z lokátorů.
            ['edihead_id' => $head->id, 'qso_at' => '2026-01-18 08:30:00', 'call_sign' => 'A', 'received_wwl' => 'JN89AA', 'qso_points' => 99],
            ['edihead_id' => $head->id, 'qso_at' => '2026-01-18 09:30:00', 'call_sign' => 'B', 'received_wwl' => 'JO70AA', 'qso_points' => 99],
            // mimo čas (12:30) a mimo den (17.) → nezapočítají se.
            ['edihead_id' => $head->id, 'qso_at' => '2026-01-18 12:30:00', 'call_sign' => 'C', 'received_wwl' => 'JN88AA', 'qso_points' => 1],
            ['edihead_id' => $head->id, 'qso_at' => '2026-01-17 09:00:00', 'call_sign' => 'D', 'received_wwl' => 'JN77AA', 'qso_points' => 1],
        ]));

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
            't_date' => '20260118;20260118', 'p_call' => 'OK1TEST', 'p_wwlo' => 'JN99AJ',
            'p_sect' => '', 'p_band' => '', 'r_name' => '', 'r_phon' => '', 'r_emai' => '', 's_powe' => 100,
        ]);
        Ediline::insert($this->withExchange([
            // vlastní čtverec JN99 (2 body) + 2× cizí JN89 (3 body) ve stejném čtverci.
            // QSO-Points v deníku jsou schválně 0 – přepočítáme je z lokátorů.
            ['edihead_id' => $head->id, 'qso_at' => '2026-01-18 08:30:00', 'call_sign' => 'A', 'received_wwl' => 'JN99XX', 'qso_points' => 0],
            ['edihead_id' => $head->id, 'qso_at' => '2026-01-18 09:00:00', 'call_sign' => 'B', 'received_wwl' => 'JN89AA', 'qso_points' => 0],
            ['edihead_id' => $head->id, 'qso_at' => '2026-01-18 09:30:00', 'call_sign' => 'C', 'received_wwl' => 'JN89BB', 'qso_points' => 0],
        ]));

        $score = app(ScoringService::class)->scoreEdi($head);

        // pocet=3, body za spojení = 2 (vlastní JN99) + 3 + 3 (soused JN89) = 8,
        // nasobice = 1 cizí čtverec (JN89) + 1 vlastní (JN99) = 2, body = 8 × 2 = 16.
        $this->assertSame(3, $score->pocet);
        $this->assertSame(8, $score->boduZaQso);
        $this->assertSame(2, $score->nasobice);
        $this->assertSame(16, $score->body);
    }

    public function test_score_edi_uses_kolo_day_not_first_tdate_token(): void
    {
        // Dvoudenní TDate: první token (20.) ≠ den konání kola (21.). QSO jsou ze
        // dne konání (21.) – musí se započítat. Dřív skóre bralo den z prvního
        // tokenu TDate (20.), takže platná QSO z 21. padala jako „mimo den" → 0.
        $kolo = VkvpaKola::create([
            'datum_konani' => '2026-06-21 08:00:00',
            'datum_uzaverky' => now()->addDays(5),
            'nazev' => '06/2026',
            'poznamka' => '',
        ]);

        $head = Edihead::create([
            'id_kola' => $kolo->id,
            't_date' => '20260620;20260621', 'p_call' => 'OK1TEST', 'p_wwlo' => 'JN99AJ',
            'p_sect' => '', 'p_band' => '', 'r_name' => '', 'r_phon' => '', 'r_emai' => '', 's_powe' => 100,
        ]);
        Ediline::insert($this->withExchange([
            // QSO ze dne konání (21.) v okně → započítá se.
            ['edihead_id' => $head->id, 'qso_at' => '2026-06-21 08:30:00', 'call_sign' => 'A', 'received_wwl' => 'JN89AA', 'qso_points' => 0],
            // QSO z prvního dne TDate (20.), který NENÍ den konání → mimo den, vyřazeno.
            ['edihead_id' => $head->id, 'qso_at' => '2026-06-20 08:30:00', 'call_sign' => 'B', 'received_wwl' => 'JO70AA', 'qso_points' => 0],
        ]));

        $score = app(ScoringService::class)->scoreEdi($head);

        // Jen QSO z 21. (JN89 soused = 3 b). pocet=1, nasobice = 1 cizí + 1 vlastní = 2,
        // body = 3 × 2 = 6.
        $this->assertSame(1, $score->pocet);
        $this->assertSame(3, $score->boduZaQso);
        $this->assertSame(2, $score->nasobice);
        $this->assertSame(6, $score->body);
    }

    public function test_yearly_results_nullifies_non_edi_for_kola_above_threshold(): void
    {
        config(['vkvpa.non_edi_nullify_from_kolo' => 2]); // práh = ID kola 2

        $kat = $this->kategorie();
        $k1 = $this->kolo('1. kolo 2026'); // ID=1, pod prahem → body se počítají
        $k2 = $this->kolo('2. kolo 2026'); // ID=2, na prahu → non-EDI nulifikace

        $e1 = $this->entry($k1->id, $kat->id, 'OK1A', 100);
        $e1->update(['poradi' => 1]); // edihead_id=NULL (default) → pod prahem, počítá se

        $e2 = $this->entry($k2->id, $kat->id, 'OK1A', 200);
        $e2->update(['poradi' => 1]); // edihead_id=NULL (default) → na/nad prahem → 0 bodů

        $res = app(ScoringService::class)->yearlyResults(2026);

        $row = $res->firstWhere('znacka', 'OK1A');
        $this->assertNotNull($row);
        $this->assertSame(100, (int) $row->celkem); // 100 + 0 (nulifikováno) = 100
    }

    // ------------------------------------------------------------------
    // Hraniční případy scoringu

    public function test_score_edi_no_qso_lines_gives_zero_points(): void
    {
        // Deník bez QSO řádků: pocet=0, nasobice=1 (vlastní čtverec vždy), body=0.
        $head = Edihead::create([
            't_date' => '20260118;20260118', 'p_call' => 'OK1TEST', 'p_wwlo' => 'JN99AJ',
            'p_sect' => '', 'p_band' => '', 'r_name' => '', 'r_phon' => '', 'r_emai' => '', 's_powe' => 100,
        ]);
        // Žádné edilines.

        $score = app(ScoringService::class)->scoreEdi($head);

        $this->assertSame(0, $score->pocet);
        $this->assertSame(0, $score->boduZaQso);
        $this->assertSame(1, $score->nasobice, 'Vlastní čtverec vždy počítá jako násobič');
        $this->assertSame(0, $score->body);
    }

    public function test_score_edi_all_qsos_outside_window_gives_zero_points(): void
    {
        $head = Edihead::create([
            't_date' => '20260118;20260118', 'p_call' => 'OK1TEST', 'p_wwlo' => 'JN99AJ',
            'p_sect' => '', 'p_band' => '', 'r_name' => '', 'r_phon' => '', 'r_emai' => '', 's_powe' => 100,
        ]);
        Ediline::insert([
            ['edihead_id' => $head->id, 'qso_at' => '2026-01-18 07:59:00', 'call_sign' => 'A', 'received_wwl' => 'JN89AA', 'qso_points' => 3],
            ['edihead_id' => $head->id, 'qso_at' => '2026-01-18 11:01:00', 'call_sign' => 'B', 'received_wwl' => 'JO70AA', 'qso_points' => 4],
            ['edihead_id' => $head->id, 'qso_at' => '2026-01-18 12:30:00', 'call_sign' => 'C', 'received_wwl' => 'JN88AA', 'qso_points' => 3],
        ]);

        $score = app(ScoringService::class)->scoreEdi($head);

        $this->assertSame(0, $score->pocet);
        $this->assertSame(0, $score->boduZaQso);
        $this->assertSame(1, $score->nasobice);
        $this->assertSame(0, $score->body);
    }

    public function test_score_edi_window_boundary_times_are_inclusive(): void
    {
        // 0800 a 1100 musí být započteny (BETWEEN je inclusive na obou koncích).
        // 0759 a 1101 musí být vyřazeny.
        $head = Edihead::create([
            't_date' => '20260118;20260118', 'p_call' => 'OK1TEST', 'p_wwlo' => 'JN99AJ',
            'p_sect' => '', 'p_band' => '', 'r_name' => '', 'r_phon' => '', 'r_emai' => '', 's_powe' => 100,
        ]);
        Ediline::insert($this->withExchange([
            ['edihead_id' => $head->id, 'qso_at' => '2026-01-18 07:59:00', 'call_sign' => 'A', 'received_wwl' => 'JN89AA', 'qso_points' => 0],
            ['edihead_id' => $head->id, 'qso_at' => '2026-01-18 08:00:00', 'call_sign' => 'B', 'received_wwl' => 'JO70AA', 'qso_points' => 0],
            ['edihead_id' => $head->id, 'qso_at' => '2026-01-18 11:00:00', 'call_sign' => 'C', 'received_wwl' => 'JN88AA', 'qso_points' => 0],
            ['edihead_id' => $head->id, 'qso_at' => '2026-01-18 11:01:00', 'call_sign' => 'D', 'received_wwl' => 'JN77AA', 'qso_points' => 0],
        ]));

        $score = app(ScoringService::class)->scoreEdi($head);

        // Pouze B (0800) a C (1100) jsou v okně.
        // JO70: 2 pásy od JN99 = 4 body; JN88: 2 pásy od JN99 = 4 body.
        // pocet=2, boduZaQso=8, nasobice=2 (JO70, JN88) + 1 (JN99 vlastní) = 3, body=24.
        $this->assertSame(2, $score->pocet, 'Pouze QSO přesně na 0800 a 1100 se počítají');
        $this->assertSame(3, $score->nasobice);
    }

    public function test_score_edi_empty_home_locator_still_scores(): void
    {
        // Prázdné PWWLo: domácí čtverec = '' → Maidenhead::qsoPoints vrací 0 za vlastní,
        // ale QSO ve vzdálených čtvercích se stále počítají.
        $head = Edihead::create([
            't_date' => '20260118;20260118', 'p_call' => 'OK1TEST', 'p_wwlo' => '',
            'p_sect' => '', 'p_band' => '', 'r_name' => '', 'r_phon' => '', 'r_emai' => '', 's_powe' => 100,
        ]);
        Ediline::insert($this->withExchange([
            ['edihead_id' => $head->id, 'qso_at' => '2026-01-18 08:30:00', 'call_sign' => 'A', 'received_wwl' => 'JN89AA', 'qso_points' => 0],
        ]));

        $score = app(ScoringService::class)->scoreEdi($head);

        // Prázdný domácí čtverec → nasobice=1 (jen 1 cizí čtverec, vlastní '' se nepřidá).
        $this->assertSame(1, $score->pocet);
        $this->assertSame(1, $score->nasobice);
    }

    public function test_yearly_results_use_datum_konani_year_not_nazev(): void
    {
        $kat = $this->kategorie();
        // Kolo s chybným rokem v názvu – rozhodovat musí rok `datum_konani`.
        $kolo = VkvpaKola::create([
            'datum_konani' => '2026-05-17',
            'datum_uzaverky' => '2026-05-31',
            'nazev' => '5. kolo 2025',
            'poznamka' => '',
        ]);
        $e = $this->entry($kolo->id, $kat->id, 'OK1A', 100);
        $e->update(['poradi' => 1, 'edihead_id' => 1]);

        $scoring = app(ScoringService::class);
        $this->assertNotNull($scoring->yearlyResults(2026)->firstWhere('znacka', 'OK1A'));
        $this->assertNull($scoring->yearlyResults(2025)->firstWhere('znacka', 'OK1A'));
    }

    public function test_yearly_results_aggregates_by_callsign(): void
    {
        $kat = $this->kategorie();
        $k1 = $this->kolo('1. kolo 2026');
        $k2 = $this->kolo('2. kolo 2026');
        $e1 = $this->entry($k1->id, $kat->id, 'OK1A', 100);
        $e2 = $this->entry($k2->id, $kat->id, 'OK1A', 150);
        $e1->update(['poradi' => 1, 'edihead_id' => 1]);
        $e2->update(['poradi' => 1, 'edihead_id' => 1]);

        $res = app(ScoringService::class)->yearlyResults(2026);

        $row = $res->firstWhere('znacka', 'OK1A');
        $this->assertNotNull($row);
        $this->assertSame(250, (int) $row->celkem);
    }

    /**
     * Kdyby v jednom měsíci omylem existovala dvě kola (unique je jen na celý
     * den), TDate se musí deterministicky spárovat s nejstarším z nich.
     */
    public function test_kolo_for_tdate_picks_earliest_round_of_month(): void
    {
        $pozdejsi = VkvpaKola::create([
            'datum_konani' => '2031-07-20', 'datum_uzaverky' => now()->addDays(5),
            'nazev' => 'Červenec B', 'poznamka' => '',
        ]);
        $drivejsi = VkvpaKola::create([
            'datum_konani' => '2031-07-06', 'datum_uzaverky' => now()->addDays(5),
            'nazev' => 'Červenec A', 'poznamka' => '',
        ]);

        $id = app(ScoringService::class)->koloForTDate('20310720;20310720');

        $this->assertSame($drivejsi->id, $id);
        $this->assertNotSame($pozdejsi->id, $id);
    }
}
