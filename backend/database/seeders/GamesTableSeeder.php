<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GamesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = now();

        DB::table('games')->insertOrIgnore([
            [
                'code'       => 'CEO',
                'name'       => 'CEO',
                'icon_slug'  => 'CEO',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code'       => 'LOL',
                'name'       => 'League of Legends',
                'icon_slug'  => 'LOL',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code'       => 'VALORANT',
                'name'       => 'Valorant',
                'icon_slug'  => 'Valorant',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code'       => 'RL',
                'name'       => 'Rocket League',
                'icon_slug'  => 'RocketLeague',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code'       => 'TFT',
                'name'       => 'Teamfight Tactics',
                'icon_slug'  => 'TFT',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code'       => 'FORTNITE',
                'name'       => 'Fortnite',
                'icon_slug'  => 'Fortnite',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code'       => 'TEKKEN8',
                'name'       => 'Tekken 8',
                'icon_slug'  => 'Tekken8',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code'       => 'TRACKMANIA',
                'name'       => 'Trackmania',
                'icon_slug'  => 'Trackmania',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code'       => 'SF',
                'name'       => 'Street Fighter',
                'icon_slug'  => 'SF',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code'       => 'SSBU',
                'name'       => 'Super Smash Bros. Ultimate',
                'icon_slug'  => 'SSB',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }
}
