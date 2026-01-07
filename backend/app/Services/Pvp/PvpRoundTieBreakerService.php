<?php

namespace App\Services\Pvp;

/**
 * Resolves winners for guess-based PvP rounds using common tie-break rules.
 *
 * Rules:
 * 1) Fewer guesses wins
 * 2) If equal, faster solve time wins
 * 3) If still equal, lowest user id wins (deterministic fallback)
 */
class PvpRoundTieBreakerService
{
    /**
     * Determine the winner between two players.
     *
     * Expected player structure:
     * [
     *   'guess_count' => int,
     *   'started_at' => string|null,
     *   'solved_at' => string|null
     * ]
     *
     * @param int   $userA First user id.
     * @param array $a     First player data.
     * @param int   $userB Second user id.
     * @param array $b     Second player data.
     *
     * @return int Winner user id.
     */
    public function resolve(int $userA, array $a, int $userB, array $b): int
    {
        $aGuesses = (int) ($a['guess_count'] ?? 0);
        $bGuesses = (int) ($b['guess_count'] ?? 0);

        if ($aGuesses !== $bGuesses) {
            return $aGuesses < $bGuesses ? $userA : $userB;
        }

        $aTime = $this->solveDurationMs($a);
        $bTime = $this->solveDurationMs($b);

        if ($aTime !== $bTime) {
            return $aTime < $bTime ? $userA : $userB;
        }

        return min($userA, $userB);
    }

    /**
     * Compute solve duration in milliseconds.
     *
     * @param array $player Player data.
     *
     * @return int
     */
    private function solveDurationMs(array $player): int
    {
        $startedAt = (string) ($player['started_at'] ?? '');
        $solvedAt = (string) ($player['solved_at'] ?? '');

        $s = strtotime($startedAt);
        $e = strtotime($solvedAt);

        if ($s === false || $e === false) {
            return PHP_INT_MAX;
        }

        return max(0, ($e - $s) * 1000);
    }
}
