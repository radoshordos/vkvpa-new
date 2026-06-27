<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Prispevek;
use App\Models\PrispevekFoto;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class DiskuseSeeder extends Seeder
{
    public function run(): void
    {
        // MySQL odmítne TRUNCATE tabulky, na kterou míří cizí klíč
        // (diskuse_foto → diskuse), i když je potomek prázdný – ořezání dat
        // constraint neruší. Vypneme proto FK kontroly (shodně s JsonTableSeeder).
        Schema::disableForeignKeyConstraints();

        try {
            PrispevekFoto::query()->truncate();
            Prispevek::query()->truncate();
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        // Snapshot ostré DB z 2026-06-27 (čas v UTC, shodně s app.timezone).
        // Fotky se neseedují – v ostré DB byly ještě jako cesty na disku (starý
        // sloupec `foto`); nové schéma je drží binárně v `diskuse_foto`.
        $prispevky = [
            [
                'kolo_id' => 130,
                'znacka' => 'OK1KZE',
                'jmeno' => 'OK1VUM',
                'text' => 'Sem můžete psát své komentáře a zážitky a doplnit je fotografií',
                'ip' => '151.249.104.130',
                'created_at' => '2026-04-22 07:11:23',
            ],
            [
                'kolo_id' => 130,
                'znacka' => 'OK1DWF',
                'jmeno' => 'Karel',
                'text' => 'Test',
                'ip' => '78.80.107.86',
                'created_at' => '2026-04-22 10:59:25',
            ],
            [
                'kolo_id' => 131,
                'znacka' => 'OK2XKO',
                'jmeno' => 'Jirka',
                'text' => 'Dík za QSO !',
                'ip' => '213.194.199.173',
                'created_at' => '2026-05-17 18:32:27',
            ],
            [
                'kolo_id' => 131,
                'znacka' => 'OK1IO',
                'jmeno' => 'Jiří Knejfl',
                'text' => 'Zdravím,dnes velmi kvalitní contest.Podmínky OK. Contest jsem jel z kopce.WX krásné i když venku chladno.Dík za milá QSO fungovalo to velmi hezky,NSL v dalším kole. 73 Jirka.',
                'ip' => '109.164.55.70',
                'created_at' => '2026-05-18 11:31:16',
            ],
            [
                'kolo_id' => 131,
                'znacka' => 'OK1KZE',
                'jmeno' => 'ok1vum',
                'text' => 'Tentokrát dobrá účast, dokonce zavolal SSB bez domluvy 9A6A ze Hvaru JN83GE. Na slyšenou příště.',
                'ip' => '151.249.105.55',
                'created_at' => '2026-05-18 13:13:16',
            ],
            [
                'kolo_id' => 132,
                'znacka' => 'OK5SE',
                'jmeno' => 'Jiří',
                'text' => 'No holky kluci, webové rozhraní pro VKV PA vypadá super, krásně udělané statistiky a už jsem zvědavý, co ještě přibude po uzávěrce kola. Good job a velký dík.',
                'ip' => '77.240.177.162',
                'created_at' => '2026-06-23 10:15:13',
            ],
            [
                'kolo_id' => 132,
                'znacka' => 'OK1IO',
                'jmeno' => null,
                'text' => 'Zdravím všechny závodníky, hezký závod. Jen to rušení z JJV mne na QTH trápí, téměř vždy jako když někdo vaří oběd. 
V cca v 11.30 je po sršení. Vyskytuje se to pouze na 2metrech. Děkuji všem za účast a NSL v dalším kole.73 Jirka OK1IO',
                'ip' => '109.164.55.70',
                'created_at' => '2026-06-23 10:33:10',
            ],
            [
                'kolo_id' => 132,
                'znacka' => 'OK2BPN',
                'jmeno' => 'Jaroslav',
                'text' => 'Moc pěkně udělaná statistika, děkuji.',
                'ip' => '188.246.125.84',
                'created_at' => '2026-06-24 07:55:31',
            ],
        ];

        foreach ($prispevky as $data) {
            Prispevek::create($data);
        }
    }
}
