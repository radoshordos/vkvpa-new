<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Edihead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Příkaz vkvpa:repair-edihead-band-sect doplní prázdné p_band/p_sect
 * přeparsováním uložené hlavičky src, neprázdné sloupce nepřepisuje.
 */
class RepairEdiheadBandSectTest extends TestCase
{
    use RefreshDatabase;

    private const string SRC = "[REG1TEST;1]\nTName=Provozni aktiv\nTDate=20260315;20260315\n"
        ."PCall=OK2KJT\nPWWLo=JN99AJ\nPSect=MULTI\nPBand=144 MHz\n"
        ."[QSORecords;1]\n260315;0800;OK2IMH;1;59;001;59;001;;JN99BP;2;;;;\n[END;]\n";

    public function test_fills_empty_columns_from_src_and_keeps_existing(): void
    {
        $empty = $this->makeHead(pBand: '', pSect: '', src: self::SRC);          // doplní obojí
        $partial = $this->makeHead(pBand: '432 MHz', pSect: '', src: self::SRC); // doplní jen p_sect, p_band nechá
        $full = $this->makeHead(pBand: '432 MHz', pSect: 'SO', src: self::SRC);  // nedotkne se
        $noSrc = $this->makeHead(pBand: '', pSect: '', src: null);               // bez src → nezmění

        $this->assertSame(0, Artisan::call('vkvpa:repair-edihead-band-sect'));

        $empty->refresh();
        self::assertSame('144 MHz', $empty->p_band);
        self::assertSame('MULTI', $empty->p_sect);

        $partial->refresh();
        self::assertSame('432 MHz', $partial->p_band); // zachováno
        self::assertSame('MULTI', $partial->p_sect);   // doplněno

        $full->refresh();
        self::assertSame('432 MHz', $full->p_band);
        self::assertSame('SO', $full->p_sect);

        $noSrc->refresh();
        self::assertSame('', $noSrc->p_band);
        self::assertSame('', $noSrc->p_sect);
    }

    private function makeHead(string $pBand, string $pSect, ?string $src): Edihead
    {
        return Edihead::create([
            't_date' => '20260315;20260315',
            'p_call' => 'OK2KJT',
            'p_wwlo' => 'JN99AJ',
            'p_sect' => $pSect,
            'p_band' => $pBand,
            'r_name' => 'Test',
            'r_phon' => '',
            's_powe' => 100,
            'src' => $src,
        ]);
    }
}
