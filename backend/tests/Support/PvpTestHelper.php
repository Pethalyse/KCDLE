<?php

namespace Tests\Support;

use App\Models\Game;
use App\Models\KcdlePlayer;
use App\Models\Player;
use App\Models\PvpMatch;
use App\Models\PvpMatchPlayer;
use App\Models\User;
use Illuminate\Support\Facades\Config;

trait PvpTestHelper
{
    protected function pvpConfigureForSingleRound(string $roundType, int $bestOf = 1): void
    {
        Config::set('pvp.allowed_best_of', [1, 3, 5]);
        Config::set('pvp.default_best_of', 5);
        Config::set('pvp.round_pool', [$roundType]);
        Config::set('pvp.presence_seconds', 90);
        Config::set('pvp.idle_seconds', 300);
    }

    protected function pvpSeedMinimalKcdlePlayer(string $slug = 'tester', string $name = 'Tester'): int
    {
        $game = Game::firstOrCreate(['code' => 'kcdle'], ['name' => 'KCDLE', 'icon_slug' => null]);
        $player = Player::create([
            'slug' => $slug,
            'display_name' => $name,
            'country_code' => null,
            'birthdate' => null,
            'role_id' => null,
        ]);

        $wrapper = KcdlePlayer::create([
            'player_id' => $player->id,
            'game_id' => $game->id,
            'current_team_id' => null,
            'previous_team_before_kc_id' => null,
            'first_official_year' => 2020,
            'trophies_count' => 0,
            'active' => true,
        ]);

        return (int) $wrapper->id;
    }

    protected function pvpCreateMatch(string $game, string $roundType, int $bestOf = 1, ?User $u1 = null, ?User $u2 = null): array
    {
        $u1 ??= User::factory()->create();
        $u2 ??= User::factory()->create();

        $match = PvpMatch::create([
            'game' => $game,
            'status' => 'active',
            'best_of' => $bestOf,
            'current_round' => 1,
            'rounds' => [$roundType],
            'state' => [
                'round' => 1,
                'round_type' => $roundType,
                'chooser_rule' => 'random_first_then_last_winner',
                'chooser_user_id' => null,
                'last_round_winner_user_id' => null,
            ],
            'started_at' => now(),
        ]);

        PvpMatchPlayer::create([
            'match_id' => $match->id,
            'user_id' => $u1->id,
            'seat' => 1,
            'points' => 0,
            'last_seen_at' => now(),
            'last_action_at' => now(),
        ]);

        PvpMatchPlayer::create([
            'match_id' => $match->id,
            'user_id' => $u2->id,
            'seat' => 2,
            'points' => 0,
            'last_seen_at' => now(),
            'last_action_at' => now(),
        ]);

        return [$match->fresh(), $u1, $u2];
    }
}
