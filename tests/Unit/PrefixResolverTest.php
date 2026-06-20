<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Edi\PrefixResolver;
use PHPUnit\Framework\TestCase;

class PrefixResolverTest extends TestCase
{
    private function resolver(): PrefixResolver
    {
        return new PrefixResolver([
            ['prefix' => 'OK', 'country' => 'Czech Rep'],
            ['prefix' => '9A', 'country' => 'Croatia'],
            ['prefix' => 'HB9', 'country' => 'Switzerland'],
            ['prefix' => 'HB0', 'country' => 'Liechtenstein'],
            ['prefix' => 'UA1', 'country' => 'European Russia'],
            ['prefix' => 'UA2', 'country' => 'Kaliningrad'],
        ]);
    }

    public function test_matches_simple_prefix(): void
    {
        $this->assertSame(
            ['prefix' => 'OK', 'country' => 'Czech Rep'],
            $this->resolver()->lookup('OK1FPS'),
        );
    }

    public function test_longest_match_wins_over_shorter(): void
    {
        // HB9 i HB0 začínají na „HB" – musí vyhrát delší (specifičtější) prefix.
        $this->assertSame('Switzerland', $this->resolver()->lookup('HB9AA')['country'] ?? null);
        $this->assertSame('Liechtenstein', $this->resolver()->lookup('HB0XX')['country'] ?? null);

        // UA2 (Kaliningrad) nesmí spadnout pod obecnější UA1.
        $this->assertSame('Kaliningrad', $this->resolver()->lookup('UA2FF')['country'] ?? null);
        $this->assertSame('European Russia', $this->resolver()->lookup('UA1AA')['country'] ?? null);
    }

    public function test_uses_part_before_slash(): void
    {
        // Hostující prefix: rozhoduje část před lomítkem.
        $this->assertSame('Croatia', $this->resolver()->lookup('9A/OK1ABC')['country'] ?? null);
        // Portable suffix: základ zůstává OK.
        $this->assertSame('Czech Rep', $this->resolver()->lookup('OK1ABC/P')['country'] ?? null);
    }

    public function test_is_case_insensitive(): void
    {
        $this->assertSame('Czech Rep', $this->resolver()->lookup('ok1abc')['country'] ?? null);
    }

    public function test_unknown_prefix_returns_null(): void
    {
        $this->assertNull($this->resolver()->lookup('ZZ9ZZ'));
        $this->assertNull($this->resolver()->lookup(''));
    }
}
