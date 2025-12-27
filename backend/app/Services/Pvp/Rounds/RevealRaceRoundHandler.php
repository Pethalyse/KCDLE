<?php

namespace App\Services\Pvp\Rounds;

use App\Models\PvpMatch;
use App\Services\Pvp\PvpParticipantService;
use App\Services\Pvp\PvpSecretPlayerService;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Throwable;

/**
 * Reveal-race round handler (PvP).
 */
readonly class RevealRaceRoundHandler implements PvpRoundHandlerInterface
{
    private const REVEAL_INTERVAL_SECONDS = 8;
    private const WRONG_GUESS_COOLDOWN_SECONDS = 5;

    public function __construct(
        private PvpParticipantService     $participants,
        private PvpSecretPlayerService    $secrets,
        private GuessRoundStateService    $guessState,
        private GuessActionPayloadService $guessPayload,
        private GuessRoundApplyService    $guessApply,
        private HintValueService          $hints
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

    public function name(): string
    {
        return 'Course contre la montre';
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

        $players = $this->guessState->initPlayers([$u1, $u2], $nowIso);

        foreach ($players as $uid => $st) {
            $st['last_lock_at'] = null;
            $st['lock_blocked_until'] = null;
            $players[(int) $uid] = $st;
        }

        $allowedKeys = $this->allowedKeys((string) $match->game);

        return [
            'turn_user_id' => null,
            'round_data' => [
                'reveal_race' => [
                    'secret_player_id' => $secretId,
                    'started_at' => $nowIso,
                    'next_reveal_at' => $now->copy()->addSeconds(self::REVEAL_INTERVAL_SECONDS)->toISOString(),
                    'allowed_keys' => array_values($allowedKeys),
                    'revealed_keys' => [],
                    'revealed' => [],
                    'winner_user_id' => null,
                    'players' => $players,
                ],
            ],
        ];
    }

    /**
     * Passive tick hook.
     *
     * @param PvpMatch $match Locked match instance.
     *
     * @return array{statePatch?:array, events?:array}
     */
    public function tick(PvpMatch $match): array
    {
        $state = $match->state ?? [];
        $data = (array) Arr::get($state, 'round_data.reveal_race', []);

        $beforeKeys = array_values((array) ($data['revealed_keys'] ?? []));
        $beforeNext = (string) ($data['next_reveal_at'] ?? '');

        $updated = $this->applyTimeReveals($match, $data);

        $afterKeys = array_values((array) ($updated['revealed_keys'] ?? []));
        $afterNext = (string) ($updated['next_reveal_at'] ?? '');

        if ($this->sameStringList($beforeKeys, $afterKeys) && $beforeNext === $afterNext) {
            return [];
        }

        $newKeys = array_values(array_diff($afterKeys, $beforeKeys));

        $events = [];
        if (!empty($newKeys)) {
            $events[] = [
                'type' => 'reveal_race_reveal',
                'user_id' => null,
                'payload' => [
                    'keys' => $newKeys,
                ],
            ];
        }

        return [
            'statePatch' => [
                'round_data' => [
                    'reveal_race' => $updated,
                ],
            ],
            'events' => $events,
        ];
    }

    /**
     * Return the public round state for a participant.
     *
     * @param PvpMatch $match  Match instance.
     * @param int      $userId Requesting user id.
     *
     * @return array
     */
    public function publicState(PvpMatch $match, int $userId): array
    {
        $data = (array) Arr::get($match->state ?? [], 'round_data.reveal_race', []);
        $players = (array) ($data['players'] ?? []);

        $view = $this->guessState->buildPublicPlayers($players, $userId);

        $youFull = (array) ($players[$userId] ?? []);
        $now = now();

        $blockedUntil = $this->parseIso((string) ($youFull['lock_blocked_until'] ?? ''));
        $blockedMs = 0;

        if ($blockedUntil !== null && $blockedUntil->greaterThan($now)) {
            $blockedMs = (int) $blockedUntil->diffInMilliseconds($now);
        }

        return [
            'phase' => 'guess',
            'winner_user_id' => is_numeric($data['winner_user_id'] ?? null) ? (int) $data['winner_user_id'] : null,
            'revealed' => (array) ($data['revealed'] ?? []),
            'next_reveal_at' => (string) ($data['next_reveal_at'] ?? ''),
            'server_time' => $now->toISOString(),
            'you' => array_merge($view['you'], [
                'blocked_ms' => $blockedMs,
                'last_lock_at' => $youFull['last_lock_at'] ?? null,
                'lock_blocked_until' => $youFull['lock_blocked_until'] ?? null,
            ]),
            'opponent' => $view['opponent'],
        ];
    }

    /**
     * Handle a participant action for this round.
     *
     * Supported action:
     * - { type: "guess", player_id: int }
     *
     * @param PvpMatch $match  Match instance.
     * @param int      $userId Acting user id.
     * @param array    $action Action payload.
     *
     * @return PvpRoundResult
     */
    public function handleAction(PvpMatch $match, int $userId, array $action): PvpRoundResult
    {
        $playerId = $this->guessPayload->requireGuessPlayerId($action);

        $state = $match->state ?? [];
        $data = (array) Arr::get($state, 'round_data.reveal_race', []);

        $winnerAlready = (int) ($data['winner_user_id'] ?? 0);
        if ($winnerAlready > 0) {
            abort(409, 'Round already ended.');
        }

        $players = (array) ($data['players'] ?? []);
        if (!isset($players[$userId])) {
            abort(403, 'Not a participant.');
        }

        $secretId = (int) ($data['secret_player_id'] ?? 0);
        if ($secretId <= 0) {
            abort(500, 'Round not initialized.');
        }

        $now = now();
        $nowIso = $now->toISOString();

        $blockedUntil = $this->parseIso((string) ($players[$userId]['lock_blocked_until'] ?? ''));
        if ($blockedUntil !== null && $blockedUntil->greaterThan($now)) {
            abort(409, 'You are temporarily blocked.');
        }

        $players[$userId]['last_lock_at'] = $nowIso;

        $applied = $this->guessApply->apply($data, $userId, $playerId, $secretId);

        $data = $applied['data'];
        $players = (array) ($data['players'] ?? []);
        $correct = (bool) $applied['correct'];
        $guessCount = (int) $applied['guessCount'];

        if (!$correct) {
            $players[$userId]['lock_blocked_until'] = $now->copy()
                ->addSeconds(self::WRONG_GUESS_COOLDOWN_SECONDS)
                ->toISOString();
            $data['players'] = $players;
        }

        $events = [[
            'type' => 'reveal_race_guess_made',
            'user_id' => null,
            'payload' => [
                'actor_user_id' => $userId,
                'guess_order' => $guessCount,
                'correct' => $correct,
            ],
        ]];

        $patch = [
            'turn_user_id' => null,
            'round_data' => [
                'reveal_race' => $data,
            ],
        ];

        if ($correct) {
            $data['winner_user_id'] = $userId;

            $events[] = [
                'type' => 'reveal_race_solved',
                'user_id' => null,
                'payload' => [
                    'actor_user_id' => $userId,
                    'guess_count' => $guessCount,
                    'solved_at' => $nowIso,
                ],
            ];

            $events[] = [
                'type' => 'reveal_race_round_resolved',
                'user_id' => null,
                'payload' => [
                    'winner_user_id' => $userId,
                ],
            ];

            $patch['round_data']['reveal_race'] = $data;

            return PvpRoundResult::ended($userId, $patch, $events);
        }

        return PvpRoundResult::ongoing($patch, $events);
    }

    /**
     * Apply time-driven reveals to the round data.
     *
     * @param PvpMatch $match Match instance.
     * @param array    $data  Round data.
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
        $data['revealed'] = $this->hints->buildRevealed((string) $match->game, $secretId, $revealedKeys);

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
        $keys = config('pvp.reveal_race.keys.' . $game);

        if (!is_array($keys) || count($keys) < 1) {
            abort(500, 'Reveal race keys are not configured for game: ' . $game);
        }

        return array_values(array_unique(array_map('strval', $keys)));
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
     * Compare two string lists ignoring order and duplicates.
     *
     * @param array<int,string> $a First list.
     * @param array<int,string> $b Second list.
     *
     * @return bool
     */
    private function sameStringList(array $a, array $b): bool
    {
        $aa = array_values(array_unique(array_map('strval', $a)));
        $bb = array_values(array_unique(array_map('strval', $b)));
        sort($aa);
        sort($bb);
        return $aa === $bb;
    }
}
