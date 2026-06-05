<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\VkvpaConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VkvpaConfigTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_returns_default_when_key_missing(): void
    {
        // Chybějící klíč nesmí shodit aplikaci (find() → null), vrací výchozí hodnotu.
        $this->assertNull(VkvpaConfig::get('neexistuje'));
        $this->assertSame('fallback', VkvpaConfig::get('neexistuje', 'fallback'));
    }

    public function test_put_then_get_roundtrips_value(): void
    {
        VkvpaConfig::put('klic', 'hodnota');
        $this->assertSame('hodnota', VkvpaConfig::get('klic', 'fallback'));
    }

    public function test_put_updates_existing_value(): void
    {
        VkvpaConfig::put('klic', 'puvodni');
        VkvpaConfig::put('klic', 'nova');

        $this->assertSame('nova', VkvpaConfig::get('klic'));
        $this->assertSame(1, VkvpaConfig::query()->where('cfg_key', 'klic')->count());
    }
}
