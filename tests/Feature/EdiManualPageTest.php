<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;

/**
 * Admin manuál k EDI importu.
 */
class EdiManualPageTest extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    private function admin(): User
    {
        $user = User::create([
            'name' => 'AdminManual',
            'email' => 'manual@example.com',
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

    public function test_admin_can_open_manual_from_menu(): void
    {
        $this->actingAs($this->admin())
            ->get(route('edi.manual'))
            ->assertOk()
            ->assertSee('Základní struktura souboru')
            ->assertSee('QSO záznam - 15 polí')
            ->assertSee('SPowe=2,5')
            ->assertSee('SPowe=0.25')
            ->assertSee('edi_heads.s_powe')
            ->assertSee('Date;Time;Call;Mode;SentRST;SentNr;RcvdRST;RcvdNr;RcvdExch;RcvdWWL;QSO-Points;NewExch;NewWWL;NewDXCC;Duplicate')
            ->assertSee(route('edi.manual'), escape: false);
    }
}
