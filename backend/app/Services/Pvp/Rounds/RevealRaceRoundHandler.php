<?php

namespace App\Services\Pvp\Rounds;

use App\Models\PvpMatch;
use App\Services\Pvp\PvpParticipantService;
use App\Services\Pvp\PvpSecretPlayerService;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Throwable;

/**
 * Reveal-race (Duel final) round handler (PvP).
 *
 * Rules:
 * - One secret player shared by both participants.
 * - Hints are revealed progressively over time (not per guess).
 * - The first player who locks the correct answer wins the round instantly.
 * - A wrong lock blocks the player for 5 seconds (cannot lock again during that time).
 */
readonly class RevealRaceRoundHandler implements PvpRoundHandlerInterface
{
    private const REVEAL_INTERVAL_SECONDS = 8;
    private const WRONG_LOCK_COOLDOWN_SECONDS = 5;

    public function __construct(
        private PvpParticipantService  $participants,
        private PvpSecretPlayerService $secrets,
        private HintValueService       $hints,
    ) {
    }

    /**
     * Return the unique round type identifier handled by this implementation.
     *
     * @return string
     */
    public function type(): string
    {
        return 'reveal_race';
    }

    /**
     * Initialize round state when the round starts.
     *
     * @param PvpMatch $match Match instance.
     *
     * @return array
     */
    public function initialize(PvpMatch $match): array
    {
        [$u1, $u2] = $this->participants->getTwoUserIds((int) $match->id);

        $secretId = $this->secrets->pickSecretId($match);
        if ($secretId <= 0) {
            abort(500, 'Unable to select a secret player.');
        }

        $now = now();
        $nowIso = $now->toISOString();

        $allowedKeys = $this->allowedKeys((string) $match->game);

        return [
            'turn_user_id' => null,
            'round_data' => [
                'reveal_race' => [
                    'secret_player_id' => $secretId,
                    'started_at' => $nowIso,
                    'next_reveal_at' => $now->addSeconds(self::REVEAL_INTERVAL_SECONDS)->toISOString(),
                    'allowed_keys' => array_values($allowedKeys),
                    'revealed_keys' => [],
                    'revealed_hints' => [],
                    'winner_user_id' => null,
                    'players' => [
                        $u1 => [
                            'lock_count' => 0,
                            'last_lock_at' => null,
                            'lock_blocked_until' => null,
                        ],
                        $u2 => [
                            'lock_count' => 0,
                            'last_lock_at' => null,
                            'lock_blocked_until' => null,
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Return the public round state for a participant.
     *
     * This method also performs time-based reveal progression, so simply polling
     * this endpoint will make hints appear over time without requiring guesses.
     *
     * @param PvpMatch $match  Match instance.
     * @param int      $userId Requesting user id.
     *
     * @return array
     */
    public function publicState(PvpMatch $match, int $userId): array
    {
        $state = $match->state ?? [];
        $data = (array) Arr::get($state, 'round_data.reveal_race', []);
        $data = $this->applyTimeReveals($match, $data);

        $players = (array) ($data['players'] ?? []);
        $you = (array) ($players[$userId] ?? []);
        $opp = $this->opponentState($players, $userId);

        $now = now();
        $blockedUntil = $this->parseIso((string) ($you['lock_blocked_until'] ?? ''));
        $blockedMs = 0;

        if ($blockedUntil !== null && $blockedUntil->greaterThan($now)) {
            $blockedMs = (int) $blockedUntil->diffInMilliseconds($now);
        }

        return [
            'phase' => 'lock',
            'winner_user_id' => is_numeric($data['winner_user_id'] ?? null) ? (int) $data['winner_user_id'] : null,
            'revealed_hints' => (array) ($data['revealed_hints'] ?? []),
            'you' => [
                'lock_count' => (int) ($you['lock_count'] ?? 0),
                'blocked_ms' => $blockedMs,
            ],
            'opponent' => [
                'lock_count' => (int) ($opp['lock_count'] ?? 0),
            ],
            'server_time' => $now->toISOString(),
        ];
    }

    /**
     * Handle a participant action for this round.
     *
     * Supported action:
     * - { type: "lock", player_id: int }
     *
     * @param PvpMatch     $match  Match instance.
     * @param int          $userId Acting user id.
     * @param array $action Action payload.
     *
     * @return PvpRoundResult
     */
    public function handleAction(PvpMatch $match, int $userId, array $action): PvpRoundResult
    {
        $type = (string) ($action['type'] ?? '');

        if ($type !== 'lock') {
            abort(422, 'Invalid action.');
        }

        $playerId = (int) ($action['player_id'] ?? 0);
        if ($playerId <= 0) {
            abort(422, 'Invalid player_id.');
        }

        $state = $match->state ?? [];
        $data = (array) Arr::get($state, 'round_data.reveal_race', []);
        $data = $this->applyTimeReveals($match, $data);

        $winnerAlready = (int) ($data['winner_user_id'] ?? 0);
        if ($winnerAlready > 0) {
            abort(409, 'Round already ended.');
        }

        $players = (array) ($data['players'] ?? []);
        if (!isset($players[$userId])) {
            abort(403, 'Not a participant.');
        }

        $now = now();
        $nowIso = $now->toISOString();

        $blockedUntil = $this->parseIso((string) ($players[$userId]['lock_blocked_until'] ?? ''));
        if ($blockedUntil !== null && $blockedUntil->greaterThan($now)) {
            abort(409, 'You are temporarily blocked.');
        }

        $secretId = (int) ($data['secret_player_id'] ?? 0);
        if ($secretId <= 0) {
            abort(500, 'Round not initialized.');
        }

        $players[$userId]['lock_count'] = (int) ($players[$userId]['lock_count'] ?? 0) + 1;
        $players[$userId]['last_lock_at'] = $nowIso;

        $correct = ($playerId === $secretId);

        $events = [[
            'type' => 'reveal_race_lock',
            'user_id' => null,
            'payload' => [
                'actor_user_id' => $userId,
                'player_id' => $playerId,
                'correct' => $correct,
                'lock_count' => (int) $players[$userId]['lock_count'],
            ],
        ]];

        if ($correct) {
            $data['winner_user_id'] = $userId;
            $data['players'] = $players;

            $events[] = [
                'type' => 'reveal_race_round_resolved',
                'user_id' => null,
                'payload' => [
                    'winner_user_id' => $userId,
                    'locked_at' => $nowIso,
                ],
            ];

            $patch = [
                'turn_user_id' => null,
                'round_data' => [
                    'reveal_race' => $data,
                ],
            ];

            return PvpRoundResult::ended($userId, $patch, $events);
        }

        $players[$userId]['lock_blocked_until'] = $now->copy()
            ->addSeconds(self::WRONG_LOCK_COOLDOWN_SECONDS)
            ->toISOString();

        $data['players'] = $players;

        $patch = [
            'turn_user_id' => null,
            'round_data' => [
                'reveal_race' => $data,
            ],
        ];

        return PvpRoundResult::ongoing($patch, $events);
    }

    /**
     * Apply time-driven reveals to the round data.
     *
     * @param PvpMatch     $match Match instance.
     * @param array $data  Round data.
     *
     * @return array
     */
    private function applyTimeReveals(PvpMatch $match, array $data): array
    {
        $winnerAlready = (int) ($data['winner_user_id'] ?? 0);
        if ($winnerAlready > 0) {
            return $data;
        }

        $secretId = (int) ($data['secret_player_id'] ?? 0);
        if ($secretId <= 0) {
            return $data;
        }

        $allowed = array_values((array) ($data['allowed_keys'] ?? []));
        $revealedKeys = array_values((array) ($data['revealed_keys'] ?? []));

        $nextRevealAt = $this->parseIso((string) ($data['next_reveal_at'] ?? ''));
        if ($nextRevealAt === null) {
            return $data;
        }

        $now = now();

        while ($nextRevealAt->lessThanOrEqualTo($now)) {
            $remaining = array_values(array_diff($allowed, $revealedKeys));
            if (count($remaining) === 0) {
                break;
            }

            $key = $remaining[0];
            $revealedKeys[] = $key;

            $nextRevealAt = $nextRevealAt->copy()->addSeconds(self::REVEAL_INTERVAL_SECONDS);
        }

        $revealedKeys = array_values(array_unique($revealedKeys));

        $data['revealed_keys'] = $revealedKeys;
        $data['next_reveal_at'] = $nextRevealAt->toISOString();
        $data['revealed_hints'] = $this->hints->buildRevealed((string) $match->game, $secretId, $revealedKeys);

        return $data;
    }

    /**
     * Allowed reveal keys by game.
     *
     * @param string $game Game identifier.
     *
     * @return array<int,string>
     */
    private function allowedKeys(string $game): array
    {
        if ($game !== 'kcdle') {
            return ['country_code', 'role_id', 'game_id'];
        }

        return [
            'current_team_id',
            'previous_team_id',
            'trophies_count',
            'first_official_year',
            'country_code',
            'role_id',
            'game_id',
        ];
    }

    /**
     * Parse an ISO datetime string to a Carbon instance.
     *
     * @param string $iso ISO string.
     *
     * @return Carbon|null
     */
    private function parseIso(string $iso): ?Carbon
    {
        if ($iso === '') {
            return null;
        }

        try {
            return now()->parse($iso);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Get the opponent state from players map.
     *
     * @param array $players Players state map.
     * @param int          $userId  Current user id.
     *
     * @return array
     */
    private function opponentState(array $players, int $userId): array
    {
        foreach ($players as $uid => $st) {
            if ((int) $uid !== $userId) {
                return (array) $st;
            }
        }

        return [];
    }
}
