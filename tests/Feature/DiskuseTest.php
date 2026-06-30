<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\DiscussionPost;
use App\Models\EdiRound;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DiskuseTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create(['name' => 'Admin', 'password' => Hash::make('x'), 'is_admin' => true]);
    }

    private function round(): EdiRound
    {
        return EdiRound::create([
            'starts_at' => now()->subDays(3),
            'closes_at' => now()->addDays(2),
            'name' => '05/2026',
            'note' => '',
        ]);
    }

    // ------------------------------------------------------------------
    // index

    public function test_index_redirects_to_latest_kolo(): void
    {
        $kolo = $this->round();

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
        $kolo = $this->round();
        DiscussionPost::create(['round_id' => $kolo->id, 'callsign' => 'OK1AB', 'body' => 'Ahoj ze závodu!', 'ip_address' => '127.0.0.1']);

        $this->get(route('diskuse.show', $kolo->id))
            ->assertOk()
            ->assertSee('OK1AB')
            ->assertSee('Ahoj ze závodu!');
    }

    public function test_show_renders_empty_state_without_prispevky(): void
    {
        $kolo = $this->round();

        $this->get(route('diskuse.show', $kolo->id))
            ->assertOk()
            ->assertSee('Buďte první');
    }

    public function test_selectbox_omits_old_kola_without_prispevky(): void
    {
        $aktualni = $this->round();
        $stareBez = EdiRound::create([
            'starts_at' => now()->subYears(2),
            'closes_at' => now()->subYears(2),
            'name' => '01/2024',
            'note' => '',
        ]);
        $stareS = EdiRound::create([
            'starts_at' => now()->subYears(3),
            'closes_at' => now()->subYears(3),
            'name' => '01/2023',
            'note' => '',
        ]);
        DiscussionPost::create(['round_id' => $stareS->id, 'callsign' => 'OK1AB', 'body' => 'Starý příspěvek.', 'ip_address' => '127.0.0.1']);

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
        $this->round();
        $stare = EdiRound::create([
            'starts_at' => now()->subYears(2),
            'closes_at' => now()->subYears(2),
            'name' => '01/2024',
            'note' => '',
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
        $kolo = $this->round();

        $this->post(route('diskuse.store', $kolo->id), [
            'znacka' => 'OK1TEST',
            'text' => 'Testovací příspěvek z jednotkového testu.',
        ])->assertRedirect(route('diskuse.show', $kolo->id))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('discussion_posts', [
            'round_id' => $kolo->id,
            'callsign' => 'OK1TEST',
        ]);
    }

    public function test_store_normalises_znacka_to_uppercase(): void
    {
        $kolo = $this->round();

        $this->post(route('diskuse.store', $kolo->id), [
            'znacka' => 'ok1lower',
            'text' => 'Text příspěvku.',
        ]);

        $this->assertDatabaseHas('discussion_posts', ['callsign' => 'OK1LOWER']);
    }

    public function test_store_accepts_optional_jmeno(): void
    {
        $kolo = $this->round();

        $this->post(route('diskuse.store', $kolo->id), [
            'znacka' => 'OK1A',
            'jmeno' => 'Jan Novák',
            'text' => 'Příspěvek s jménem.',
        ]);

        $this->assertDatabaseHas('discussion_posts', ['callsign' => 'OK1A', 'name' => 'Jan Novák']);
    }

    public function test_store_saves_photo_into_database(): void
    {
        $kolo = $this->round();

        $this->post(route('diskuse.store', $kolo->id), [
            'znacka' => 'OK1FOTO',
            'text' => 'Příspěvek s fotkou.',
            'fotky' => [UploadedFile::fake()->image('foto.jpg', 1200, 800)],
        ])->assertRedirect();

        $prispevek = DiscussionPost::where('callsign', 'OK1FOTO')->firstOrFail();
        $this->assertCount(1, $prispevek->photos);

        $foto = $prispevek->photos->first();
        $this->assertNotNull($foto);
        $this->assertSame('image/webp', $foto->mime_type);
        $this->assertNotEmpty($foto->data);
        $this->assertNotEmpty($foto->thumbnail);
        $this->assertGreaterThan(0, $foto->width);
        $this->assertGreaterThan(0, $foto->height);
    }

    public function test_store_saves_multiple_photos_in_order(): void
    {
        $kolo = $this->round();

        $this->post(route('diskuse.store', $kolo->id), [
            'znacka' => 'OK1MULTI',
            'text' => 'Příspěvek s více fotkami.',
            'fotky' => [
                UploadedFile::fake()->image('a.jpg', 800, 600),
                UploadedFile::fake()->image('b.jpg', 800, 600),
                UploadedFile::fake()->image('c.jpg', 800, 600),
            ],
        ])->assertRedirect();

        $prispevek = DiscussionPost::where('callsign', 'OK1MULTI')->firstOrFail();
        $this->assertCount(3, $prispevek->photos);
        $this->assertSame([0, 1, 2], $prispevek->photos->pluck('position')->all());
    }

    public function test_store_rejects_more_than_max_photos(): void
    {
        $kolo = $this->round();

        $fotky = [];
        for ($i = 0; $i < 6; $i++) {
            $fotky[] = UploadedFile::fake()->image("f{$i}.jpg", 400, 400);
        }

        $this->post(route('diskuse.store', $kolo->id), [
            'znacka' => 'OK1MAX',
            'text' => 'Příliš mnoho fotek.',
            'fotky' => $fotky,
        ])->assertSessionHasErrors('fotky');

        $this->assertDatabaseMissing('discussion_posts', ['callsign' => 'OK1MAX']);
    }

    public function test_store_downscales_large_photo(): void
    {
        $kolo = $this->round();

        $this->post(route('diskuse.store', $kolo->id), [
            'znacka' => 'OK1BIG',
            'text' => 'Velká fotka se má zmenšit.',
            'fotky' => [UploadedFile::fake()->image('big.jpg', 4000, 3000)],
        ])->assertRedirect();

        $foto = DiscussionPost::where('callsign', 'OK1BIG')->firstOrFail()->photos->first();
        $this->assertNotNull($foto);
        $this->assertLessThanOrEqual(2000, $foto->width);
        $this->assertLessThanOrEqual(2000, $foto->height);
    }

    public function test_foto_route_serves_image_from_db(): void
    {
        $kolo = $this->round();

        $this->post(route('diskuse.store', $kolo->id), [
            'znacka' => 'OK1SERVE',
            'text' => 'Servírovaná fotka.',
            'fotky' => [UploadedFile::fake()->image('s.jpg', 600, 600)],
        ]);

        $foto = DiscussionPost::where('callsign', 'OK1SERVE')->firstOrFail()->photos->firstOrFail();

        $this->get(route('diskuse.foto', $foto->id))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/webp');

        $this->get(route('diskuse.foto.nahled', $foto->id))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/webp');
    }

    public function test_store_requires_znacka(): void
    {
        $kolo = $this->round();

        $this->post(route('diskuse.store', $kolo->id), [
            'text' => 'Text bez značky.',
        ])->assertSessionHasErrors('znacka');
    }

    public function test_store_rejects_znacka_with_special_chars(): void
    {
        $kolo = $this->round();

        $this->post(route('diskuse.store', $kolo->id), [
            'znacka' => 'OK1@#$',
            'text' => 'Text s neplatnou značkou.',
        ])->assertSessionHasErrors('znacka');
    }

    public function test_store_requires_text(): void
    {
        $kolo = $this->round();

        $this->post(route('diskuse.store', $kolo->id), [
            'znacka' => 'OK1TEST',
        ])->assertSessionHasErrors('text');
    }

    public function test_store_rejects_text_too_short(): void
    {
        $kolo = $this->round();

        $this->post(route('diskuse.store', $kolo->id), [
            'znacka' => 'OK1TEST',
            'text' => 'x',
        ])->assertSessionHasErrors('text');
    }

    public function test_store_rejects_non_image_file(): void
    {
        $kolo = $this->round();

        $this->post(route('diskuse.store', $kolo->id), [
            'znacka' => 'OK1FILE',
            'text' => 'Příspěvek s neplatným souborem.',
            'fotky' => [UploadedFile::fake()->create('dokument.pdf', 100, 'application/pdf')],
        ])->assertSessionHasErrors('fotky.*');
    }

    // ------------------------------------------------------------------
    // destroy

    public function test_admin_can_delete_prispevek(): void
    {
        $kolo = $this->round();
        $p = DiscussionPost::create(['round_id' => $kolo->id, 'callsign' => 'OK1DEL', 'body' => 'Smažitelný.', 'ip_address' => '127.0.0.1']);

        $this->actingAs($this->admin())
            ->delete(route('diskuse.destroy', $p->id))
            ->assertRedirect(route('diskuse.show', $kolo->id))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('discussion_posts', ['id' => $p->id]);
    }

    public function test_admin_delete_removes_photos_from_db(): void
    {
        $kolo = $this->round();

        $this->post(route('diskuse.store', $kolo->id), [
            'znacka' => 'OK1FOTO',
            'text' => 'Příspěvek s fotkou.',
            'fotky' => [UploadedFile::fake()->image('foto.jpg', 600, 400)],
        ]);

        $p = DiscussionPost::where('callsign', 'OK1FOTO')->firstOrFail();
        $this->assertCount(1, $p->photos);

        $this->actingAs($this->admin())
            ->delete(route('diskuse.destroy', $p->id));

        $this->assertDatabaseMissing('discussion_posts', ['id' => $p->id]);
        $this->assertDatabaseMissing('discussion_post_photos', ['discussion_post_id' => $p->id]);
    }

    public function test_guest_cannot_delete_prispevek(): void
    {
        $kolo = $this->round();
        $p = DiscussionPost::create(['round_id' => $kolo->id, 'callsign' => 'OK1ND', 'body' => 'Nesmažitelný hostem.', 'ip_address' => '127.0.0.1']);

        $this->delete(route('diskuse.destroy', $p->id))
            ->assertRedirect(route('login'));

        $this->assertDatabaseHas('discussion_posts', ['id' => $p->id]);
    }
}
