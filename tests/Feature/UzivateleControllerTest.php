<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\Admin\UzivateleController;
use App\Models\EdiCategory;
use App\Models\EdiEntry;
use App\Models\EdiRound;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Admin přehled kontaktních / osobních údajů závodníků z edi_entries.
 *
 * @see UzivateleController
 */
class UzivateleControllerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create(['name' => 'Admin', 'password' => Hash::make('x'), 'is_admin' => true]);
    }

    private int $koloSeq = 0;

    /** @param  array<string, mixed>  $overrides */
    private function zaznam(array $overrides = []): EdiEntry
    {
        $this->koloSeq++;
        $kolo = EdiRound::create([
            'starts_at' => now()->subDays(2)->addMinutes($this->koloSeq),
            'closes_at' => now()->addDays(3),
            'name' => '05/2026',
            'note' => '',
        ]);
        $kat = EdiCategory::create(['name' => '144 MHz single op '.$this->koloSeq, 'band' => 'A'.$this->koloSeq, 'section' => 'SO', 'variant' => 'domestic']);

        return EdiEntry::create(array_merge([
            'round_id' => $kolo->id, 'category_id' => $kat->id, 'callsign' => 'OK1TEST',
            'locator' => 'JN99AJ', 'qso_count' => 10, 'multiplier' => 5, 'points' => 50,
            'qso_points' => 0, 'approved' => true, 'sent' => false,
            'name' => 'Jan Novák', 'email' => 'jan@example.com', 'phone' => '+420 777 123 456',
        ], $overrides));
    }

    public function test_index_requires_admin(): void
    {
        $this->get(route('uzivatele.index'))
            ->assertRedirect(route('login'));
    }

    public function test_index_shows_contact_data_for_admin(): void
    {
        $this->zaznam();

        $this->actingAs($this->admin())
            ->get(route('uzivatele.index'))
            ->assertOk()
            ->assertSee('OK1TEST')
            ->assertSee('jan@example.com')
            ->assertSee('+420 777 123 456');
    }

    public function test_search_filters_records(): void
    {
        $this->zaznam(['callsign' => 'OK1AAA', 'email' => 'aaa@example.com']);
        $this->zaznam(['callsign' => 'OK2BBB', 'email' => 'bbb@example.com']);

        $this->actingAs($this->admin())
            ->get(route('uzivatele.index', ['q' => 'OK1AAA']))
            ->assertOk()
            ->assertSee('OK1AAA')
            ->assertDontSee('OK2BBB');
    }
}
