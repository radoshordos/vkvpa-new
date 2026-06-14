<?php

declare(strict_types=1);

namespace Database\Seeders;

class VkvpaKategorieTableSeeder extends JsonTableSeeder
{
    protected string $table = 'vkvpa_kategorie';

    protected ?int $autoIncrement = 46;

    /**
     * Snapshot ostré DB z 2026-06-10.
     *
     * @return list<array<string, mixed>>
     */
    protected function rows(): array
    {
        return [
            ['id' => 1, 'nazev' => '144 MHz single op', 'popis' => '', 'zkratka' => '144 SO', 'dxid' => 0],
            ['id' => 2, 'nazev' => '144 MHz multi op', 'popis' => '', 'zkratka' => '144 MO', 'dxid' => 0],
            ['id' => 3, 'nazev' => '432 MHz single op', 'popis' => '', 'zkratka' => '432 SO', 'dxid' => 0],
            ['id' => 4, 'nazev' => '432 MHz multi op', 'popis' => '', 'zkratka' => '432 MO', 'dxid' => 0],
            ['id' => 5, 'nazev' => '1.3 GHz single op', 'popis' => '', 'zkratka' => '1.3 SO', 'dxid' => 0],
            ['id' => 6, 'nazev' => '1.3 GHz multi op', 'popis' => '', 'zkratka' => '1.3 MO', 'dxid' => 0],
            ['id' => 7, 'nazev' => '2.3 GHz single op', 'popis' => '', 'zkratka' => '2.3 SO', 'dxid' => 0],
            ['id' => 8, 'nazev' => '2.3 GHz multi op', 'popis' => '', 'zkratka' => '2.3 MO', 'dxid' => 0],
            ['id' => 9, 'nazev' => '3.4 GHz single op', 'popis' => '', 'zkratka' => '3.4 SO', 'dxid' => 0],
            ['id' => 10, 'nazev' => '3.4 GHz multi op', 'popis' => '', 'zkratka' => '3.4 MO', 'dxid' => 0],
            ['id' => 11, 'nazev' => '5.7 GHz single op', 'popis' => '', 'zkratka' => '5.7 SO', 'dxid' => 0],
            ['id' => 12, 'nazev' => '5.7 MHz multi op', 'popis' => '', 'zkratka' => '5.7 MO', 'dxid' => 0],
            ['id' => 13, 'nazev' => '10 GHz single op', 'popis' => '', 'zkratka' => '10 SO', 'dxid' => 0],
            ['id' => 14, 'nazev' => '10 GHz multi op', 'popis' => '', 'zkratka' => '10 MO', 'dxid' => 0],
            ['id' => 15, 'nazev' => '24 GHz single op', 'popis' => '', 'zkratka' => '24 SO', 'dxid' => 0],
            ['id' => 16, 'nazev' => '24 GHz multi op', 'popis' => '', 'zkratka' => '24 MO', 'dxid' => 0],
            ['id' => 17, 'nazev' => '47 GHz single op', 'popis' => '', 'zkratka' => '47 SO', 'dxid' => 0],
            ['id' => 18, 'nazev' => '47 GHz multi op', 'popis' => '', 'zkratka' => '47 MO', 'dxid' => 0],
            ['id' => 19, 'nazev' => '76 GHz single op', 'popis' => '', 'zkratka' => '76 SO', 'dxid' => 0],
            ['id' => 20, 'nazev' => '76 MHz multi op', 'popis' => '', 'zkratka' => '76 MO', 'dxid' => 0],
            ['id' => 21, 'nazev' => '122 GHz single op', 'popis' => '', 'zkratka' => '122 SO', 'dxid' => 0],
            ['id' => 22, 'nazev' => '122 GHz multi op', 'popis' => '', 'zkratka' => '122 MO', 'dxid' => 0],
            ['id' => 23, 'nazev' => '144 MHz single DX', 'popis' => '', 'zkratka' => '144 MHz SO-DX', 'dxid' => 1],
            ['id' => 24, 'nazev' => '144 MHz multi DX', 'popis' => '', 'zkratka' => '144 MO DX', 'dxid' => 2],
            ['id' => 25, 'nazev' => '432 MHz SO DX', 'popis' => '', 'zkratka' => '432 SO DX', 'dxid' => 3],
            ['id' => 26, 'nazev' => '432 MHz MO DX', 'popis' => '', 'zkratka' => '432 MO DX', 'dxid' => 4],
            ['id' => 27, 'nazev' => '1.3 GHz SO DX', 'popis' => '', 'zkratka' => '1.3 SO DX', 'dxid' => 5],
            ['id' => 28, 'nazev' => '1.3 GHz MO DX', 'popis' => '', 'zkratka' => '1.3 MO DX', 'dxid' => 6],
            ['id' => 29, 'nazev' => '2.3 GHz SO DX', 'popis' => '', 'zkratka' => '2.3 SO DX', 'dxid' => 7],
            ['id' => 30, 'nazev' => '2.3 GHz MO DX', 'popis' => '', 'zkratka' => '2.3 MO DX', 'dxid' => 8],
            ['id' => 31, 'nazev' => '3.4 GHz SO DX', 'popis' => '', 'zkratka' => '3.4 SO DX', 'dxid' => 9],
            ['id' => 32, 'nazev' => '3.4 GHz MO DX', 'popis' => '', 'zkratka' => '3.4 MO DX', 'dxid' => 10],
            ['id' => 33, 'nazev' => '5.7 GHz SO DX', 'popis' => '', 'zkratka' => '5.7 SO DX', 'dxid' => 11],
            ['id' => 34, 'nazev' => '5.7 GHz MO DX', 'popis' => '', 'zkratka' => '5.7 MO DX', 'dxid' => 12],
            ['id' => 35, 'nazev' => '10 GHz SO DX', 'popis' => '', 'zkratka' => '10 SO DX', 'dxid' => 13],
            ['id' => 36, 'nazev' => '10 GHz MO DX', 'popis' => '', 'zkratka' => '10 MO DX', 'dxid' => 14],
            ['id' => 38, 'nazev' => '24 GHz SO DX', 'popis' => '', 'zkratka' => '24 SO DX', 'dxid' => 15],
            ['id' => 39, 'nazev' => '24 GHz MO DX', 'popis' => '', 'zkratka' => '24 MO DX', 'dxid' => 16],
            ['id' => 42, 'nazev' => '47 GHz single op DX', 'popis' => '', 'zkratka' => '47 SO DX', 'dxid' => 17],
            ['id' => 43, 'nazev' => '47 GHz multi op DX', 'popis' => '', 'zkratka' => '47 MO DX', 'dxid' => 18],
            ['id' => 44, 'nazev' => '76 GHz multi op DX', 'popis' => '', 'zkratka' => '76 MO DX', 'dxid' => 20],
            ['id' => 45, 'nazev' => '76 GHz single op DX', 'popis' => '', 'zkratka' => '76 SO DX', 'dxid' => 19],
        ];
    }
}
