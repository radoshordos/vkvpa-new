<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Prispevek;
use App\Models\User;
use App\Models\VkvpaKola;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DiskuseTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create(['name' => 'Admin', 'password' => Hash::make('x'), 'is_admin' => true]);
    }

    private function kolo(): VkvpaKola
    {
        return VkvpaKola::create([
            'datum_konani' => now()->subDays(3),
            'datum_uzaverky' => now()->addDays(2),
            'nazev' => '05/2026',
            'poznamka' => '',
        ]);
    }

    // ------------------------------------------------------------------
    // index

    public function test_index_redirects_to_latest_kolo(): void
    {
        $kolo = $this->kolo();

        $this->get(route('diskuse.index'))
            ->assertRedirect(route('diskuse.show', $kolo->id));
    }

    public function test_index_redirects_home_when_no_kola_exist(): void
    {
        $this->get(route('diskuse.index'))
            ->assertRedirect(route('home'));
    }

    // ------------------------------------------------------------------
    // show

    public function test_show_renders_view_with_prispevky(): void
    {
        $kolo = $this->kolo();
        Prispevek::create(['kolo_id' => $kolo->id, 'znacka' => 'OK1AB', 'text' => 'Ahoj ze závodu!', 'ip' => '127.0.0.1']);

        $this->get(route('diskuse.show', $kolo->id))
            ->assertOk()
            ->assertSee('OK1AB')
            ->assertSee('Ahoj ze závodu!');
    }

    public function test_show_renders_empty_state_without_prispevky(): void
    {
        $kolo = $this->kolo();

        $this->get(route('diskuse.show', $kolo->id))
            ->assertOk()
            ->assertSee('Buďte první');
    }

    public function test_selectbox_omits_old_kola_without_prispevky(): void
    {
        $aktualni = $this->kolo();
        $stareBez = VkvpaKola::create([
            'datum_konani' => now()->subYears(2),
            'datum_uzaverky' => now()->subYears(2),
            'nazev' => '01/2024',
            'poznamka' => '',
        ]);
        $stareS = VkvpaKola::create([
            'datum_konani' => now()->subYears(3),
            'datum_uzaverky' => now()->subYears(3),
            'nazev' => '01/2023',
            'poznamka' => '',
        ]);
        Prispevek::create(['kolo_id' => $stareS->id, 'znacka' => 'OK1AB', 'text' => 'Starý příspěvek.', 'ip' => '127.0.0.1']);

        $kola = $this->get(route('diskuse.show', $aktualni->id))
            ->assertOk()
            ->viewData('kola');

        $this->assertInstanceOf(Collection::class, $kola);
        $this->assertTrue($kola->contains('id', $aktualni->id));
        $this->assertTrue($kola->contains('id', $stareS->id));
        $this->assertFalse($kola->contains('id', $stareBez->id));
    }

    public function test_selectbox_always_contains_displayed_kolo(): void
    {
        $this->kolo();
        $stare = VkvpaKola::create([
            'datum_konani' => now()->subYears(2),
            'datum_uzaverky' => now()->subYears(2),
            'nazev' => '01/2024',
            'poznamka' => '',
        ]);

        $kola = $this->get(route('diskuse.show', $stare->id))
            ->assertOk()
            ->viewData('kola');

        $this->assertInstanceOf(Collection::class, $kola);
        $this->assertTrue($kola->contains('id', $stare->id));
    }

    // ------------------------------------------------------------------
    // store

    public function test_store_creates_prispevek(): void
    {
        $kolo = $this->kolo();

        $this->post(route('diskuse.store', $kolo->id), [
            'znacka' => 'OK1TEST',
            'text' => 'Testovací příspěvek z jednotkového testu.',
        ])->assertRedirect(route('diskuse.show', $kolo->id))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('diskuse', [
            'kolo_id' => $kolo->id,
            'znacka' => 'OK1TEST',
        ]);
    }

    public function test_store_normalises_znacka_to_uppercase(): void
    {
        $kolo = $this->kolo();

        $this->post(route('diskuse.store', $kolo->id), [
            'znacka' => 'ok1lower',
            'text' => 'Text příspěvku.',
        ]);

        $this->assertDatabaseHas('diskuse', ['znacka' => 'OK1LOWER']);
    }

    public function test_store_accepts_optional_jmeno(): void
    {
        $kolo = $this->kolo();

        $this->post(route('diskuse.store', $kolo->id), [
            'znacka' => 'OK1A',
            'jmeno' => 'Jan Novák',
            'text' => 'Příspěvek s jménem.',
        ]);

        $this->assertDatabaseHas('diskuse', ['znacka' => 'OK1A', 'jmeno' => 'Jan Novák']);
    }

    public function test_store_saves_photo(): void
    {
        Storage::fake('public');
        $kolo = $this->kolo();

        $this->post(route('diskuse.store', $kolo->id), [
            'znacka' => 'OK1FOTO',
            'text' => 'Příspěvek s fotkou.',
            'foto' => UploadedFile::fake()->image('foto.jpg', 200, 200),
        ])->assertRedirect();

        $prispevek = Prispevek::where('znacka', 'OK1FOTO')->firstOrFail();
        $this->assertNotNull($prispevek->foto);
        Storage::disk('public')->assertExists($prispevek->foto);
    }

    public function test_store_requires_znacka(): void
    {
        $kolo = $this->kolo();

        $this->post(route('diskuse.store', $kolo->id), [
            'text' => 'Text bez značky.',
        ])->assertSessionHasErrors('znacka');
    }

    public function test_store_rejects_znacka_with_special_chars(): void
    {
        $kolo = $this->kolo();

        $this->post(route('diskuse.store', $kolo->id), [
            'znacka' => 'OK1@#$',
            'text' => 'Text s neplatnou značkou.',
        ])->assertSessionHasErrors('znacka');
    }

    public function test_store_requires_text(): void
    {
        $kolo = $this->kolo();

        $this->post(route('diskuse.store', $kolo->id), [
            'znacka' => 'OK1TEST',
        ])->assertSessionHasErrors('text');
    }

    public function test_store_rejects_text_too_short(): void
    {
        $kolo = $this->kolo();

        $this->post(route('diskuse.store', $kolo->id), [
            'znacka' => 'OK1TEST',
            'text' => 'x',
        ])->assertSessionHasErrors('text');
    }

    public function test_store_rejects_non_image_file(): void
    {
        Storage::fake('public');
        $kolo = $this->kolo();

        $this->post(route('diskuse.store', $kolo->id), [
            'znacka' => 'OK1FILE',
            'text' => 'Příspěvek s neplatným souborem.',
            'foto' => UploadedFile::fake()->create('dokument.pdf', 100, 'application/pdf'),
        ])->assertSessionHasErrors('foto');
    }

    // ------------------------------------------------------------------
    // destroy

    public function test_admin_can_delete_prispevek(): void
    {
        $kolo = $this->kolo();
        $p = Prispevek::create(['kolo_id' => $kolo->id, 'znacka' => 'OK1DEL', 'text' => 'Smažitelný.', 'ip' => '127.0.0.1']);

        $this->actingAs($this->admin())
            ->delete(route('diskuse.destroy', $p->id))
            ->assertRedirect(route('diskuse.show', $kolo->id))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('diskuse', ['id' => $p->id]);
    }

    public function test_admin_delete_removes_photo_from_storage(): void
    {
        Storage::fake('public');
        $kolo = $this->kolo();

        $fakeFile = UploadedFile::fake()->image('foto.jpg');
        $path = $fakeFile->storeAs('diskuse/'.$kolo->id, 'foto.jpg', 'public');
        $this->assertIsString($path);

        $p = Prispevek::create([
            'kolo_id' => $kolo->id,
            'znacka' => 'OK1FOTO',
            'text' => 'Příspěvek s fotkou.',
            'foto' => $path,
            'ip' => '127.0.0.1',
        ]);

        $this->actingAs($this->admin())
            ->delete(route('diskuse.destroy', $p->id));

        Storage::disk('public')->assertMissing($path);
    }

    public function test_guest_cannot_delete_prispevek(): void
    {
        $kolo = $this->kolo();
        $p = Prispevek::create(['kolo_id' => $kolo->id, 'znacka' => 'OK1ND', 'text' => 'Nesmažitelný hostem.', 'ip' => '127.0.0.1']);

        $this->delete(route('diskuse.destroy', $p->id))
            ->assertRedirect(route('login'));

        $this->assertDatabaseHas('diskuse', ['id' => $p->id]);
    }
}
