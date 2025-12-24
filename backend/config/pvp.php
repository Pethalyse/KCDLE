<?php

return [
    'afk_seconds' => env('PVP_AFK_SECONDS', 90),
    'idle_seconds' => env('PVP_IDLE_SECONDS', 300),

    'allowed_best_of' => [1, 3, 5],
    'disable_shuffle' => env('PVP_DISABLE_SHUFFLE', false),

    'default_best_of' => 5,

    'round_pool' => [
        'locked_infos',
        'whois',
        'classic',
        'draft',
        'reveal_race',
    ],

    'whois' => [
        'keys' => [
            'kcdle' => [
                'country_code',
                'role_id',
                'game_id',
                'current_team_id',
                'previous_team_id',
                'trophies_count',
                'first_official_year',
                'age',
            ],
            'lecdle' => [
                'country_code',
                'current_team_id',
                'lol_role',
                'age',
            ],
            'lfldle' => [
                'country_code',
                'current_team_id',
                'lol_role',
                'age',
            ],
        ],
        'meta' => [
            'country_code' => ['type' => 'enum', 'ops' => ['eq'], 'cast' => 'upper'],
            'role_id' => ['type' => 'enum', 'ops' => ['eq'], 'cast' => 'int'],
            'game_id' => ['type' => 'enum', 'ops' => ['eq'], 'cast' => 'int'],
            'current_team_id' => ['type' => 'enum', 'ops' => ['eq'], 'cast' => 'int'],
            'previous_team_id' => ['type' => 'enum', 'ops' => ['eq'], 'cast' => 'int'],
            'lol_role' => ['type' => 'enum', 'ops' => ['eq'], 'cast' => 'upper'],
            'trophies_count' => ['type' => 'number', 'ops' => ['eq', 'lt', 'gt'], 'cast' => 'int'],
            'first_official_year' => ['type' => 'number', 'ops' => ['eq', 'lt', 'gt'], 'cast' => 'int'],
            'age' => ['type' => 'number', 'ops' => ['eq', 'lt', 'gt'], 'cast' => 'int'],
        ],
        'lol_roles' => ['TOP', 'JNG', 'MID', 'BOT', 'SUP'],
    ],

    'locked_infos' => [
        'keys' => [
            'kcdle' => [
                'country_code',
                'role_id',
                'game_id',
                'current_team_id',
                'previous_team_id',
                'trophies_count',
                'first_official_year',
                'age',
            ],
            'lecdle' => [
                'country_code',
                'current_team_id',
                'lol_role',
                'age',
            ],
            'lfldle' => [
                'country_code',
                'current_team_id',
                'lol_role',
                'age',
            ],
        ],
    ],
];
