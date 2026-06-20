<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Prefix;
use App\Services\Edi\PrefixResolver;
use Database\Seeders\PrefixesTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrefixesDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PrefixesTableSeeder::class);
    }

    public function test_swapped_baltic_countries_are_fixed(): void
    {
        $this->assertSame('Lithuania', Prefix::query()->where('prefix', 'LY')->value('country'));
        $this->assertSame('Latvia', Prefix::query()->where('prefix', 'YL')->value('country'));
    }

    public function test_liechtenstein_spelling_is_fixed(): void
    {
        $this->assertSame('Liechtenstein', Prefix::query()->where('prefix', 'HB0')->value('country'));
    }

    public function test_no_duplicate_prefixes(): void
    {
        $total = Prefix::query()->count();
        $distinct = Prefix::query()->distinct()->count('prefix');

        $this->assertSame($total, $distinct, 'Tabulka prefixů nemá obsahovat duplicitní prefixy.');
    }

    public function test_country_names_are_unified(): void
    {
        // Žádný z roztříštěných zápisů téže entity nesmí zůstat.
        foreach (['Isle Of Man', 'Northern Ireland GI', 'Guernsey and Dependencies'] as $stale) {
            $this->assertSame(0, Prefix::query()->where('country', $stale)->count(), "Zůstal starý zápis: {$stale}");
        }
    }

    public function test_added_european_countries_are_present(): void
    {
        $resolver = PrefixResolver::fromDatabase();

        $this->assertSame('Norway', $resolver->lookup('LA1ABC')['country'] ?? null);
        $this->assertSame('Belarus', $resolver->lookup('EW1AA')['country'] ?? null);
        $this->assertSame('European Russia', $resolver->lookup('UA3LL')['country'] ?? null);
        $this->assertSame('Moldova', $resolver->lookup('ER1AA')['country'] ?? null);
        $this->assertSame('Ireland', $resolver->lookup('EI2AB')['country'] ?? null);
        $this->assertSame('Greece', $resolver->lookup('SV1AA')['country'] ?? null);
    }

    public function test_kaliningrad_still_wins_over_european_russia(): void
    {
        // Doplnění evropského Ruska nesmí rozbít původní Kaliningrad (UA2/R2).
        $resolver = PrefixResolver::fromDatabase();

        $this->assertSame('Kaliningrad', $resolver->lookup('UA2FF')['country'] ?? null);
        $this->assertSame('European Russia', $resolver->lookup('UA1FF')['country'] ?? null);
    }
}
