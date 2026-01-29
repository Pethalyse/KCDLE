<?php

namespace App\Http\Controllers\Api\Discord;

use App\Http\Controllers\Controller;
use App\Models\DailyGame;
use App\Models\PendingGuess;
use App\Models\Player;
use App\Models\User;
use App\Models\UserGameResult;
use App\Models\UserGuess;
use App\Services\AnonKeyService;
use App\Services\Dle\PlayerComparisonService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Discord bot API controller for retrieving today's run state.
 *
 * This endpoint is meant to be called by the Discord bot backend.
 * It uses the Discord bot secret middleware for authentication.
 *
 * Given a game and a discord_id, it returns the current state for today:
 * - whether the daily has been solved
 * - the list of guesses performed today (including those made on the website)
 * - comparison payload for each guess (same format as the public guess API)
 */
class DiscordBotTodayController extends Controller
{
    public function __construct(
        protected AnonKeyService $anonKeys,
        protected PlayerComparisonService $comparison
    ) {}

    /**
     * Retrieve today's run (guesses + solved status) for a Discord user.
     *
     * If the Discord account is linked to a KCDLE user (users.discord_id),
     * guesses are retrieved from the authenticated user history.
     *
     * Otherwise, guesses are retrieved from the anonymous Discord key history
     * (pending_guesses), derived from the discord_id.
     *
     * Query parameters:
     * - discord_id: string (required) The Discord user identifier.
     *
     * Response JSON:
     * - game: string
     * - selected_for_date: string (YYYY-MM-DD)
     * - solved: bool
     * - guesses: int
     * - history: array<int, array{guess_order:int, player_id:int, player_name:string, correct:bool, fields:array<string,int|null>}>
     *
     * @param string  $game    Game identifier ('kcdle', 'lfldle', 'lecdle').
     * @param Request $request HTTP request.
     *
     * @return JsonResponse
     */
    public function show(string $game, Request $request): JsonResponse
    {
        if (!in_array($game, ['kcdle', 'lfldle', 'lecdle'], true)) {
            return response()->json(['message' => 'Unknown game.'], Response::HTTP_NOT_FOUND);
        }

        $data = $request->validate([
            'discord_id' => ['required', 'string', 'max:32'],
        ]);

        $discordId = (string) $data['discord_id'];

        $daily = DailyGame::query()
            ->where('game', $game)
            ->whereDate('selected_for_date', today())
            ->first();

        if (!$daily instanceof DailyGame) {
            return response()->json(['message' => 'No daily game configured for today.'], Response::HTTP_NOT_FOUND);
        }

        $secretWrapper = $daily->getAttribute('player_model');
        if (!$secretWrapper) {
            return response()->json(['message' => 'No daily game configured for today.'], Response::HTTP_NOT_FOUND);
        }

        $user = User::query()
            ->where('discord_id', $discordId)
            ->first();

        $guesses = [];
        $solved = false;

        if ($user instanceof User) {
            $result = UserGameResult::query()
                ->where('user_id', $user->getAttribute('id'))
                ->where('daily_game_id', $daily->getAttribute('id'))
                ->first();

            if ($result instanceof UserGameResult) {
                $solved = $result->getAttribute('won_at') !== null;

                $rows = UserGuess::query()
                    ->where('user_game_result_id', $result->getAttribute('id'))
                    ->orderBy('guess_order')
                    ->get();

                foreach ($rows as $row) {
                    $guesses[] = [
                        'guess_order' => (int) $row->getAttribute('guess_order'),
                        'player_id' => (int) $row->getAttribute('player_id'),
                        'correct' => null,
                    ];
                }
            }
        } else {
            $anonKey = $this->anonKeys->fromValue('discord:' . $discordId);

            $rows = PendingGuess::query()
                ->where('anon_key', $anonKey)
                ->where('daily_game_id', $daily->getAttribute('id'))
                ->orderBy('guess_order')
                ->get();

            foreach ($rows as $row) {
                $correct = (bool) $row->getAttribute('correct');
                if ($correct) {
                    $solved = true;
                }

                $guesses[] = [
                    'guess_order' => (int) $row->getAttribute('guess_order'),
                    'player_id' => (int) $row->getAttribute('player_id'),
                    'correct' => $correct,
                ];
            }
        }

        $history = [];

        foreach ($guesses as $g) {
            $playerId = (int) $g['player_id'];
            $guessWrapper = Player::resolvePlayerModel($game, $playerId);

            if (!$guessWrapper) {
                continue;
            }

            $comparison = $this->comparison->comparePlayers($secretWrapper, $guessWrapper, $game);
            $correct = (bool) ($comparison['correct'] ?? false);

            $playerName = null;
            try {
                $playerName = $guessWrapper->player?->display_name;
            } catch (\Throwable) {
                $playerName = null;
            }

            if (!is_string($playerName) || trim($playerName) === '') {
                $playerName = '#' . $playerId;
            }

            $history[] = [
                'guess_order' => (int) $g['guess_order'],
                'player_id' => $playerId,
                'player_name' => (string) $playerName,
                'correct' => $correct,
                'fields' => (array) ($comparison['fields'] ?? []),
            ];
        }

        return response()->json([
            'game' => $game,
            'selected_for_date' => $daily->getAttribute('selected_for_date')?->format('Y-m-d') ?? (string) $daily->getAttribute('selected_for_date'),
            'solved' => $solved,
            'guesses' => count($history),
            'history' => $history,
        ]);
    }
}
