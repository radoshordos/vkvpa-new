<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\Admin\ExportController;
use App\Models\Edihead;
use App\Models\User;
use App\Models\VkvpaKola;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Admin – Export EDI deníků po kolech v ZIP archivu.
 *
 * @see ExportController
 */
class ExportControllerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create(['name' => 'Admin', 'password' => Hash::make('x'), 'is_admin' => true]);
    }

    private function makeKolo(): VkvpaKola
    {
        return VkvpaKola::create([
            'datum_konani' => '2026-01-17 08:00:00',
            'datum_uzaverky' => '2026-02-01 23:59:00',
            'nazev' => 'Testovací kolo',
            'poznamka' => '',
        ]);
    }

    public function test_index_requires_admin(): void
    {
        $this->get(route('export.index'))->assertRedirect(route('login'));
    }

    public function test_index_lists_round_with_log_count(): void
    {
        $kolo = $this->makeKolo();
        Edihead::create([
            'id_kola' => $kolo->id, 't_date' => '20260117', 'p_call' => 'OK1ABC',
            'p_wwlo' => 'JN79', 'p_sect' => '', 'p_band' => '144MHz', 'r_name' => 'X',
            's_powe' => 10, 'src' => 'PCall=OK1ABC',
        ]);

        $this->actingAs($this->admin())
            ->get(route('export.index'))
            ->assertOk()
            ->assertSee('Testovací kolo');
    }

    public function test_download_returns_zip(): void
    {
        $kolo = $this->makeKolo();
        Edihead::create([
            'id_kola' => $kolo->id, 't_date' => '20260117', 'p_call' => 'OK1ABC',
            'p_wwlo' => 'JN79', 'p_sect' => '', 'p_band' => '144MHz', 'r_name' => 'X',
            's_powe' => 10, 'src' => 'PCall=OK1ABC',
        ]);

        $this->actingAs($this->admin())
            ->get(route('export.download', $kolo->id))
            ->assertOk()
            ->assertHeader('content-type', 'application/zip');
    }

    public function test_download_404_when_no_logs(): void
    {
        $kolo = $this->makeKolo();

        $this->actingAs($this->admin())
            ->get(route('export.download', $kolo->id))
            ->assertNotFound();
    }
}
