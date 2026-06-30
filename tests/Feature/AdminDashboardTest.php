<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\Admin\DashboardController;
use App\Models\EdiEntry;
use App\Models\EdiRound;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Admin dashboard se sezonními souhrny a grafy.
 *
 * @see DashboardController
 */
class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return $this->makeUser('Admin', isAdmin: true);
    }

    /** @param array<string, mixed> $overrides */
    private function entry(EdiRound $round, int $categoryId, string $callsign, array $overrides = []): EdiEntry
    {
        return EdiEntry::create(array_merge([
            'round_id' => $round->id,
            'category_id' => $categoryId,
            'callsign' => $callsign,
            'locator' => 'JN99AJ',
            'qso_count' => 10,
            'qso_points' => 30,
            'multiplier' => 5,
            'points' => 150,
            'name' => 'Test',
            'approved' => true,
            'sent' => false,
            'rank' => 1,
        ], $overrides));
    }

    public function test_dashboard_renders_and_groups_distribution_by_band(): void
    {
        Cache::flush();

        $round = EdiRound::create([
            'starts_at' => '2026-01-18 08:00:00',
            'closes_at' => '2026-02-01 23:59:00',
            'name' => '01/2026',
            'note' => '',
        ]);

        $this->entry($round, 1, 'OK1AAA', ['points' => 100, 'qso_count' => 8]);  // 144 MHz SO
        $this->entry($round, 2, 'OK1BBB', ['points' => 200, 'qso_count' => 12]); // 144 MHz MO
        $this->entry($round, 3, 'OK1CCC', ['points' => 300, 'qso_count' => 16]); // 432 MHz SO
        $this->entry($round, 4, 'OK1DDD', ['approved' => false, 'rank' => 0]);   // pending, ignored by chart

        $html = $this->actingAs($this->admin())
            ->get(route('admin.dashboard', ['rok' => 2026]))
            ->assertOk()
            ->assertSee('Distribuce pásem 2026')
            ->getContent();

        $this->assertIsString($html);
        $compact = str_replace(' ', '', $html);
        $this->assertStringContainsString('katLabels:["144MHz","432MHz"]', $compact);
        $this->assertStringContainsString('katData:[2,1]', $compact);
    }
}
