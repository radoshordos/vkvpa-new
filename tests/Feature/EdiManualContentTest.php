<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Obsahové kontroly admin manuálu k EDI importu.
 */
class EdiManualContentTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::create([
            'name' => 'AdminManualContent',
            'email' => 'manual-content@example.com',
            'password' => Hash::make('tajne-heslo'),
        ]);
        $user->forceFill(['is_admin' => true])->save();

        return $user;
    }

    public function test_route_is_registered(): void
    {
        $this->assertTrue(Route::has('edi.manual'));
    }

    public function test_guest_is_redirected_from_manual(): void
    {
        $this->get(route('edi.manual'))
            ->assertRedirect(route('login'));
    }

    public function test_admin_can_open_manual_from_menu_and_see_sante_edges(): void
    {
        $this->actingAs($this->admin())
            ->get(route('edi.manual'))
            ->assertOk()
            ->assertSee('Základní struktura souboru')
            ->assertSee('QSO záznam - 15 polí')
            ->assertSee('SPowe=2,5')
            ->assertSee('SPowe=0.25')
            ->assertSee('edi_heads.s_powe')
            ->assertSee('Anténa')
            ->assertSee('SAnte=2x9 el. F9FT+20 el. yagi')
            ->assertSee('SAnte=antena=poznámka')
            ->assertSee('edi_heads.s_ante')
            ->assertSee('edi_heads.src')
            ->assertSee('varchar(100)')
            ->assertSee('Date;Time;Call;Mode;SentRST;SentNr;RcvdRST;RcvdNr;RcvdExch;RcvdWWL;QSO-Points;NewExch;NewWWL;NewDXCC;Duplicate')
            ->assertSee(route('edi.manual'), escape: false);
    }
}
