<?php

namespace App\Services\Pvp\Rounds;

/**
 * Applies a guess to a guess-based round data structure.
 */
readonly class GuessRoundApplyService
{
    public function __construct(private GuessRoundStateService $guessState)
    {
    }

    /**
     * Apply a guess into $data['players'] and return mutation info.
     *
     * @param array $data     Round data containing 'players'.
     * @param int          $userId   Acting user id.
     * @param int          $playerId Guessed player id.
     * @param int          $secretId Secret player id.
     *
     * @return array{data:array, players:array, correct:bool, nowIso:string, guessCount:int}
     */
    public function apply(array $data, int $userId, int $playerId, int $secretId, array $meta = []): array
    {
        $players = (array) ($data['players'] ?? []);
        $correct = $playerId === $secretId;

        $nowIso = now()->toISOString();

        $applied = $this->guessState->applyGuess($players, $userId, $playerId, $correct, $nowIso, $meta);

        $players = $applied['players'];
        $data['players'] = $players;

        return [
            'data' => $data,
            'players' => $players,
            'correct' => $correct,
            'nowIso' => $nowIso,
            'guessCount' => (int) $applied['guess_count'],
        ];
    }
}
