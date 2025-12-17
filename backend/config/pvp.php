<?php

return [
    'afk_seconds' => env('PVP_AFK_SECONDS', 90),
    'idle_seconds' => env('PVP_IDLE_SECONDS', 90),

    'allowed_best_of' => [1, 3, 5],

    'default_best_of' => 5,

    'round_pool' => [
        'classic',
        'whois',
        'locked_infos',
        'draft',
        'reveal_race',
    ],
];
