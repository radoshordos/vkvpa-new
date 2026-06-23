<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\RstPropagation;
use PHPUnit\Framework\TestCase;

/**
 * @see RstPropagation
 */
class RstPropagationTest extends TestCase
{
    public function test_letters_lists_all_allowed_tone_letters(): void
    {
        $this->assertSame('ASM', RstPropagation::letters());
    }

    public function test_numeric_and_allowed_letter_reports_are_valid(): void
    {
        $this->assertTrue(RstPropagation::isValidReport('59'));
        $this->assertTrue(RstPropagation::isValidReport('599'));
        $this->assertTrue(RstPropagation::isValidReport('59A'));
        $this->assertTrue(RstPropagation::isValidReport('59S'));
        $this->assertTrue(RstPropagation::isValidReport('59M'));
        $this->assertTrue(RstPropagation::isValidReport('59m')); // vstup se uppercasuje
        $this->assertTrue(RstPropagation::isValidReport(''));    // prázdný = neúplné spojení
    }

    public function test_other_letters_are_invalid(): void
    {
        $this->assertFalse(RstPropagation::isValidReport('59X'));
        $this->assertFalse(RstPropagation::isValidReport('59B'));
        $this->assertFalse(RstPropagation::isValidReport('59AA'));
        $this->assertFalse(RstPropagation::isValidReport('5A9'));
    }

    public function test_try_from_report_extracts_tone_letter(): void
    {
        $this->assertSame(RstPropagation::Aurora, RstPropagation::tryFromReport('59A'));
        $this->assertSame(RstPropagation::Scatter, RstPropagation::tryFromReport('59s'));
        $this->assertSame(RstPropagation::Multipath, RstPropagation::tryFromReport('599M'));
        $this->assertNull(RstPropagation::tryFromReport('59'));
        $this->assertNull(RstPropagation::tryFromReport(''));
        $this->assertNull(RstPropagation::tryFromReport('59X'));
    }

    public function test_labels_use_international_terms(): void
    {
        $this->assertSame('auroral propagation', RstPropagation::Aurora->label());
        $this->assertSame('scatter propagation', RstPropagation::Scatter->label());
        $this->assertSame('multipath propagation', RstPropagation::Multipath->label());
    }
}
