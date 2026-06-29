<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Exceptions\UnknownBandException;
use App\Services\Edi\CategoryResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Určení kategorie z hlavičky EDI: pásmo (PBand) + sekce (PSect) + DX (prefix PCall).
 *
 * Resolver páruje id přes číselník `edi_categories` (naseedovaný v base TestCase).
 *
 * @see CategoryResolver
 */
class CategoryResolverTest extends TestCase
{
    use RefreshDatabase;

    private CategoryResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new CategoryResolver;
    }

    public function test_band_section_and_dx_combine_to_category(): void
    {
        // OK + single + 144 → „144 MHz single op" (id 1)
        $this->assertSame(1, $this->resolver->resolve('OK2KJT', '144 MHz', 'SINGLE'));
        // OL + multi + 145 (→144) → „144 MHz multi op" (id 2)
        $this->assertSame(2, $this->resolver->resolve('OL7M', '145 MHz', 'Multi'));
        // 9A (DX) + single + 144 → „144 MHz single DX" (id 23)
        $this->assertSame(23, $this->resolver->resolve('9A1A', '144 MHz', 'SO'));
        // OM (DX) + multi + 435 (→432) → „432 MHz MO DX" (id 26)
        $this->assertSame(26, $this->resolver->resolve('OM3W', '435 MHz', 'MULTI-OP'));
    }

    public function test_band_normalization(): void
    {
        $this->assertSame(5, $this->resolver->resolve('OK1A', '1,3 GHz', 'SO')); // 1.3 single op
        $this->assertSame(3, $this->resolver->resolve('OK1A', '432 MHZ', 'single')); // 432 single op
    }

    public function test_band_without_space_before_unit(): void
    {
        // chybějící mezera mezi číslem a jednotkou: „47GHz" musí dát stejnou kategorii jako „47 GHz"
        $this->assertSame(17, $this->resolver->resolve('OK1A', '47GHz', 'SO'));
        $this->assertSame(17, $this->resolver->resolve('OK1A', '47 GHz', 'SO'));
        // platí i pro MHz a desetinné pásmo, vč. nadbytečných mezer
        $this->assertSame(1, $this->resolver->resolve('OK1A', '144MHz', 'SO'));
        $this->assertSame(5, $this->resolver->resolve('OK1A', '1,3GHz', 'SO'));
        $this->assertSame(13, $this->resolver->resolve('OK1A', '10  GHZ', 'SO'));
    }

    public function test_power_suffix_is_ignored(): void
    {
        $this->assertSame(1, $this->resolver->resolve('OK1A', '144 MHz', 'SO-LP'));
        $this->assertSame(1, $this->resolver->resolve('OK1A', '144 MHz', 'SINGLE-OP HIGH'));
        $this->assertSame(2, $this->resolver->resolve('OK1A', '144 MHz', 'M3LP')); // multi
    }

    public function test_dx_decided_by_callsign_prefix_not_psect(): void
    {
        // „SO DX" v PSect, ale značka OK → není DX (prefix rozhoduje) → op (id 1)
        $this->assertSame(1, $this->resolver->resolve('OK1A', '144 MHz', 'SO DX'));
        // „single" v PSect, ale značka DL → DX (id 23)
        $this->assertSame(23, $this->resolver->resolve('DL1A', '144 MHz', 'single'));
    }

    public function test_unknown_section_returns_null(): void
    {
        $this->assertNull($this->resolver->resolve('OK1A', '144 MHz', ''));   // prázdné
        $this->assertNull($this->resolver->resolve('OK1A', '144 MHz', '01')); // nerozpoznané
    }

    public function test_unknown_band_throws(): void
    {
        $this->expectException(UnknownBandException::class);
        $this->resolver->resolve('OK1A', '14 MHz', 'SO');
    }
}
