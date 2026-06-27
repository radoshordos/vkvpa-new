<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Severity;
use App\Models\EdiCategory;
use App\Models\Edihead;
use App\Models\Ediline;
use App\Models\VkvpaData;
use App\Models\VkvpaKola;
use App\Services\Admin\AdminEntryChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminEntryCheckerTest extends TestCase
{
    use RefreshDatabase;

    private AdminEntryChecker $checker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->checker = new AdminEntryChecker;
    }

    private function kolo(): VkvpaKola
    {
        return VkvpaKola::create([
            'datum_konani' => '2026-03-15 08:00:00',
            'datum_uzaverky' => '2026-03-15 11:00:00',
            'nazev' => 'Testovací kolo',
            'poznamka' => '',
        ]);
    }

    private function kat(): EdiCategory
    {
        return EdiCategory::create(['name' => '144 MHz', 'band' => 'A', 'section' => 'SO', 'variant' => 'domestic']);
    }

    /** @param array<string, mixed> $overrides */
    private function entry(VkvpaKola $kolo, EdiCategory $kat, array $overrides = []): VkvpaData
    {
        return VkvpaData::create(array_merge([
            'id_kola' => $kolo->id,
            'id_kategorie' => $kat->id,
            'znacka' => 'OK1TEST',
            'locator' => 'JN79FX',
            'jmeno' => 'Jan Novák',
            'mail' => 'test@example.com',
            'telefon' => '',
            'pocet' => 0,
            'bodu_za_qso' => 0,
            'nasobice' => 0,
            'body' => 0,
            'qrp' => false,
            'lp' => false,
            'soapbox' => '',
            'poznamka' => '',
            'schvaleno' => false,
        ], $overrides));
    }

    private function edihead(VkvpaKola $kolo, string $pCall = 'OK1TEST'): Edihead
    {
        return Edihead::create([
            'id_kola' => $kolo->id,
            'p_call' => $pCall,
            'p_wwlo' => 'JN79FX',
            't_date' => '20260315',
            'r_name' => 'Jan Novák',
            'r_emai' => '',
            's_powe' => 50,
        ]);
    }

    private function ediline(Edihead $head, string $callSign, string $time = '0900'): Ediline
    {
        return Ediline::create([
            'edihead_id' => $head->id,
            'call_sign' => $callSign,
            'qso_at' => '2026-03-15 '.substr($time, 0, 2).':'.substr($time, 2, 2).':00',
            'received_wwl' => 'JN89QL',
        ]);
    }

    public function test_no_warnings_for_complete_entry_without_edi(): void
    {
        $kolo = $this->kolo();
        $kat = $this->kat();
        $entry = $this->entry($kolo, $kat);

        $warnings = $this->checker->warnings($entry);

        $this->assertSame([], $warnings);
    }

    public function test_warns_when_name_is_missing(): void
    {
        $kolo = $this->kolo();
        $kat = $this->kat();
        $entry = $this->entry($kolo, $kat, ['jmeno' => '']);

        $warnings = $this->checker->warnings($entry);

        $this->assertCount(1, $warnings);
        $this->assertStringContainsString('jméno', mb_strtolower($warnings[0]->message));
        $this->assertSame(Severity::Warning, $warnings[0]->severity);
    }

    public function test_warns_when_email_is_missing(): void
    {
        $kolo = $this->kolo();
        $kat = $this->kat();
        $entry = $this->entry($kolo, $kat, ['mail' => '']);

        $warnings = $this->checker->warnings($entry);

        $this->assertCount(1, $warnings);
        $this->assertStringContainsString('e-mail', mb_strtolower($warnings[0]->message));
        $this->assertSame(Severity::Warning, $warnings[0]->severity);
    }

    public function test_warns_for_both_missing_name_and_email(): void
    {
        $kolo = $this->kolo();
        $kat = $this->kat();
        $entry = $this->entry($kolo, $kat, ['jmeno' => '', 'mail' => '']);

        $warnings = $this->checker->warnings($entry);

        $this->assertCount(2, $warnings);
    }

    public function test_no_edi_warnings_when_no_edihead_linked(): void
    {
        $kolo = $this->kolo();
        $kat = $this->kat();
        $entry = $this->entry($kolo, $kat, ['edihead_id' => null]);

        $warnings = $this->checker->warnings($entry);

        $this->assertSame([], $warnings);
    }

    public function test_detects_self_qso(): void
    {
        $kolo = $this->kolo();
        $kat = $this->kat();
        $head = $this->edihead($kolo, 'OK1TEST');
        $this->ediline($head, 'OK1TEST', '0930');
        $this->ediline($head, 'OK2XYZ', '0945');
        $entry = $this->entry($kolo, $kat, ['edihead_id' => $head->id]);

        $warnings = $this->checker->warnings($entry);

        $this->assertCount(1, $warnings);
        $this->assertStringContainsString('Self-QSO', $warnings[0]->message);
        $this->assertStringContainsString('09:30', $warnings[0]->message);
        $this->assertSame(Severity::Warning, $warnings[0]->severity);
    }

    public function test_no_self_qso_warning_when_all_calls_are_different(): void
    {
        $kolo = $this->kolo();
        $kat = $this->kat();
        $head = $this->edihead($kolo, 'OK1TEST');
        $this->ediline($head, 'OK2XYZ', '0900');
        $this->ediline($head, 'OK3ABC', '0915');
        $entry = $this->entry($kolo, $kat, ['edihead_id' => $head->id]);

        $warnings = $this->checker->warnings($entry);

        $this->assertSame([], $warnings);
    }

    public function test_operating_rate_warning_triggered_above_threshold(): void
    {
        $kolo = $this->kolo();
        $kat = $this->kat();
        $head = $this->edihead($kolo);
        // 16 unique QSOs all at 09:00 → all fall within any 10-minute window → exceeds threshold of 15
        for ($i = 0; $i < 16; $i++) {
            $this->ediline($head, sprintf('OK%02dXY', $i), '0900');
        }
        $entry = $this->entry($kolo, $kat, ['edihead_id' => $head->id]);

        $warnings = $this->checker->warnings($entry);

        $found = array_filter($warnings, static fn ($w): bool => str_contains($w->message, 'tempo provozu'));
        $this->assertNotEmpty($found);
        $this->assertSame(Severity::Warning, array_values($found)[0]->severity);
    }

    public function test_no_rate_warning_when_qso_spread_over_time(): void
    {
        $kolo = $this->kolo();
        $kat = $this->kat();
        $head = $this->edihead($kolo);
        // 5 QSOs spread 30 minutes apart — well under the threshold of 15 per 10 min
        foreach (['0800', '0810', '0820', '0830', '0840'] as $t) {
            $this->ediline($head, 'OK'.substr($t, 2).'XY', $t);
        }
        $entry = $this->entry($kolo, $kat, ['edihead_id' => $head->id]);

        $warnings = $this->checker->warnings($entry);

        $rateWarnings = array_filter($warnings, static fn ($w): bool => str_contains($w->message, 'tempo provozu'));
        $this->assertEmpty($rateWarnings);
    }

    public function test_cross_check_warning_when_worked_stations_have_logs(): void
    {
        $kolo = $this->kolo();
        $kat = $this->kat();

        $head = $this->edihead($kolo, 'OK1TEST');
        $this->ediline($head, 'OK2XYZ');
        $entry = $this->entry($kolo, $kat, ['edihead_id' => $head->id]);

        // OK2XYZ also submitted its own edihead in the same round
        $this->edihead($kolo, 'OK2XYZ');

        $warnings = $this->checker->warnings($entry);

        $found = array_filter($warnings, static fn ($w): bool => str_contains($w->message, 'Křížová kontrola'));
        $this->assertNotEmpty($found);
        $crossWarning = array_values($found)[0];
        $this->assertStringContainsString('1', $crossWarning->message);
        $this->assertSame(Severity::Info, $crossWarning->severity);
    }

    public function test_no_cross_check_warning_when_no_worked_stations_have_logs(): void
    {
        $kolo = $this->kolo();
        $kat = $this->kat();
        $head = $this->edihead($kolo, 'OK1TEST');
        $this->ediline($head, 'OK9ZZZ');
        $entry = $this->entry($kolo, $kat, ['edihead_id' => $head->id]);
        // OK9ZZZ does NOT have its own edihead

        $warnings = $this->checker->warnings($entry);

        $crossWarnings = array_filter($warnings, static fn ($w): bool => str_contains($w->message, 'Křížová kontrola'));
        $this->assertEmpty($crossWarnings);
    }

    public function test_warns_for_invalid_home_locator(): void
    {
        $kolo = $this->kolo();
        $kat = $this->kat();
        $head = Edihead::create([
            'id_kola' => $kolo->id,
            'p_call' => 'OK1TEST',
            'p_wwlo' => 'ZZ99AJ',  // neplatný lokátor
            't_date' => '20260315',
            'r_name' => 'Jan Novák',
            'r_emai' => '',
            's_powe' => 50,
        ]);
        $entry = $this->entry($kolo, $kat, ['edihead_id' => $head->id]);

        $warnings = $this->checker->warnings($entry);

        $found = array_filter($warnings, static fn ($w): bool => str_contains($w->message, 'PWWLo'));
        $this->assertNotEmpty($found);
        $this->assertSame(Severity::Warning, array_values($found)[0]->severity);
    }

    public function test_warns_for_empty_home_locator(): void
    {
        $kolo = $this->kolo();
        $kat = $this->kat();
        $head = Edihead::create([
            'id_kola' => $kolo->id,
            'p_call' => 'OK1TEST',
            'p_wwlo' => '',  // prázdný lokátor
            't_date' => '20260315',
            'r_name' => 'Jan Novák',
            'r_emai' => '',
            's_powe' => 50,
        ]);
        $entry = $this->entry($kolo, $kat, ['edihead_id' => $head->id]);

        $warnings = $this->checker->warnings($entry);

        $found = array_filter($warnings, static fn ($w): bool => str_contains($w->message, 'PWWLo'));
        $this->assertNotEmpty($found);
        $this->assertSame(Severity::Warning, array_values($found)[0]->severity);
    }

    public function test_warns_for_duplicate_call_signs(): void
    {
        $kolo = $this->kolo();
        $kat = $this->kat();
        $head = $this->edihead($kolo);
        $this->ediline($head, 'OK2XYZ', '0900');
        $this->ediline($head, 'OK2XYZ', '0930');  // duplicitní
        $this->ediline($head, 'OK3ABC', '1000');
        $entry = $this->entry($kolo, $kat, ['edihead_id' => $head->id]);

        $warnings = $this->checker->warnings($entry);

        $found = array_filter($warnings, static fn ($w): bool => str_contains($w->message, 'Duplicitní'));
        $this->assertNotEmpty($found);
        $this->assertSame(Severity::Warning, array_values($found)[0]->severity);
        $this->assertStringContainsString('OK2XYZ', array_values($found)[0]->message);
    }

    public function test_warns_for_invalid_received_locator(): void
    {
        $kolo = $this->kolo();
        $kat = $this->kat();
        $head = $this->edihead($kolo);
        Ediline::create([
            'edihead_id' => $head->id,
            'call_sign' => 'OK2BAD',
            'qso_at' => '2026-03-15 09:00:00',
            'received_wwl' => 'ZZ99XX',  // neplatný Maidenhead
        ]);
        $entry = $this->entry($kolo, $kat, ['edihead_id' => $head->id]);

        $warnings = $this->checker->warnings($entry);

        $found = array_filter($warnings, static fn ($w): bool => str_contains($w->message, 'Neplatný WWL'));
        $this->assertNotEmpty($found);
        $this->assertSame(Severity::Warning, array_values($found)[0]->severity);
        $this->assertStringContainsString('OK2BAD', array_values($found)[0]->message);
    }

    public function test_info_for_out_of_window_qsos(): void
    {
        $kolo = $this->kolo();
        $kat = $this->kat();
        $head = $this->edihead($kolo);
        $this->ediline($head, 'OK2XYZ', '0900');   // v okně
        $this->ediline($head, 'OK3ABC', '1200');   // mimo okno
        $entry = $this->entry($kolo, $kat, ['edihead_id' => $head->id]);

        $warnings = $this->checker->warnings($entry);

        $found = array_filter($warnings, static fn ($w): bool => str_contains($w->message, 'mimo závodní okno'));
        $this->assertNotEmpty($found);
        $this->assertSame(Severity::Info, array_values($found)[0]->severity);
    }
}
