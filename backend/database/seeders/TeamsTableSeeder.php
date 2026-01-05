<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TeamsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = now();

        $teams = [
            ['slug' => 'none',               'display_name' => 'None',               'short_name' => null,   'country_code' => 'NN', 'is_karmine_corp' => false],
            ['slug' => 'aegis',              'display_name' => 'Aegis',              'short_name' => null,   'country_code' => null, 'is_karmine_corp' => false],
            ['slug' => 'barca_esport',       'display_name' => 'BarcaEsport',        'short_name' => null,   'country_code' => null, 'is_karmine_corp' => false],
            ['slug' => 'bds',                'display_name' => 'BDS',                'short_name' => 'BDS',  'country_code' => null, 'is_karmine_corp' => false],
            ['slug' => 'bds_academy',        'display_name' => 'BDS Academy',        'short_name' => 'BDS.A','country_code' => null, 'is_karmine_corp' => false],
            ['slug' => 'bk_rog',             'display_name' => 'BK ROG',             'short_name' => null,   'country_code' => null, 'is_karmine_corp' => false],
            ['slug' => 'dvm',                'display_name' => 'DVM',                'short_name' => null,   'country_code' => null, 'is_karmine_corp' => false],
            ['slug' => 'eyax_prestige',      'display_name' => 'EYAX Prestige',      'short_name' => null,   'country_code' => null, 'is_karmine_corp' => false],
            ['slug' => 'fnatic',             'display_name' => 'Fnatic',             'short_name' => 'FNC',  'country_code' => null, 'is_karmine_corp' => false],
            ['slug' => 'g2_esports',         'display_name' => 'G2 Esports',         'short_name' => 'G2',   'country_code' => null, 'is_karmine_corp' => false],
            ['slug' => 'galions',            'display_name' => 'Galions',            'short_name' => null,   'country_code' => null, 'is_karmine_corp' => false],
            ['slug' => 'gameward',           'display_name' => 'GameWard',           'short_name' => 'GW',   'country_code' => null, 'is_karmine_corp' => false],
            ['slug' => 'gentlemates',        'display_name' => 'Gentlemates',        'short_name' => null,   'country_code' => null, 'is_karmine_corp' => false],
            ['slug' => 'giantx',             'display_name' => 'GiantX',             'short_name' => null,   'country_code' => null, 'is_karmine_corp' => false],
            ['slug' => 'ici_japon_corp',     'display_name' => 'IciJaponCorp',       'short_name' => 'IJC',  'country_code' => null, 'is_karmine_corp' => false],
            ['slug' => 'jjrox',              'display_name' => 'Jjrox',              'short_name' => null,   'country_code' => null, 'is_karmine_corp' => false],
            ['slug' => 'joblife',            'display_name' => 'Joblife',            'short_name' => null,   'country_code' => null, 'is_karmine_corp' => false],

            ['slug' => 'karmine_corp',       'display_name' => 'Karmine Corp',       'short_name' => 'KC',   'country_code' => 'FR', 'is_karmine_corp' => true],
            ['slug' => 'karmine_corp_blue',  'display_name' => 'Karmine Corp Blue',  'short_name' => 'KCB',  'country_code' => 'FR', 'is_karmine_corp' => true],
            ['slug' => 'karmine_corp_blue_star',  'display_name' => 'Karmine Corp Blue Star',  'short_name' => 'KCBS',  'country_code' => 'FR', 'is_karmine_corp' => true],

            ['slug' => 'los_ratones',        'display_name' => 'Los Ratones',        'short_name' => null,   'country_code' => null, 'is_karmine_corp' => false],
            ['slug' => 'movistar_koi',       'display_name' => 'Movistar KOI',       'short_name' => 'MKOI',  'country_code' => null, 'is_karmine_corp' => false],
            ['slug' => 'misa_esport',        'display_name' => 'Misa Esport',        'short_name' => null,   'country_code' => null, 'is_karmine_corp' => false],
            ['slug' => 'papara_supermassive','display_name' => 'Papara SuperMassive','short_name' => 'PSM',  'country_code' => null, 'is_karmine_corp' => false],
            ['slug' => 'rogue',              'display_name' => 'Rogue',              'short_name' => 'RGE',  'country_code' => null, 'is_karmine_corp' => false],
            ['slug' => 'sk_gaming',          'display_name' => 'SK Gaming',          'short_name' => 'SK',   'country_code' => null, 'is_karmine_corp' => false],
            ['slug' => 'sentinels',          'display_name' => 'Sentinels',          'short_name' => 'SEN',  'country_code' => 'US', 'is_karmine_corp' => false],
            ['slug' => 'solary',             'display_name' => 'Solary',             'short_name' => 'SLY',  'country_code' => 'FR', 'is_karmine_corp' => false],
            ['slug' => 'team_go',            'display_name' => 'Team GO',            'short_name' => 'GO',   'country_code' => 'FR', 'is_karmine_corp' => false],
            ['slug' => 'team_du_sud',        'display_name' => 'Team du Sud',        'short_name' => null,   'country_code' => 'FR', 'is_karmine_corp' => false],
            ['slug' => 'team_heretics',      'display_name' => 'Team Heretics',      'short_name' => 'TH',   'country_code' => 'ES', 'is_karmine_corp' => false],
            ['slug' => 'twisted_minds',      'display_name' => 'Twisted Minds',      'short_name' => null,   'country_code' => null, 'is_karmine_corp' => false],
            ['slug' => 'unicorns_of_love',   'display_name' => 'Unicorns of Love',   'short_name' => 'UOL',  'country_code' => null, 'is_karmine_corp' => false],
            ['slug' => 'valiant',            'display_name' => 'Valiant',            'short_name' => null,   'country_code' => null, 'is_karmine_corp' => false],
            ['slug' => 'vitality',           'display_name' => 'Vitality',           'short_name' => 'VIT',  'country_code' => 'FR', 'is_karmine_corp' => false],
            ['slug' => 'vitality_bee',       'display_name' => 'Vitality Bee',       'short_name' => 'VIT.B','country_code' => 'FR', 'is_karmine_corp' => false],
            ['slug' => 'xset',               'display_name' => 'XSET',               'short_name' => null,   'country_code' => 'US', 'is_karmine_corp' => false],
            ['slug' => 'natus_vincere',      'display_name' => 'Natus Vincere',      'short_name' => 'NAVI', 'country_code' => 'UA', 'is_karmine_corp' => false],
        ];

        foreach ($teams as $team) {
            DB::table('teams')->insertOrIgnore([
                ...$team,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
