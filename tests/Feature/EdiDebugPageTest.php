<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\Admin\EdiDebugController;
use App\Models\Edihead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Admin stránka „EDI debug" – přístup a analýza nahraného deníku.
 *
 * @see EdiDebugController
 */
class EdiDebugPageTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create(['name' => 'Admin', 'password' => Hash::make('x'), 'is_admin' => true]);
    }

    public function test_admin_sees_upload_form(): void
    {
        $this->actingAs($this->admin())
            ->get(route('edi.debug.create'))
            ->assertOk()
            ->assertSee('EDI debug');
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('edi.debug.create'))->assertRedirect(route('login'));
    }

    public function test_admin_uploads_edi_and_sees_breakdown(): void
    {
        $content = (string) file_get_contents(__DIR__.'/../fixtures/sample.edi');
        $file = UploadedFile::fake()->createWithContent('sample.edi', $content);

        $this->actingAs($this->admin())
            ->post(route('edi.debug.store'), ['upload' => $file])
            ->assertOk()
            ->assertSee('body za spojení') // skóre headline se vykreslil
            ->assertSee('započteno');     // tabulka rozpadu
    }

    public function test_show_from_db_hides_upload_form(): void
    {
        $src = (string) file_get_contents(__DIR__.'/../fixtures/sample.edi');
        $head = Edihead::create([
            'id_kola' => 1,
            't_date' => '20260315',
            'p_call' => 'OK1AAA',
            'p_wwlo' => 'JN79',
            'p_band' => '144 MHz',
            'r_name' => 'A',
            'r_emai' => 'a@a.cz',
            's_powe' => 100,
            'src' => $src,
        ]);

        $this->actingAs($this->admin())
            ->get(route('edi.debug.show', $head))
            ->assertOk()
            ->assertSee('body za spojení')
            ->assertDontSee('enctype="multipart/form-data"', false);
    }

    public function test_invalid_file_shows_error(): void
    {
        $file = UploadedFile::fake()->createWithContent('bad.edi', "[REG1TEST;1]\nPCall=OK1ABC\n[QSORecords;2]\nnonsense\n[END;]\n");

        $this->actingAs($this->admin())
            ->post(route('edi.debug.store'), ['upload' => $file])
            ->assertRedirect()
            ->assertSessionHasErrors('upload');
    }
}
