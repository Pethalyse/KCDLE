<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DailyGame;
use App\Models\KcdlePlayer;
use App\Models\LoldlePlayer;
use App\Models\Player;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;


class GameGuessController extends Controller
{

    public function store(string $game, Request $request): JsonResponse
    {
        if (!in_array($game, ['kcdle', 'lfldle', 'lecdle'], true)) {
            return response()->json([
                'message' => 'Unknown game.',
            ], Response::HTTP_NOT_FOUND);
        }

        $data = $request->validate([
            'player_id' => ['required', 'integer'],
            'guesses'   => ['required', 'integer', 'min:1'],
        ]);

        /**
         * @var DailyGame $daily
         */
        $daily = DailyGame::where('game', $game)
            ->whereDate('selected_for_date', today())
            ->first();

        if (!$daily) {
            return response()->json([
                'message' => 'No daily game configured for today.',
            ], Response::HTTP_NOT_FOUND);
        }

        $secretWrapper = $daily->getAttribute('player_model');
        $guessWrapper  = Player::resolvePlayerModel($game, $data['player_id']);

        if (!$secretWrapper || ! $guessWrapper) {
            return response()->json([
                'message' => 'Invalid player.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $comparison = $this->comparePlayers($secretWrapper, $guessWrapper, $game);
        $correct = $comparison['correct'] ?? false;

        Log::channel('guess')->info('Guess attempt', [
            'ip'      => $request->ip(),
            'game'    => $game,
            'player_id' => $data['player_id'],
            'correct' => $correct,
            'guesses' => $data['guesses'],
        ]);

        if ($correct) {
            Log::channel('guess')->info('Correct guess', [
                'ip'         => $request->ip(),
                'game'       => $game,
                'player_id'  => $data['player_id'],
                'total_guesses_used' => $data['guesses'],
                'daily_id'   => $daily->getAttribute('id'),
            ]);

            $daily->increment('solvers_count');
            $daily->increment('total_guesses', $data['guesses']);
        }

        return response()->json([
            'correct'    => $correct,
            'comparison' => $comparison,
            'stats'      => [
                'solvers_count'   => $daily->getAttribute('solvers_count'),
                'total_guesses'   => $daily->getAttribute('total_guesses'),
                'average_guesses' => $daily->getAttribute('average_guesses'),
            ],
        ]);
    }

    protected function comparePlayers($secret, $guess, string $game): array
    {
        return match ($game) {
            'kcdle'  => $this->compareKcdlePlayers($secret, $guess),
            'lfldle', 'lecdle' => $this->compareLoldlePlayers($secret, $guess),
            default  => [
                'correct' => false,
                'fields'  => [],
            ],
        };
    }

    protected function compareKcdlePlayers(KcdlePlayer $secret, KcdlePlayer $guess): array
    {
        $secretPlayer = $secret->getAttribute('player');
        $guessPlayer  = $guess->getAttribute('player');

        $slug      = $this->eq($secretPlayer?->getAttribute('slug'), $guessPlayer?->getAttribute('slug'));
        $country   = $this->eq($secretPlayer?->getAttribute('country_code'), $guessPlayer?->getAttribute('country_code'));
        $role      = $this->eq($secretPlayer?->getAttribute('role_id'), $guessPlayer?->getAttribute('role_id'));
        $gameField = $this->eq($secret->getAttribute('game_id'), $guess->getAttribute('game_id'));
        $currentTeam = $this->eq(
            $secret->getAttribute('currentTeam')?->getAttribute('id'),
                $guess->getAttribute('currentTeam')?->getAttribute('id')
        );
        $previousTeam = $this->eq(
            $secret->getAttribute('previousTeam')?->getAttribute('id'),
            $guess->getAttribute('previousTeam')?->getAttribute('id')
        );

        $birthday = $this->cmpDate(
            $secretPlayer?->getAttribute('birthdate'),
            $guessPlayer?->getAttribute('birthdate')
        );

        $firstOfficialYear = $this->cmpNumber(
            $secret->getAttribute('first_official_year'),
            $guess->getAttribute('first_official_year')
        );
        $trophies = $this->cmpNumber(
            $secret->getAttribute('trophies_count'),
            $guess->getAttribute('trophies_count')
        );

        $correct = $slug === 1;

        return [
            'correct' => $correct,
            'fields'  => [
                'country'            => $country,
                'birthday'           => $birthday,
                'game'               => $gameField,
                'first_official_year'=> $firstOfficialYear,
                'trophies'           => $trophies,
                'previous_team'      => $previousTeam,
                'current_team'       => $currentTeam,
                'role'               => $role,
                'slug'               => $slug,
            ],
        ];
    }


    protected function compareLoldlePlayers(LoldlePlayer $secret, LoldlePlayer $guess): array
    {
        $secretPlayer = $secret->getAttribute('player');
        $guessPlayer  = $guess->getAttribute('player');

        $slug      = $this->eq($secretPlayer?->getAttribute('slug'), $guessPlayer?->getAttribute('slug'));
        $country   = $this->eq($secretPlayer?->getAttribute('country_code'), $guessPlayer?->getAttribute('country_code'));

        $birthday = $this->cmpDate(
            $secretPlayer?->getAttribute('birthdate'),
            $guessPlayer?->getAttribute('birthdate')
        );

        $team = $this->eq(
            $secret->getAttribute('team_id'),
            $guess->getAttribute('team_id')
        );

        $lolRole = $this->eq(
            $secret->getAttribute('lol_role'),
            $guess->getAttribute('lol_role')
        );

        $correct = $slug === 1;

        return [
            'correct' => $correct,
            'fields'  => [
                'country'  => $country,
                'birthday' => $birthday,
                'team'     => $team,
                'lol_role' => $lolRole,
                'slug'     => $slug,
            ],
        ];
    }

    /**
     * @param mixed $a
     * @param mixed $b
     * @return int
     *
     * 0 : false
     * 1 : true
     */
    protected function eq(mixed $a, mixed $b): int
    {
        return (int) ($a === $b);
    }

    /**
     * @param float|null $secret
     * @param float|null $guess
     * @return int|null
     *
     * -1 : secret < guess
     *  0 : secret > guess
     *  1 : secret == guess
     */
    protected function cmpNumber(?float $secret, ?float $guess): ?int
    {
        if ($secret === null || $guess === null) {
            return null;
        }

        if ($secret === $guess) {
            return 1;
        }

        return $secret < $guess ? -1 : 0;
    }

    /**
     * @param string|DateTimeInterface|null $secret
     * @param string|DateTimeInterface|null $guess
     * @return int|null
     *
     * Compare l'age selon les dates données.
     * -1 : secret est plus jeune
     *  0 : secret est plus âgé
     *  1 : même date
     */
    protected function cmpDate(null|string|DateTimeInterface $secret, null|string|DateTimeInterface $guess): ?int
    {
        if ($secret === null || $guess === null) {
            return null;
        }

        $s = $secret instanceof Carbon ? $secret->age : Carbon::parse($secret)->age;
        $g = $guess instanceof Carbon ? $guess->age : Carbon::parse($guess)->age;

        if ($s === $g) {
            return 1;
        }

        return $s < $g ? -1 : 0;
    }

}
