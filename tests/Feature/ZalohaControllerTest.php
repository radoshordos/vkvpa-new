<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\Admin\ZalohaController;
use App\Models\Edihead;
use App\Models\User;
use App\Models\VkvpaKola;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Admin – SQL záloha závodních tabulek (schéma + data).
 *
 * @see ZalohaController
 */
class ZalohaControllerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create(['name' => 'Admin', 'password' => Hash::make('x'), 'is_admin' => true]);
    }

    private function seedKolo(): VkvpaKola
    {
        $kolo = VkvpaKola::create([
            'datum_konani' => '2026-01-17 08:00:00',
            'datum_uzaverky' => '2026-02-01 23:59:00',
            'nazev' => 'Testovací kolo',
            'poznamka' => '',
        ]);

        Edihead::create([
            'id_kola' => $kolo->id, 't_date' => '20260117', 'p_call' => "OK1'ABC",
            'p_wwlo' => 'JN79', 'p_sect' => '', 'p_band' => '144MHz', 'r_name' => 'X',
            's_powe' => 10, 'src' => 'PCall=OK1ABC',
        ]);

        return $kolo;
    }

    public function test_index_requires_admin(): void
    {
        $this->get(route('zaloha.index'))->assertRedirect(route('login'));
    }

    public function test_download_requires_admin(): void
    {
        $this->post(route('zaloha.download'), ['tables' => ['edihead']])
            ->assertRedirect(route('login'));
    }

    public function test_index_lists_tables_with_row_counts(): void
    {
        $this->seedKolo();

        $this->actingAs($this->admin())
            ->get(route('zaloha.index'))
            ->assertOk()
            ->assertSee('edihead')
            ->assertSee('vkvpa_kola');
    }

    public function test_download_streams_sql_with_schema_and_data(): void
    {
        $this->seedKolo();

        $response = $this->actingAs($this->admin())
            ->post(route('zaloha.download'), ['tables' => ['vkvpa_kola', 'edihead']]);

        $response->assertOk();
        $response->assertHeader('content-type', 'application/sql; charset=utf-8');
        $this->assertStringContainsString('.sql', (string) $response->headers->get('content-disposition'));

        $sql = $response->streamedContent();

        $this->assertStringContainsString('DROP TABLE IF EXISTS `edihead`;', $sql);
        $this->assertStringContainsString('CREATE TABLE', $sql);
        $this->assertStringContainsString('INSERT INTO `edihead`', $sql);
        $this->assertStringContainsString('Testovací kolo', $sql);
        // Apostrof ve značce musí být bezpečně escapovaný (ne rozbít literál).
        $this->assertStringContainsString("OK1''ABC", $sql);
    }

    public function test_download_gzip_returns_decompressible_sql(): void
    {
        $this->seedKolo();

        $response = $this->actingAs($this->admin())
            ->post(route('zaloha.download'), ['tables' => ['vkvpa_kola'], 'gzip' => '1']);

        $response->assertOk();
        $response->assertHeader('content-type', 'application/gzip');
        $this->assertStringContainsString('.sql.gz', (string) $response->headers->get('content-disposition'));

        $sql = gzdecode($response->streamedContent());

        $this->assertIsString($sql);
        $this->assertStringContainsString('CREATE TABLE', $sql);
        $this->assertStringContainsString('Testovací kolo', $sql);
    }

    public function test_download_rejects_table_outside_allowlist(): void
    {
        $this->seedKolo();

        $this->actingAs($this->admin())
            ->post(route('zaloha.download'), ['tables' => ['users']])
            ->assertSessionHasErrors('tables.0');
    }

    public function test_download_requires_at_least_one_table(): void
    {
        $this->actingAs($this->admin())
            ->post(route('zaloha.download'), ['tables' => []])
            ->assertSessionHasErrors('tables');
    }
}
