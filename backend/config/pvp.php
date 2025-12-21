<?php

return [
    'afk_seconds' => env('PVP_AFK_SECONDS', 90),
    'idle_seconds' => env('PVP_IDLE_SECONDS', 300),

    'allowed_best_of' => [1, 3, 5],
    'disable_shuffle' => env('PVP_DISABLE_SHUFFLE', false),

    'default_best_of' => 5,

    'round_pool' => [
        'classic',
        'whois',
        'locked_infos',
        'draft',
        'reveal_race',
    ],
];
