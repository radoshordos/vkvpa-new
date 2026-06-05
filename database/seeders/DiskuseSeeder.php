<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Prispevek;
use Illuminate\Database\Seeder;

class DiskuseSeeder extends Seeder
{
    public function run(): void
    {
        Prispevek::query()->truncate();

        $prispevky = [
            [
                'kolo_id' => 130,
                'znacka' => 'OK1KZE',
                'jmeno' => 'OK1VUM',
                'text' => 'Sem můžete psát své komentáře a zážitky a doplnit je fotografií',
                'foto' => 'diskuse/130/1776841883_OK1KZE.jpg',
                'ip' => '151.249.104.130',
                'created_at' => '2026-04-22 09:11:23',
            ],
            [
                'kolo_id' => 130,
                'znacka' => 'OK1DWF',
                'jmeno' => 'Karel',
                'text' => 'Test',
                'foto' => 'diskuse/130/1776855565_OK1DWF.jpg',
                'ip' => '78.80.107.86',
                'created_at' => '2026-04-22 12:59:25',
            ],
            [
                'kolo_id' => 131,
                'znacka' => 'OK2XKO',
                'jmeno' => 'Jirka',
                'text' => 'Dík za QSO !',
                'foto' => 'diskuse/131/1779042747_OK2XKO.jpg',
                'ip' => '213.194.199.173',
                'created_at' => '2026-05-17 20:32:27',
            ],
            [
                'kolo_id' => 131,
                'znacka' => 'OK1IO',
                'jmeno' => 'Jiří Knejfl',
                'text' => 'Zdravím,dnes velmi kvalitní contest.Podmínky OK. Contest jsem jel z kopce.WX krásné i když venku chladno.Dík za milá QSO fungovalo to velmi hezky,NSL v dalším kole. 73 Jirka.',
                'foto' => null,
                'ip' => '109.164.55.70',
                'created_at' => '2026-05-18 13:31:16',
            ],
            [
                'kolo_id' => 131,
                'znacka' => 'OK1KZE',
                'jmeno' => 'ok1vum',
                'text' => 'Tentokrát dobrá účast, dokonce zavolal SSB bez domluvy 9A6A ze Hvaru JN83GE. Na slyšenou příště.',
                'foto' => 'diskuse/131/1779109995_OK1KZE.jpg',
                'ip' => '151.249.105.55',
                'created_at' => '2026-05-18 15:13:16',
            ],
        ];

        foreach ($prispevky as $data) {
            Prispevek::create($data);
        }
    }
}
