<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\EdiPorovnaniController;
use App\Models\Edihead;
use App\Models\Ediline;
use App\Models\User;
use App\Models\VkvpaData;
use App\Models\VkvpaKategorie;
use App\Models\VkvpaKola;
use App\Services\Edi\EdiImportService;
use App\Services\Edi\EdiParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Samostatná stránka porovnání dvou deníků (hráč vs. hráč) z téhož kola
 * a téže kategorie.
 *
 * @see EdiPorovnaniController
 */
class EdiPorovnaniTest extends TestCase
{
    use RefreshDatabase;

    private function importSample(): Edihead
    {
        $edi = (string) file_get_contents(__DIR__.'/../fixtures/sample.edi');

        return new EdiImportService()->import(new EdiParser()->parse($edi));
    }

    private function user(): User
    {
        return User::create(['name' => 'Test', 'password' => Hash::make('x'), 'is_admin' => false]);
    }

    private function admin(): User
    {
        return User::create(['name' => 'Admin', 'password' => Hash::make('x'), 'is_admin' => true]);
    }

    public function test_renders_without_round_with_no_rivals(): void
    {
        $head = $this->importSample();

        $html = $this->actingAs($this->user())
            ->get(route('edi.porovnani', $head->id))
            ->assertOk()
            ->getContent() ?: '';

        $this->assertStringContainsString('Porovnání deníků', $html);
        $this->assertStringContainsString('není žádný další deník', $html);
    }

    public function test_compare_renders_for_selected_rival(): void
    {
        // Vyhodnocené kolo, dva deníky v téže kategorii: OK2IMH udělali oba,
        // OK2IWU jen OK2KJT (sample.edi), OK9ZZZ jen soupeř OK1BBB.
        [$head, $rival] = $this->seedRound();

        $html = $this->actingAs($this->user())
            ->get(route('edi.porovnani', ['head' => $head->id, 'porovnat' => $rival->id]))
            ->assertOk()
            ->getContent() ?: '';

        $compact = str_replace(' ', '', $html);
        $this->assertStringContainsString('"rival":"OK1BBB"', $compact);
        $this->assertStringContainsString('"onlyMine"', $compact);
        $this->assertStringContainsString('OK9ZZZ', $html);
        $this->assertStringContainsString('rivalCumulative', $html);
        $this->assertStringContainsString('Udělali oba', $html);
    }

    public function test_rival_select_shown_without_selection(): void
    {
        [$head] = $this->seedRound();

        $html = $this->actingAs($this->user())
            ->get(route('edi.porovnani', $head->id))
            ->assertOk()
            ->getContent() ?: '';

        $this->assertStringContainsString('Porovnat OK2KJT s', $html);
        $this->assertStringContainsString('OK1BBB', $html);
        $this->assertStringContainsString('compare:null', str_replace(' ', '', $html));
    }

    public function test_rivals_limited_to_same_category(): void
    {
        // Deník OK7CCC je v témže kole, ale jiné kategorii → nesmí se nabízet
        // a query parametr s jeho id se ignoruje.
        [$head, , $other] = $this->seedRound();

        $html = $this->actingAs($this->user())
            ->get(route('edi.porovnani', ['head' => $head->id, 'porovnat' => $other->id]))
            ->assertOk()
            ->getContent() ?: '';

        $this->assertStringNotContainsString('OK7CCC', $html);
        $this->assertStringContainsString('compare:null', str_replace(' ', '', $html));
    }

    public function test_compare_hidden_while_round_open(): void
    {
        // Kolo v příjmu hlášení → porovnání by odhalilo soupeřův deník;
        // query parametr se ignoruje a výběr se nenabízí (ani adminovi).
        [$head, $rival] = $this->seedRound(aktivni: true);

        $html = $this->actingAs($this->admin())
            ->get(route('edi.porovnani', ['head' => $head->id, 'porovnat' => $rival->id]))
            ->assertOk()
            ->getContent() ?: '';

        $this->assertStringContainsString('compare:null', str_replace(' ', '', $html));
        $this->assertStringContainsString('po uzávěrce', $html);
        $this->assertStringNotContainsString('OK1BBB', $html);
        $this->assertStringNotContainsString('OK9ZZZ', $html);
    }

    /**
     * Kolo se třemi deníky: sample.edi (OK2KJT) + soupeř OK1BBB v téže
     * kategorii A + OK7CCC v jiné kategorii B. Všechny záznamy schválené.
     *
     * @return array{Edihead, Edihead, Edihead}
     */
    private function seedRound(bool $aktivni = false): array
    {
        $kolo = VkvpaKola::create([
            'datum_konani' => '2026-03-15',
            'datum_uzaverky' => '2026-03-20 23:59:59',
            'nazev' => '03/2026',
            'poznamka' => '',
            'aktivni' => $aktivni,
            'vyhodnoceno' => $aktivni ? null : '2026-03-21 10:00:00',
        ]);

        $katA = VkvpaKategorie::create(['nazev' => '144 MHz', 'popis' => '', 'zkratka' => 'A', 'dxid' => 0]);
        $katB = VkvpaKategorie::create(['nazev' => '432 MHz', 'popis' => '', 'zkratka' => 'B', 'dxid' => 0]);

        $head = $this->importSample();
        $head->update(['id_kola' => $kolo->id]);

        $rival = Edihead::create([
            'id_kola' => $kolo->id, 't_date' => '20260315', 'p_call' => 'OK1BBB', 'p_wwlo' => 'JN89',
            'p_band' => '144 MHz', 'r_name' => 'B', 'r_hbbs' => 'b@b.cz', 's_powe' => 100,
        ]);
        Ediline::create(['edihead_id' => $rival->id, 'date' => '260315', 'time' => '0830', 'call_sign' => 'OK2IMH', 'received_wwl' => 'JN99BP']);
        Ediline::create(['edihead_id' => $rival->id, 'date' => '260315', 'time' => '0831', 'call_sign' => 'OK9ZZZ', 'received_wwl' => 'JO60AA']);

        $other = Edihead::create([
            'id_kola' => $kolo->id, 't_date' => '20260315', 'p_call' => 'OK7CCC', 'p_wwlo' => 'JO70AA',
            'p_band' => '432 MHz', 'r_name' => 'C', 'r_hbbs' => 'c@c.cz', 's_powe' => 100,
        ]);

        foreach ([
            [$head, $katA->id, 'OK2KJT', 'JN99AJ'],
            [$rival, $katA->id, 'OK1BBB', 'JN89'],
            [$other, $katB->id, 'OK7CCC', 'JO70AA'],
        ] as [$h, $katId, $znacka, $locator]) {
            VkvpaData::create([
                'id_kola' => $kolo->id, 'id_kategorie' => $katId,
                'qrp' => false, 'lp' => false, 'znacka' => $znacka, 'locator' => $locator,
                'pocet' => 2, 'bodu_za_qso' => 7, 'nasobice' => 2, 'body' => 14,
                'jmeno' => 'Test', 'mail' => 't@t.cz', 'telefon' => '', 'poznamka' => '',
                'soapbox' => '', 'ip' => '', 'edihead_id' => $h->id,
                'poradi' => 1, 'schvaleno' => true, 'session_id' => '',
            ]);
        }

        return [$head, $rival, $other];
    }
}
