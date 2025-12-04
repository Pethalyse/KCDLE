<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LeaguesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $lolId = DB::table('games')->where('code', 'LOL')->value('id');

        DB::table('leagues')->insertOrIgnore([
            [
                'code' => 'LEC',
                'name' => 'League of Legends EMEA Championship',
                'game_id' => $lolId,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'LFL',
                'name' => 'Ligue Française de League of Legends',
                'game_id' => $lolId,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ]);
    }
}
