<?php

namespace App\Http\Controllers\Api\Discord;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AnonKeyService;
use App\Services\GameGuessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Discord bot API controller.
 *
 * This endpoint is meant to be called by the bot backend, not by browsers.
 * It reuses the same guess logic as the public API through GameGuessService.
 *
 * The server computes the guess order, and replay-after-win is enforced for bot usage.
 */
class DiscordBotGuessController extends Controller
{
    public function __construct(
        protected GameGuessService $guessService,
        protected AnonKeyService $anonKeys
    ) {}

    /**
     * Submit a guess from the Discord bot context.
     *
     * If the Discord account is linked to a KCDLE user (users.discord_id),
     * the guess is persisted as an authenticated user guess.
     *
     * Otherwise, guesses are persisted under an anonymous key derived from
     * the discord_id, to prevent replay.
     *
     * This endpoint enforces "no replay after win" (409) for bot usage.
     *
     * @param string  $game    Game identifier ('kcdle', 'lfldle', 'lecdle').
     * @param Request $request Request payload.
     *
     * @return JsonResponse
     */
    public function guess(string $game, Request $request): JsonResponse
    {
        $data = $request->validate([
            'discord_id' => ['required', 'string', 'max:32'],
            'player_id' => ['required', 'integer'],
        ]);

        $discordId = (string) $data['discord_id'];
        $playerId = (int) $data['player_id'];

        $user = User::query()
            ->where('discord_id', $discordId)
            ->first();

        $anonKey = null;
        if (!$user instanceof User) {
            $anonKey = $this->anonKeys->fromValue('discord:' . $discordId);
        }

        $result = $this->guessService->submitGuess(
            $game,
            $request,
            $playerId,
            $user,
            $anonKey,
            true,
            false
        );

        return response()->json($result['payload'], (int) $result['status']);
    }
}
