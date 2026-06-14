<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Edihead;
use App\Models\Ediline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Test zpětného doplnění edilines.qso_at příkazem edilines:backfill-qso-at.
 */
class BackfillEdilineQsoAtTest extends TestCase
{
    use RefreshDatabase;

    private const string SRC = "[REG1TEST;1]\n"
        ."TDate=20260315;20260315\nPCall=OK2KJT\nPWWLo=JN99BP\n"
        ."[QSORecords;2]\n"
        ."260315;0800;OK2IMH;1;59;001;59;001;;JN99BP;2;;;;\n"
        ."260315;0801;OK2IWU;1;59;002;59;002;;JN89PV;3;;;;\n"
        ."[END;]\n";

    /** Legacy deník (prázdné date/time) se obnoví ze src podle pořadí lokátorů. */
    public function test_backfills_legacy_lines_from_src(): void
    {
        $head = Edihead::create(['t_date' => '20260315', 'p_call' => 'OK2KJT', 'p_wwlo' => 'JN99BP', 'r_name' => 'Test', 'r_hbbs' => '', 's_powe' => 5, 'src' => self::SRC]);

        // Snapshot legacy: lokátor sedí, ale date/time/mode/qso_at chybí.
        Ediline::insert([
            ['edihead_id' => $head->id, 'received_wwl' => 'JN99BP'],
            ['edihead_id' => $head->id, 'received_wwl' => 'JN89PV'],
        ]);

        $this->artisan('edilines:backfill-qso-at')->assertSuccessful();

        $lines = Ediline::where('edihead_id', $head->id)->orderBy('id')->get();

        $this->assertSame('0800', $lines[0]->time);
        $this->assertSame('260315', $lines[0]->date);
        $this->assertSame(1, $lines[0]->mode_code);
        $this->assertSame('2026-03-15 08:00:00', $lines[0]->qso_at?->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-03-15 08:01:00', $lines[1]->qso_at?->utc()->format('Y-m-d H:i:s'));
    }

    /** Moderní deník (date/time vyplněné) jen složí qso_at, src se nečte. */
    public function test_backfills_qso_at_from_existing_date_time(): void
    {
        $head = Edihead::create(['t_date' => '20260315', 'p_call' => 'OK2KJT', 'p_wwlo' => 'JN99BP', 'r_name' => 'Test', 'r_hbbs' => '', 's_powe' => 5, 'src' => '']);

        Ediline::insert([
            ['edihead_id' => $head->id, 'date' => '260315', 'time' => '0945', 'received_wwl' => 'JN99BP'],
        ]);

        $this->artisan('edilines:backfill-qso-at')->assertSuccessful();

        $line = Ediline::where('edihead_id', $head->id)->firstOrFail();
        $this->assertSame('2026-03-15 09:45:00', $line->qso_at?->utc()->format('Y-m-d H:i:s'));
    }

    /** Nekonzistentní deník (src bez date/time nelze spárovat) se přeskočí. */
    public function test_skips_when_unrecoverable(): void
    {
        $head = Edihead::create(['t_date' => '20260315', 'p_call' => 'OK2KJT', 'p_wwlo' => 'JN99BP', 'r_name' => 'Test', 'r_hbbs' => '', 's_powe' => 5, 'src' => '']);
        Ediline::insert([['edihead_id' => $head->id, 'received_wwl' => 'JN99BP']]);

        $this->artisan('edilines:backfill-qso-at')->assertSuccessful();

        $this->assertNull(Ediline::where('edihead_id', $head->id)->firstOrFail()->qso_at);
    }

    /** Dry-run nic nezapíše. */
    public function test_dry_run_does_not_write(): void
    {
        $head = Edihead::create(['t_date' => '20260315', 'p_call' => 'OK2KJT', 'p_wwlo' => 'JN99BP', 'r_name' => 'Test', 'r_hbbs' => '', 's_powe' => 5, 'src' => self::SRC]);
        Ediline::insert([
            ['edihead_id' => $head->id, 'received_wwl' => 'JN99BP'],
            ['edihead_id' => $head->id, 'received_wwl' => 'JN89PV'],
        ]);

        $this->artisan('edilines:backfill-qso-at', ['--dry-run' => true])->assertSuccessful();

        $this->assertNull(Ediline::where('edihead_id', $head->id)->orderBy('id')->firstOrFail()->qso_at);
    }
}
