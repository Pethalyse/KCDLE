<?php

namespace App\Services\Pvp\Rounds;

use App\Models\PvpMatch;
use App\Services\Pvp\PvpParticipantService;
use App\Services\Pvp\PvpRoundTieBreakerService;
use App\Services\Pvp\PvpSecretPlayerService;
use Illuminate\Support\Arr;

/**
 * Locked infos round handler (PvP).
 */
readonly class LockedInfosRoundHandler implements PvpRoundHandlerInterface
{
    public function __construct(
        private PvpParticipantService     $participants,
        private PvpSecretPlayerService    $secrets,
        private GuessRoundStateService    $guessState,
        private GuessActionPayloadService $guessPayload,
        private GuessRoundApplyService    $guessApply,
        private HintValueService          $hints,
        private PvpRoundTieBreakerService $tieBreaker
    ) {
    }

    /**
     * Return the unique round type identifier handled by this implementation.
     *
     * @return string
     */
    public function type(): string
    {
        return 'locked_infos';
    }

    public function name(): string
    {
        return "Informations limitées";
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

        $now = now()->toISOString();
        $players = $this->guessState->initPlayers([$u1, $u2], $now);

        $keys = $this->pickTwoKeys((string) $match->game);
        $revealed = $this->hints->buildRevealed((string) $match->game, $secretId, $keys);

        return [
            'turn_user_id' => null,
            'round_data' => [
                'locked_infos' => [
                    'secret_player_id' => $secretId,
                    'revealed' => $revealed,
                    'players' => $players,
                ],
            ],
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
        $data = (array) Arr::get($match->state ?? [], 'round_data.locked_infos', []);
        $players = (array) ($data['players'] ?? []);
        $view = $this->guessState->buildPublicPlayers($players, $userId);

        return [
            'phase' => 'guess',
            'revealed' => (array) ($data['revealed'] ?? []),
            'you' => $view['you'],
            'opponent' => $view['opponent'],
        ];
    }

    /**
     * Handle a participant action for this round.
     *
     * @param PvpMatch     $match  Match instance.
     * @param int          $userId Acting user id.
     * @param array $action Action payload.
     *
     * @return PvpRoundResult
     */
    public function handleAction(PvpMatch $match, int $userId, array $action): PvpRoundResult
    {
        $playerId = $this->guessPayload->requireGuessPlayerId($action);

        $state = $match->state ?? [];
        $data = (array) Arr::get($state, 'round_data.locked_infos', []);
        $secretId = (int) ($data['secret_player_id'] ?? 0);

        if ($secretId <= 0) {
            abort(500, 'Round not initialized.');
        }

        $applied = $this->guessApply->apply($data, $userId, $playerId, $secretId);

        $data = $applied['data'];
        $players = $applied['players'];
        $correct = $applied['correct'];
        $nowIso = $applied['nowIso'];
        $guessCount = $applied['guessCount'];

        $events = [[
            'type' => 'locked_guess_made',
            'user_id' => null,
            'payload' => [
                'actor_user_id' => $userId,
                'guess_order' => $guessCount,
                'correct' => $correct,
            ],
        ]];

        if ($correct) {
            $events[] = [
                'type' => 'locked_solved',
                'user_id' => null,
                'payload' => [
                    'actor_user_id' => $userId,
                    'guess_count' => $guessCount,
                    'solved_at' => $nowIso,
                ],
            ];
        }

        $patch = [
            'turn_user_id' => null,
            'round_data' => [
                'locked_infos' => $data,
            ],
        ];

        if ($this->guessState->bothSolved($players)) {
            $uids = array_map('intval', array_keys($players));

            if (count($uids) !== 2) {
                abort(500, 'Invalid match players.');
            }

            $winner = $this->tieBreaker->resolve(
                $uids[0],
                (array) $players[$uids[0]],
                $uids[1],
                (array) $players[$uids[1]]
            );

            $events[] = [
                'type' => 'locked_round_resolved',
                'user_id' => null,
                'payload' => [
                    'winner_user_id' => $winner,
                ],
            ];

            return PvpRoundResult::ended($winner, $patch, $events);
        }

        return PvpRoundResult::ongoing($patch, $events);
    }

    /**
     * Pick exactly two keys to reveal at round start.
     *
     * @param string $game Game identifier.
     *
     * @return array{0:string,1:string}
     */
    private function pickTwoKeys(string $game): array
    {
        $keys = $this->allowedKeys($game);
        shuffle($keys);

        $a = $keys[0] ?? null;
        $b = $keys[1] ?? null;

        if (!$a || !$b || $a === $b) {
            abort(500, 'Unable to pick reveal keys.');
        }

        return [$a, $b];
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
        $keys = config('pvp.locked_infos.keys.' . $game);

        if (!is_array($keys) || count($keys) < 2) {
            abort(500, 'Invalid keys.');
        }

        $keys = array_values(array_unique(array_map('strval', $keys)));

        if (count($keys) < 2) {
            abort(500, 'Invalid keys.');
        }

        return $keys;
    }
}
