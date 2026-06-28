<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\EdiPorovnaniController;
use App\Models\EdiCategory;
use App\Models\EdiEntry;
use App\Models\Edihead;
use App\Models\Ediline;
use App\Models\EdiRound;
use App\Models\User;
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

        // Porovnávací grafy: tempo po 15 minutách a směrová růžice obou stanic.
        $this->assertStringContainsString('"mine"', $compact);
        $this->assertStringContainsString('chartTimeline', $html);
        $this->assertStringContainsString('chartAzimuth', $html);
    }

    public function test_charts_hidden_without_selected_rival(): void
    {
        // Bez zvoleného soupeře nejsou data porovnávacích grafů ani plátna.
        [$head] = $this->seedRound();

        $html = $this->actingAs($this->user())
            ->get(route('edi.porovnani', $head->id))
            ->assertOk()
            ->getContent() ?: '';

        $compact = str_replace(' ', '', $html);
        $this->assertStringContainsString('timeline:null', $compact);
        $this->assertStringContainsString('azimuth:null', $compact);
        $this->assertStringNotContainsString('chartTimeline', $html);
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
        [$head, $rival] = $this->seedRound(otevrene: true);

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
    private function seedRound(bool $otevrene = false): array
    {
        $kolo = EdiRound::create([
            'starts_at' => '2026-03-15 08:00:00',
            // Otevřené kolo = uzávěrka v budoucnu (stav Příjem), jinak vyhodnocené.
            'closes_at' => $otevrene ? now()->addDay()->toDateTimeString() : '2026-03-20 23:59:59',
            'name' => '03/2026',
            'note' => '',
            'evaluated_at' => $otevrene ? null : '2026-03-21 10:00:00',
        ]);

        $katA = EdiCategory::create(['name' => '144 MHz', 'section' => 'SO', 'variant' => 'domestic']);
        $katB = EdiCategory::create(['name' => '432 MHz', 'section' => 'SO', 'variant' => 'domestic']);

        $head = $this->importSample();
        $head->update(['round_id' => $kolo->id]);

        $rival = Edihead::create([
            'round_id' => $kolo->id, 't_date' => '20260315', 'p_call' => 'OK1BBB', 'p_wwlo' => 'JN89',
            'p_band' => '144 MHz', 'r_name' => 'B', 'r_emai' => 'b@b.cz', 's_powe' => 100,
        ]);
        Ediline::create(['edihead_id' => $rival->id, 'qso_at' => '2026-03-15 08:30:00', 'call_sign' => 'OK2IMH', 'received_wwl' => 'JN99BP']);
        Ediline::create(['edihead_id' => $rival->id, 'qso_at' => '2026-03-15 08:31:00', 'call_sign' => 'OK9ZZZ', 'received_wwl' => 'JO60AA']);

        $other = Edihead::create([
            'round_id' => $kolo->id, 't_date' => '20260315', 'p_call' => 'OK7CCC', 'p_wwlo' => 'JO70AA',
            'p_band' => '432 MHz', 'r_name' => 'C', 'r_emai' => 'c@c.cz', 's_powe' => 100,
        ]);

        foreach ([
            [$head, $katA->id, 'OK2KJT', 'JN99AJ'],
            [$rival, $katA->id, 'OK1BBB', 'JN89'],
            [$other, $katB->id, 'OK7CCC', 'JO70AA'],
        ] as [$h, $katId, $znacka, $locator]) {
            EdiEntry::create([
                'round_id' => $kolo->id, 'category_id' => $katId,
                'qrp' => false, 'lp' => false, 'callsign' => $znacka, 'locator' => $locator,
                'qso_count' => 2, 'qso_points' => 7, 'multiplier' => 2, 'points' => 14,
                'name' => 'Test', 'email' => 't@t.cz', 'phone' => '', 'note' => '',
                'soapbox' => '', 'ip' => '', 'edi_head_id' => $h->id,
                'rank' => 1, 'approved' => true, 'session_id' => '',
            ]);
        }

        return [$head, $rival, $other];
    }
}
