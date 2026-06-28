<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Severity;
use App\Models\EdiCategory;
use App\Models\EdiEntry;
use App\Models\Edihead;
use App\Models\Ediline;
use App\Models\EdiRound;
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

    private function round(): EdiRound
    {
        return EdiRound::create([
            'starts_at' => '2026-03-15 08:00:00',
            'closes_at' => '2026-03-15 11:00:00',
            'name' => 'Testovací kolo',
            'note' => '',
        ]);
    }

    private function kat(): EdiCategory
    {
        return EdiCategory::create(['name' => '144 MHz', 'section' => 'SO', 'variant' => 'domestic']);
    }

    /** @param array<string, mixed> $overrides */
    private function entry(EdiRound $kolo, EdiCategory $kat, array $overrides = []): EdiEntry
    {
        return EdiEntry::create(array_merge([
            'round_id' => $kolo->id,
            'category_id' => $kat->id,
            'callsign' => 'OK1TEST',
            'locator' => 'JN79FX',
            'name' => 'Jan Novák',
            'email' => 'test@example.com',
            'phone' => '',
            'qso_count' => 0,
            'qso_points' => 0,
            'multiplier' => 0,
            'points' => 0,
            'qrp' => false,
            'lp' => false,
            'soapbox' => '',
            'note' => '',
            'approved' => false,
        ], $overrides));
    }

    private function ediHead(EdiRound $kolo, string $pCall = 'OK1TEST'): Edihead
    {
        return Edihead::create([
            'round_id' => $kolo->id,
            'p_call' => $pCall,
            'p_wwlo' => 'JN79FX',
            't_date' => '20260315',
            'r_name' => 'Jan Novák',
            'r_emai' => '',
            's_powe' => 50,
        ]);
    }

    private function ediline(Edihead $head, string $callSign, string $time = '0900', int $modeCode = 1): Ediline
    {
        return Ediline::create([
            'edihead_id' => $head->id,
            'call_sign' => $callSign,
            'qso_at' => '2026-03-15 '.substr($time, 0, 2).':'.substr($time, 2, 2).':00',
            'received_wwl' => 'JN89QL',
            'mode_code' => $modeCode,
        ]);
    }

    public function test_no_warnings_for_complete_entry_without_edi(): void
    {
        $kolo = $this->round();
        $kat = $this->kat();
        $entry = $this->entry($kolo, $kat);

        $warnings = $this->checker->warnings($entry);

        $this->assertSame([], $warnings);
    }

    public function test_warns_when_name_is_missing(): void
    {
        $kolo = $this->round();
        $kat = $this->kat();
        $entry = $this->entry($kolo, $kat, ['name' => '']);

        $warnings = $this->checker->warnings($entry);

        $this->assertCount(1, $warnings);
        $this->assertStringContainsString('jméno', mb_strtolower($warnings[0]->message));
        $this->assertSame(Severity::Warning, $warnings[0]->severity);
    }

    public function test_warns_when_email_is_missing(): void
    {
        $kolo = $this->round();
        $kat = $this->kat();
        $entry = $this->entry($kolo, $kat, ['email' => '']);

        $warnings = $this->checker->warnings($entry);

        $this->assertCount(1, $warnings);
        $this->assertStringContainsString('e-mail', mb_strtolower($warnings[0]->message));
        $this->assertSame(Severity::Warning, $warnings[0]->severity);
    }

    public function test_warns_for_both_missing_name_and_email(): void
    {
        $kolo = $this->round();
        $kat = $this->kat();
        $entry = $this->entry($kolo, $kat, ['name' => '', 'email' => '']);

        $warnings = $this->checker->warnings($entry);

        $this->assertCount(2, $warnings);
    }

    public function test_no_edi_warnings_when_no_edihead_linked(): void
    {
        $kolo = $this->round();
        $kat = $this->kat();
        $entry = $this->entry($kolo, $kat, ['edi_head_id' => null]);

        $warnings = $this->checker->warnings($entry);

        $this->assertSame([], $warnings);
    }

    public function test_detects_self_qso(): void
    {
        $kolo = $this->round();
        $kat = $this->kat();
        $head = $this->ediHead($kolo, 'OK1TEST');
        $this->ediline($head, 'OK1TEST', '0930');
        $this->ediline($head, 'OK2XYZ', '0945');
        $entry = $this->entry($kolo, $kat, ['edi_head_id' => $head->id]);

        $warnings = $this->checker->warnings($entry);

        $this->assertCount(1, $warnings);
        $this->assertStringContainsString('Self-QSO', $warnings[0]->message);
        $this->assertStringContainsString('09:30', $warnings[0]->message);
        $this->assertSame(Severity::Warning, $warnings[0]->severity);
    }

    public function test_no_self_qso_warning_when_all_calls_are_different(): void
    {
        $kolo = $this->round();
        $kat = $this->kat();
        $head = $this->ediHead($kolo, 'OK1TEST');
        $this->ediline($head, 'OK2XYZ', '0900');
        $this->ediline($head, 'OK3ABC', '0915');
        $entry = $this->entry($kolo, $kat, ['edi_head_id' => $head->id]);

        $warnings = $this->checker->warnings($entry);

        $this->assertSame([], $warnings);
    }

    public function test_operating_rate_warning_triggered_above_threshold(): void
    {
        $kolo = $this->round();
        $kat = $this->kat();
        $head = $this->ediHead($kolo);
        // 16 unique QSOs all at 09:00 → all fall within any 10-minute window → exceeds threshold of 15
        for ($i = 0; $i < 16; $i++) {
            $this->ediline($head, sprintf('OK%02dXY', $i), '0900');
        }
        $entry = $this->entry($kolo, $kat, ['edi_head_id' => $head->id]);

        $warnings = $this->checker->warnings($entry);

        $found = array_filter($warnings, static fn ($w): bool => str_contains($w->message, 'tempo provozu'));
        $this->assertNotEmpty($found);
        $this->assertSame(Severity::Warning, array_values($found)[0]->severity);
    }

    public function test_no_rate_warning_when_qso_spread_over_time(): void
    {
        $kolo = $this->round();
        $kat = $this->kat();
        $head = $this->ediHead($kolo);
        // 5 QSOs spread 30 minutes apart — well under the threshold of 15 per 10 min
        foreach (['0800', '0810', '0820', '0830', '0840'] as $t) {
            $this->ediline($head, 'OK'.substr($t, 2).'XY', $t);
        }
        $entry = $this->entry($kolo, $kat, ['edi_head_id' => $head->id]);

        $warnings = $this->checker->warnings($entry);

        $rateWarnings = array_filter($warnings, static fn ($w): bool => str_contains($w->message, 'tempo provozu'));
        $this->assertEmpty($rateWarnings);
    }

    public function test_cross_check_warning_when_worked_stations_have_logs(): void
    {
        $kolo = $this->round();
        $kat = $this->kat();

        $head = $this->ediHead($kolo, 'OK1TEST');
        $this->ediline($head, 'OK2XYZ');
        $entry = $this->entry($kolo, $kat, ['edi_head_id' => $head->id]);

        // OK2XYZ also submitted its own edihead in the same round
        $this->ediHead($kolo, 'OK2XYZ');

        $warnings = $this->checker->warnings($entry);

        $found = array_filter($warnings, static fn ($w): bool => str_contains($w->message, 'Křížová kontrola'));
        $this->assertNotEmpty($found);
        $crossWarning = array_values($found)[0];
        $this->assertStringContainsString('1', $crossWarning->message);
        $this->assertSame(Severity::Info, $crossWarning->severity);
    }

    public function test_no_cross_check_warning_when_no_worked_stations_have_logs(): void
    {
        $kolo = $this->round();
        $kat = $this->kat();
        $head = $this->ediHead($kolo, 'OK1TEST');
        $this->ediline($head, 'OK9ZZZ');
        $entry = $this->entry($kolo, $kat, ['edi_head_id' => $head->id]);
        // OK9ZZZ does NOT have its own edihead

        $warnings = $this->checker->warnings($entry);

        $crossWarnings = array_filter($warnings, static fn ($w): bool => str_contains($w->message, 'Křížová kontrola'));
        $this->assertEmpty($crossWarnings);
    }

    public function test_warns_for_invalid_home_locator(): void
    {
        $kolo = $this->round();
        $kat = $this->kat();
        $head = Edihead::create([
            'round_id' => $kolo->id,
            'p_call' => 'OK1TEST',
            'p_wwlo' => 'ZZ99AJ',  // neplatný lokátor
            't_date' => '20260315',
            'r_name' => 'Jan Novák',
            'r_emai' => '',
            's_powe' => 50,
        ]);
        $entry = $this->entry($kolo, $kat, ['edi_head_id' => $head->id]);

        $warnings = $this->checker->warnings($entry);

        $found = array_filter($warnings, static fn ($w): bool => str_contains($w->message, 'PWWLo'));
        $this->assertNotEmpty($found);
        $this->assertSame(Severity::Warning, array_values($found)[0]->severity);
    }

    public function test_warns_for_empty_home_locator(): void
    {
        $kolo = $this->round();
        $kat = $this->kat();
        $head = Edihead::create([
            'round_id' => $kolo->id,
            'p_call' => 'OK1TEST',
            'p_wwlo' => '',  // prázdný lokátor
            't_date' => '20260315',
            'r_name' => 'Jan Novák',
            'r_emai' => '',
            's_powe' => 50,
        ]);
        $entry = $this->entry($kolo, $kat, ['edi_head_id' => $head->id]);

        $warnings = $this->checker->warnings($entry);

        $found = array_filter($warnings, static fn ($w): bool => str_contains($w->message, 'PWWLo'));
        $this->assertNotEmpty($found);
        $this->assertSame(Severity::Warning, array_values($found)[0]->severity);
    }

    public function test_warns_for_duplicate_call_signs(): void
    {
        $kolo = $this->round();
        $kat = $this->kat();
        $head = $this->ediHead($kolo);
        $this->ediline($head, 'OK2XYZ', '0900');
        $this->ediline($head, 'OK2XYZ', '0930');  // duplicitní
        $this->ediline($head, 'OK3ABC', '1000');
        $entry = $this->entry($kolo, $kat, ['edi_head_id' => $head->id]);

        $warnings = $this->checker->warnings($entry);

        $found = array_filter($warnings, static fn ($w): bool => str_contains($w->message, 'Duplicitní'));
        $this->assertNotEmpty($found);
        $this->assertSame(Severity::Warning, array_values($found)[0]->severity);
        $this->assertStringContainsString('OK2XYZ', array_values($found)[0]->message);
    }

    public function test_warns_for_invalid_received_locator(): void
    {
        $kolo = $this->round();
        $kat = $this->kat();
        $head = $this->ediHead($kolo);
        Ediline::create([
            'edihead_id' => $head->id,
            'call_sign' => 'OK2BAD',
            'qso_at' => '2026-03-15 09:00:00',
            'received_wwl' => 'ZZ99XX',  // neplatný Maidenhead
            'mode_code' => 1,
        ]);
        $entry = $this->entry($kolo, $kat, ['edi_head_id' => $head->id]);

        $warnings = $this->checker->warnings($entry);

        $found = array_filter($warnings, static fn ($w): bool => str_contains($w->message, 'Neplatný WWL'));
        $this->assertNotEmpty($found);
        $this->assertSame(Severity::Warning, array_values($found)[0]->severity);
        $this->assertStringContainsString('OK2BAD', array_values($found)[0]->message);
    }

    public function test_info_for_invalid_mode_codes(): void
    {
        $kolo = $this->round();
        $kat = $this->kat();
        $head = $this->ediHead($kolo);
        $this->ediline($head, 'OK2XYZ', '0900', 1);    // SSB – ok
        $this->ediline($head, 'OK3ABC', '0915', 59);   // rozhozený RST → chybný mód
        $this->ediline($head, 'OK4DEF', '0930', 7);    // MGM – ve VKV PA nepovolený
        $entry = $this->entry($kolo, $kat, ['edi_head_id' => $head->id]);

        $warnings = $this->checker->warnings($entry);

        $found = array_filter($warnings, static fn ($w): bool => str_contains($w->message, 'Chybný kód druhu provozu'));
        $this->assertNotEmpty($found);
        $w = array_values($found)[0];
        $this->assertSame(Severity::Info, $w->severity);
        $this->assertStringContainsString('2 QSO', $w->message);
        $this->assertStringContainsString('OK3ABC: 59', $w->message);
        $this->assertStringContainsString('OK4DEF: 7', $w->message);
    }

    public function test_no_invalid_mode_warning_when_all_official(): void
    {
        $kolo = $this->round();
        $kat = $this->kat();
        $head = $this->ediHead($kolo);
        $this->ediline($head, 'OK2XYZ', '0900', 1);   // SSB
        $this->ediline($head, 'OK3ABC', '0915', 6);   // FM – povolený
        $entry = $this->entry($kolo, $kat, ['edi_head_id' => $head->id]);

        $warnings = $this->checker->warnings($entry);

        $invalid = array_filter($warnings, static fn ($w): bool => str_contains($w->message, 'Chybný kód druhu provozu'));
        $this->assertEmpty($invalid);
    }

    public function test_info_for_out_of_window_qsos(): void
    {
        $kolo = $this->round();
        $kat = $this->kat();
        $head = $this->ediHead($kolo);
        $this->ediline($head, 'OK2XYZ', '0900');   // v okně
        $this->ediline($head, 'OK3ABC', '1200');   // mimo okno
        $entry = $this->entry($kolo, $kat, ['edi_head_id' => $head->id]);

        $warnings = $this->checker->warnings($entry);

        $found = array_filter($warnings, static fn ($w): bool => str_contains($w->message, 'mimo závodní okno'));
        $this->assertNotEmpty($found);
        $this->assertSame(Severity::Info, array_values($found)[0]->severity);
    }
}
