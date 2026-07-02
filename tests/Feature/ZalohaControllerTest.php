<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\Admin\ZalohaController;
use App\Models\EdiHead;
use App\Models\EdiRound;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        return $this->makeUser('Admin', isAdmin: true);
    }

    private function seedKolo(): EdiRound
    {
        $kolo = EdiRound::create([
            'starts_at' => '2026-01-17 08:00:00',
            'closes_at' => '2026-02-01 23:59:00',
            'name' => 'Testovací kolo',
            'note' => '',
        ]);

        EdiHead::create([
            'round_id' => $kolo->id, 't_date' => '20260117', 'p_call' => "OK1'ABC",
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
        $this->post(route('zaloha.download'), ['tables' => ['edi_heads']])
            ->assertRedirect(route('login'));
    }

    public function test_index_lists_tables_with_row_counts(): void
    {
        $this->seedKolo();

        $this->actingAs($this->admin())
            ->get(route('zaloha.index'))
            ->assertOk()
            ->assertSee('migrations')
            ->assertSee('users')
            ->assertSee('edi_bands')
            ->assertSee('edi_prefixes')
            ->assertSee('edi_heads')
            ->assertSee('edi_rounds');
    }

    public function test_download_streams_sql_with_schema_and_data(): void
    {
        $this->seedKolo();

        $response = $this->actingAs($this->admin())
            ->post(route('zaloha.download'), ['tables' => ['edi_rounds', 'edi_heads']]);

        $response->assertOk();
        $response->assertHeader('content-type', 'application/sql; charset=utf-8');
        $this->assertStringContainsString('.sql', (string) $response->headers->get('content-disposition'));

        $sql = $response->streamedContent();

        $this->assertStringContainsString('DROP TABLE IF EXISTS `edi_heads`;', $sql);
        $this->assertStringContainsString('CREATE TABLE', $sql);
        $this->assertStringContainsString('INSERT INTO `edi_heads`', $sql);
        $this->assertStringContainsString('Testovací kolo', $sql);
        // Apostrof ve značce musí být bezpečně escapovaný (ne rozbít literál).
        $this->assertStringContainsString("OK1''ABC", $sql);
    }

    public function test_download_gzip_returns_decompressible_sql(): void
    {
        $this->seedKolo();

        $response = $this->actingAs($this->admin())
            ->post(route('zaloha.download'), ['tables' => ['edi_rounds'], 'gzip' => '1']);

        $response->assertOk();
        $response->assertHeader('content-type', 'application/gzip');
        $this->assertStringContainsString('.sql.gz', (string) $response->headers->get('content-disposition'));

        $sql = gzdecode($response->streamedContent());

        $this->assertIsString($sql);
        $this->assertStringContainsString('CREATE TABLE', $sql);
        $this->assertStringContainsString('Testovací kolo', $sql);
    }

    public function test_download_streams_recovery_metadata_and_lookup_tables(): void
    {
        $response = $this->actingAs($this->admin())
            ->post(route('zaloha.download'), [
                'tables' => ['migrations', 'users', 'login_tokens', 'edi_bands', 'edi_categories', 'edi_prefixes'],
            ]);

        $response->assertOk();

        $sql = $response->streamedContent();

        $this->assertStringContainsString('DROP TABLE IF EXISTS `migrations`;', $sql);
        $this->assertStringContainsString('DROP TABLE IF EXISTS `users`;', $sql);
        $this->assertStringContainsString('DROP TABLE IF EXISTS `login_tokens`;', $sql);
        $this->assertStringContainsString('DROP TABLE IF EXISTS `edi_bands`;', $sql);
        $this->assertStringContainsString('DROP TABLE IF EXISTS `edi_categories`;', $sql);
        $this->assertStringContainsString('DROP TABLE IF EXISTS `edi_prefixes`;', $sql);
        $this->assertStringContainsString('INSERT INTO `users`', $sql);
        $this->assertStringContainsString('INSERT INTO `edi_bands`', $sql);
        $this->assertStringContainsString('INSERT INTO `edi_categories`', $sql);
    }

    public function test_download_rejects_table_outside_allowlist(): void
    {
        $this->seedKolo();

        $this->actingAs($this->admin())
            ->post(route('zaloha.download'), ['tables' => ['sessions']])
            ->assertSessionHasErrors('tables.0');
    }

    public function test_download_requires_at_least_one_table(): void
    {
        $this->actingAs($this->admin())
            ->post(route('zaloha.download'), ['tables' => []])
            ->assertSessionHasErrors('tables');
    }
}
