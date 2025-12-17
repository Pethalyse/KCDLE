<?php

namespace App\Services\Pvp\Rounds;

use App\Models\Player;
use App\Models\PvpMatch;
use App\Services\Dle\PlayerComparisonService;
use App\Services\Pvp\PvpParticipantService;
use App\Services\Pvp\PvpRoundTieBreakerService;
use App\Services\Pvp\PvpSecretPlayerService;
use Illuminate\Support\Arr;

/**
 * Classic DLE round handler (PvP).
 */
readonly class ClassicRoundHandler implements PvpRoundHandlerInterface
{
    public function __construct(
        private PvpParticipantService     $participants,
        private PvpSecretPlayerService    $secrets,
        private GuessRoundStateService    $guessState,
        private GuessActionPayloadService $guessPayload,
        private GuessRoundApplyService    $guessApply,
        private PlayerComparisonService   $comparison,
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
        return 'classic';
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

        return [
            'turn_user_id' => null,
            'round_data' => [
                'classic' => [
                    'secret_player_id' => $secretId,
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
        $data = (array) Arr::get($match->state ?? [], 'round_data.classic', []);
        $players = (array) ($data['players'] ?? []);
        $view = $this->guessState->buildPublicPlayers($players, $userId);

        return [
            'phase' => 'guess',
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
        $data = (array) Arr::get($state, 'round_data.classic', []);
        $secretId = (int) ($data['secret_player_id'] ?? 0);

        if ($secretId <= 0) {
            abort(500, 'Round not initialized.');
        }

        $secretWrapper = Player::resolvePlayerModel((string) $match->game, $secretId);
        $guessWrapper = Player::resolvePlayerModel((string) $match->game, $playerId);

        if (!$secretWrapper || !$guessWrapper) {
            abort(422, 'Invalid player.');
        }

        $comparison = $this->comparison->comparePlayers($secretWrapper, $guessWrapper, (string) $match->game);

        $applied = $this->guessApply->apply($data, $userId, $playerId, $secretId);

        $data = $applied['data'];
        $players = $applied['players'];
        $correct = $applied['correct'];
        $nowIso = $applied['nowIso'];
        $guessCount = $applied['guessCount'];

        $events = [[
            'type' => 'classic_guess_made',
            'user_id' => null,
            'payload' => [
                'actor_user_id' => $userId,
                'guess_order' => $guessCount,
                'correct' => $correct,
                'comparison' => $comparison,
            ],
        ]];

        if ($correct) {
            $events[] = [
                'type' => 'classic_solved',
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
                'classic' => $data,
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
                'type' => 'classic_round_resolved',
                'user_id' => null,
                'payload' => [
                    'winner_user_id' => $winner,
                ],
            ];

            return PvpRoundResult::ended($winner, $patch, $events);
        }

        return PvpRoundResult::ongoing($patch, $events);
    }
}
