<?php

namespace App\Services\Pvp\Rounds;

use App\Models\PvpMatch;
use App\Services\Pvp\PvpParticipantService;
use App\Services\Pvp\PvpRoundTieBreakerService;
use App\Services\Pvp\PvpSecretPlayerService;
use Illuminate\Support\Arr;

/**
 * Draft indices round handler (PvP).
 */
readonly class DraftRoundHandler implements PvpRoundHandlerInterface
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
        return 'draft';
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
                'draft' => [
                    'phase' => 'draft',
                    'secret_player_id' => $secretId,
                    'allowed_keys' => $this->allowedKeys((string) $match->game),
                    'picked_keys' => [],
                    'first_picker_user_id' => null,
                    'pick_plan' => [],
                    'pick_index' => 0,
                    'revealed_hints' => [],
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
     * @return array<mixed>
     */
    public function publicState(PvpMatch $match, int $userId): array
    {
        $state = $match->state ?? [];
        $data = (array) Arr::get($state, 'round_data.draft', []);
        $phase = (string) ($data['phase'] ?? 'draft');

        if ($phase === 'draft') {
            $turnUserId = Arr::get($state, 'turn_user_id');

            return [
                'phase' => 'draft',
                'turn_user_id' => is_numeric($turnUserId) ? (int) $turnUserId : null,
                'can_choose_order' => $this->canChooseOrder($match, $data, $userId),
                'allowed_keys' => array_values((array) ($data['allowed_keys'] ?? [])),
                'picked_keys' => array_values((array) ($data['picked_keys'] ?? [])),
                'pick_index' => (int) ($data['pick_index'] ?? 0),
                'pick_plan' => array_values((array) ($data['pick_plan'] ?? [])),
            ];
        }

        $players = (array) ($data['players'] ?? []);
        $view = $this->guessState->buildPublicPlayers($players, $userId);

        return [
            'phase' => 'guess',
            'revealed_hints' => (array) ($data['revealed_hints'] ?? []),
            'you' => $view['you'],
            'opponent' => $view['opponent'],
        ];
    }

    /**
     * Handle a participant action for this round.
     *
     * @param PvpMatch     $match  Match instance.
     * @param int          $userId Acting user id.
     * @param array<mixed> $action Action payload.
     *
     * @return PvpRoundResult
     */
    public function handleAction(PvpMatch $match, int $userId, array $action): PvpRoundResult
    {
        $state = $match->state ?? [];
        $data = (array) Arr::get($state, 'round_data.draft', []);
        $phase = (string) ($data['phase'] ?? 'draft');
        $type = (string) ($action['type'] ?? '');

        if ($phase === 'draft') {
            return $this->handleDraftPhase($match, $userId, $action, $data, $type);
        }

        if ($phase === 'guess') {
            return $this->handleGuessPhase($match, $userId, $action, $data, $type);
        }

        abort(500, 'Invalid round phase.');
    }

    /**
     * Handle actions during the draft phase.
     *
     * @param PvpMatch     $match  Match instance.
     * @param int          $userId Acting user id.
     * @param array $action Action payload.
     * @param array $data   Draft round data.
     * @param string       $type   Action type.
     *
     * @return PvpRoundResult
     */
    private function handleDraftPhase(PvpMatch $match, int $userId, array $action, array $data, string $type): PvpRoundResult
    {
        if ($type === 'choose_draft_order') {
            return $this->handleChooseDraftOrder($match, $userId, $action, $data);
        }

        $turnUserId = Arr::get($match->state ?? [], 'turn_user_id');

        if (!is_numeric($turnUserId) || (int) $turnUserId !== $userId) {
            abort(409, 'Not your turn.');
        }

        if ($type !== 'pick_hint') {
            abort(422, 'Invalid action.');
        }

        return $this->handlePickHint($match, $userId, $action, $data);
    }

    /**
     * Handle actions during the guess phase.
     *
     * @param PvpMatch     $match  Match instance.
     * @param int          $userId Acting user id.
     * @param array $action Action payload.
     * @param array $data   Draft round data.
     * @param string       $type   Action type.
     *
     * @return PvpRoundResult
     */
    private function handleGuessPhase(PvpMatch $match, int $userId, array $action, array $data, string $type): PvpRoundResult
    {
        $playerId = $this->guessPayload->requireGuessPlayerId($action);

        $secretId = (int) ($data['secret_player_id'] ?? 0);
        if ($secretId <= 0) {
            abort(500, 'Secret missing.');
        }

        $applied = $this->guessApply->apply($data, $userId, $playerId, $secretId);

        $data = $applied['data'];
        $players = $applied['players'];
        $correct = $applied['correct'];
        $nowIso = $applied['nowIso'];
        $guessCount = $applied['guessCount'];

        $events = [[
            'type' => 'draft_guess_made',
            'user_id' => null,
            'payload' => [
                'actor_user_id' => $userId,
                'guess_order' => $guessCount,
                'correct' => $correct,
            ],
        ]];

        if ($correct) {
            $events[] = [
                'type' => 'draft_solved',
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
                'draft' => $data,
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
                'type' => 'draft_round_resolved',
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
     * Handle chooser selecting who picks first.
     *
     * @param PvpMatch     $match  Match instance.
     * @param int          $userId Acting user id.
     * @param array $action Action payload.
     * @param array $data   Draft round data.
     *
     * @return PvpRoundResult
     */
    private function handleChooseDraftOrder(PvpMatch $match, int $userId, array $action, array $data): PvpRoundResult
    {
        $state = $match->state ?? [];

        if (($state['turn_user_id'] ?? null) !== null) {
            abort(409, 'Draft already started.');
        }

        $chooser = (int) (($state['chooser_user_id'] ?? 0));
        if ($chooser <= 0 || $chooser !== $userId) {
            abort(403, 'Only the chooser can decide order.');
        }

        $firstPicker = (int) ($action['first_picker_user_id'] ?? 0);
        [$u1, $u2] = $this->participants->getTwoUserIds((int) $match->id);

        if ($firstPicker !== $u1 && $firstPicker !== $u2) {
            abort(422, 'Invalid first_picker_user_id.');
        }

        $secondPicker = $this->participants->opponentOf([$u1, $u2], $firstPicker);
        $pickPlan = [$firstPicker, $secondPicker, $secondPicker, $firstPicker];

        $data['first_picker_user_id'] = $firstPicker;
        $data['pick_plan'] = $pickPlan;
        $data['pick_index'] = 0;

        $patch = [
            'turn_user_id' => $pickPlan[0],
            'round_data' => [
                'draft' => $data,
            ],
        ];

        $events = [[
            'type' => 'draft_order_chosen',
            'user_id' => null,
            'payload' => [
                'first_picker_user_id' => $firstPicker,
            ],
        ]];

        return PvpRoundResult::ongoing($patch, $events);
    }

    /**
     * Handle a hint key pick during draft.
     *
     * @param PvpMatch     $match  Match instance.
     * @param int          $userId Acting user id.
     * @param array $action Action payload.
     * @param array $data   Draft round data.
     *
     * @return PvpRoundResult
     */
    private function handlePickHint(PvpMatch $match, int $userId, array $action, array $data): PvpRoundResult
    {
        $key = (string) ($action['key'] ?? '');
        if ($key === '') {
            abort(422, 'Invalid key.');
        }

        $allowed = (array) ($data['allowed_keys'] ?? []);
        if (!in_array($key, $allowed, true)) {
            abort(422, 'Key not allowed.');
        }

        $picked = array_values((array) ($data['picked_keys'] ?? []));
        if (in_array($key, $picked, true)) {
            abort(409, 'Key already picked.');
        }

        $pickPlan = (array) ($data['pick_plan'] ?? []);
        $idx = (int) ($data['pick_index'] ?? 0);

        if (!isset($pickPlan[$idx]) || (int) $pickPlan[$idx] !== $userId) {
            abort(409, 'Not your pick.');
        }

        $picked[] = $key;
        $data['picked_keys'] = $picked;
        $data['pick_index'] = $idx + 1;

        $events = [[
            'type' => 'draft_hint_picked',
            'user_id' => null,
            'payload' => [
                'actor_user_id' => $userId,
                'key' => $key,
                'picked_count' => count($picked),
            ],
        ]];

        if (count($picked) >= 4) {
            $revealed = $this->hints->buildRevealed((string) $match->game, (int) $data['secret_player_id'], $picked);

            $data['phase'] = 'guess';
            $data['revealed_hints'] = $revealed;

            $patch = [
                'turn_user_id' => null,
                'round_data' => [
                    'draft' => $data,
                ],
            ];

            $events[] = [
                'type' => 'draft_guess_phase_started',
                'user_id' => null,
                'payload' => [
                    'picked_keys' => $picked,
                    'revealed_hints' => $revealed,
                ],
            ];

            return PvpRoundResult::ongoing($patch, $events);
        }

        $nextTurn = (int) (($pickPlan[$data['pick_index']] ?? 0));
        if ($nextTurn <= 0) {
            abort(500, 'Invalid pick plan.');
        }

        $patch = [
            'turn_user_id' => $nextTurn,
            'round_data' => [
                'draft' => $data,
            ],
        ];

        return PvpRoundResult::ongoing($patch, $events);
    }

    /**
     * Determine whether the current user can choose the draft order.
     *
     * @param PvpMatch     $match  Match instance.
     * @param array<mixed> $data   Draft round data.
     * @param int          $userId Current user id.
     *
     * @return bool
     */
    private function canChooseOrder(PvpMatch $match, array $data, int $userId): bool
    {
        $state = $match->state ?? [];
        if (($state['turn_user_id'] ?? null) !== null) {
            return false;
        }

        $firstPicker = $data['first_picker_user_id'] ?? null;
        if ($firstPicker !== null) {
            return false;
        }

        $chooser = (int) (($state['chooser_user_id'] ?? 0));
        return $chooser > 0 && $chooser === $userId;
    }

    /**
     * Allowed draft keys by game.
     *
     * @param string $game Game identifier.
     *
     * @return array<int,string>
     */
    private function allowedKeys(string $game): array
    {
        if ($game !== 'kcdle') {
            return ['country_code', 'role_id'];
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
}
