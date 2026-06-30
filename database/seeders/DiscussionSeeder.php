<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\DiscussionPost;
use App\Models\DiscussionPostPhoto;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class DiscussionSeeder extends Seeder
{
    public function run(): void
    {
        // MySQL odmítne TRUNCATE tabulky, na kterou míří cizí klíč
        // (discussion_post_photos → discussion_posts), i když je potomek prázdný
        // – ořezání dat constraint neruší. Vypneme proto FK kontroly (shodně
        // s JsonTableSeeder).
        Schema::disableForeignKeyConstraints();

        try {
            DiscussionPostPhoto::query()->truncate();
            DiscussionPost::query()->truncate();
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        // Ukázková testovací data. Fotky se neseedují – drží se binárně
        // v `discussion_post_photos`.
        $posts = [
            [
                'round_id' => 130,
                'callsign' => 'OK1KZE',
                'name' => 'OK1VUM',
                'body' => 'Sem můžete psát své komentáře a zážitky a doplnit je fotografií',
                'created_at' => '2026-04-22 07:11:23',
            ],
            [
                'round_id' => 130,
                'callsign' => 'OK1DWF',
                'name' => 'Karel',
                'body' => 'Test',
                'created_at' => '2026-04-22 10:59:25',
            ],
            [
                'round_id' => 131,
                'callsign' => 'OK2XKO',
                'name' => 'Jirka',
                'body' => 'Dík za QSO !',
                'created_at' => '2026-05-17 18:32:27',
            ],
            [
                'round_id' => 131,
                'callsign' => 'OK1IO',
                'name' => 'Jiří Knejfl',
                'body' => 'Zdravím,dnes velmi kvalitní contest.Podmínky OK. Contest jsem jel z kopce.WX krásné i když venku chladno.Dík za milá QSO fungovalo to velmi hezky,NSL v dalším kole. 73 Jirka.',
                'created_at' => '2026-05-18 11:31:16',
            ],
            [
                'round_id' => 131,
                'callsign' => 'OK1KZE',
                'name' => 'ok1vum',
                'body' => 'Tentokrát dobrá účast, dokonce zavolal SSB bez domluvy 9A6A ze Hvaru JN83GE. Na slyšenou příště.',
                'created_at' => '2026-05-18 13:13:16',
            ],
            [
                'round_id' => 132,
                'callsign' => 'OK5SE',
                'name' => 'Jiří',
                'body' => 'No holky kluci, webové rozhraní pro VKV PA vypadá super, krásně udělané statistiky a už jsem zvědavý, co ještě přibude po uzávěrce kola. Good job a velký dík.',
                'created_at' => '2026-06-23 10:15:13',
            ],
            [
                'round_id' => 132,
                'callsign' => 'OK1IO',
                'name' => null,
                'body' => 'Zdravím všechny závodníky, hezký závod. Jen to rušení z JJV mne na QTH trápí, téměř vždy jako když někdo vaří oběd.
V cca v 11.30 je po sršení. Vyskytuje se to pouze na 2metrech. Děkuji všem za účast a NSL v dalším kole.73 Jirka OK1IO',
                'created_at' => '2026-06-23 10:33:10',
            ],
            [
                'round_id' => 132,
                'callsign' => 'OK2BPN',
                'name' => 'Jaroslav',
                'body' => 'Moc pěkně udělaná statistika, děkuji.',
                'created_at' => '2026-06-24 07:55:31',
            ],
        ];

        foreach ($posts as $data) {
            DiscussionPost::create($data);
        }
    }
}
