<?php

namespace App\Services\Pvp\Rounds;

/**
 * Shared helper for guess-based PvP rounds.
 *
 * Provides common state mutations:
 * - append a guess entry
 * - mark solved
 * - check both solved
 * - build public "you/opponent" view
 */
class GuessRoundStateService
{
    /**
     * Initialize per-player guess state.
     *
     * @param array<int,int> $userIds Participant ids.
     * @param string         $startedAt ISO timestamp.
     *
     * @return array<int,array{started_at:string|null,solved_at:string|null,guess_count:int,guesses:array}>
     */
    public function initPlayers(array $userIds, string $startedAt): array
    {
        $out = [];
        foreach ($userIds as $uid) {
            $uid = (int) $uid;
            $out[$uid] = [
                'started_at' => $startedAt,
                'solved_at' => null,
                'guess_count' => 0,
                'guesses' => [],
            ];
        }
        return $out;
    }

    /**
     * Apply a guess to a user's state.
     *
     * @param array  $players  Players map.
     * @param int    $userId   Acting user id.
     * @param int    $playerId Guessed player id.
     * @param bool   $correct  Whether guess is correct.
     * @param string $nowIso   Current time ISO string.
     * @param array  $meta     Extra data to persist with the guess entry (e.g. comparison payload).
     *
     * @return array{players:array, guess_count:int, entry:array}
     */
    public function applyGuess(
        array $players,
        int $userId,
        int $playerId,
        bool $correct,
        string $nowIso,
        array $meta = []
    ): array {
        $self = (array) ($players[$userId] ?? null);
        if ($self === []) {
            abort(403, 'Not a participant.');
        }

        if (! empty($self['solved_at'])) {
            abort(409, 'Already solved.');
        }

        foreach ((array) ($self['guesses'] ?? []) as $g) {
            if ((int) ($g['player_id'] ?? 0) === $playerId) {
                abort(409, 'Already guessed.');
            }
        }

        $guessCount = ((int) ($self['guess_count'] ?? 0)) + 1;

        $entry = [
            'guess_order' => $guessCount,
            'player_id' => $playerId,
            'correct' => $correct,
            'guessed_at' => $nowIso,
        ];

        if ($meta !== []) {
            foreach ($meta as $k => $v) {
                if (! array_key_exists($k, $entry)) {
                    $entry[$k] = $v;
                }
            }
        }

        $self['guess_count'] = $guessCount;
        $self['guesses'] = array_values(array_merge((array) ($self['guesses'] ?? []), [$entry]));

        if ($correct) {
            $self['solved_at'] = $nowIso;
        }

        $players[$userId] = $self;

        return [
            'players' => $players,
            'guess_count' => $guessCount,
            'entry' => $entry,
        ];
    }

    /**
     * Check if both participants solved.
     *
     * @param array $players Players map.
     *
     * @return bool
     */
    public function bothSolved(array $players): bool
    {
        $solved = 0;
        foreach ($players as $p) {
            if (! empty($p['solved_at'])) {
                $solved++;
            }
        }
        return $solved >= 2;
    }

    /**
     * Build common public view (you + opponent summary).
     *
     * @param array $players Players map.
     * @param int   $userId  Current user id.
     *
     * @return array{you:array, opponent:array}
     */
    public function buildPublicPlayers(array $players, int $userId): array
    {
        $self = (array) ($players[$userId] ?? []);
        $opp = [];

        foreach ($players as $uid => $st) {
            if ((int) $uid !== $userId) {
                $opp = (array) $st;
                break;
            }
        }

        return [
            'you' => [
                'started_at' => $self['started_at'] ?? null,
                'solved_at' => $self['solved_at'] ?? null,
                'guess_count' => (int) ($self['guess_count'] ?? 0),
                'guesses' => $self['guesses'] ?? [],
            ],
            'opponent' => [
                'solved_at' => $opp['solved_at'] ?? null,
                'guess_count' => (int) ($opp['guess_count'] ?? 0),
            ],
        ];
    }
}
