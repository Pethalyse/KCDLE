<?php

namespace App\Services\Pvp\Rounds;

use App\Models\Player;
use App\Models\PvpMatch;
use App\Services\Dle\GamePlayerService;
use App\Services\Pvp\PvpParticipantService;
use App\Services\Pvp\PvpSecretPlayerService;
use Illuminate\Support\Arr;

/**
 * Turn-based "Whois" round handler (PvP).
 */
readonly class WhoisRoundHandler implements PvpRoundHandlerInterface
{
    public function __construct(
        private PvpParticipantService  $participants,
        private PvpSecretPlayerService $secrets,
        private GamePlayerService      $players,
        private WhoisQuestionService   $questions
    ) {
    }

    /**
     * Return the unique round type identifier handled by this implementation.
     *
     * @return string
     */
    public function type(): string
    {
        return 'whois';
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

        $pool = $this->players->listPlayers((string) $match->game, true);
        $candidateIds = $pool->pluck('id')->map(fn ($v) => (int) $v)->values()->all();

        return [
            'turn_user_id' => null,
            'round_data' => [
                'whois' => [
                    'secret_player_id' => $secretId,
                    'candidate_ids' => $candidateIds,
                    'banned_ids' => [],
                    'players' => [
                        $u1 => ['asked' => 0, 'wrong_guesses' => 0],
                        $u2 => ['asked' => 0, 'wrong_guesses' => 0],
                    ],
                    'started_at' => now()->toISOString(),
                    'winner_user_id' => null,
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
        $data = (array) Arr::get($match->state ?? [], 'round_data.whois', []);
        $players = (array) ($data['players'] ?? []);
        $turnUserId = Arr::get($match->state ?? [], 'turn_user_id');

        $self = (array) ($players[$userId] ?? []);
        $opp = $this->opponentState($players, $userId);

        $candidateIds = (array) ($data['candidate_ids'] ?? []);

        return [
            'turn_user_id' => is_numeric($turnUserId) ? (int) $turnUserId : null,
            'can_choose_turn' => $this->canChooseTurn($match, $userId),
            'remaining_count' => count($candidateIds),
            'you' => [
                'asked' => (int) ($self['asked'] ?? 0),
                'wrong_guesses' => (int) ($self['wrong_guesses'] ?? 0),
            ],
            'opponent' => [
                'asked' => (int) ($opp['asked'] ?? 0),
                'wrong_guesses' => (int) ($opp['wrong_guesses'] ?? 0),
            ],
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
        $type = (string) ($action['type'] ?? '');

        if ($type === 'choose_turn') {
            return $this->handleChooseTurn($match, $userId, $action);
        }

        $state = $match->state ?? [];
        $turnUserId = Arr::get($state, 'turn_user_id');

        if (!is_numeric($turnUserId) || (int) $turnUserId !== $userId) {
            abort(409, 'Not your turn.');
        }

        return match ($type) {
            'ask' => $this->handleAsk($match, $userId, $action),
            'guess' => $this->handleGuess($match, $userId, $action),
            default => abort(422, 'Invalid action.'),
        };
    }

    /**
     * Handle chooser turn selection.
     *
     * @param PvpMatch     $match  Match instance.
     * @param int          $userId Acting user id.
     * @param array $action Action payload.
     *
     * @return PvpRoundResult
     */
    private function handleChooseTurn(PvpMatch $match, int $userId, array $action): PvpRoundResult
    {
        $state = $match->state ?? [];

        if (($state['turn_user_id'] ?? null) !== null) {
            abort(409, 'Turn already chosen.');
        }

        $chooser = (int) (($state['chooser_user_id'] ?? 0));
        if ($chooser <= 0 || $chooser !== $userId) {
            abort(403, 'Only the chooser can decide who starts.');
        }

        $first = (int) ($action['first_player_user_id'] ?? 0);
        [$u1, $u2] = $this->participants->getTwoUserIds((int) $match->id);

        if ($first !== $u1 && $first !== $u2) {
            abort(422, 'Invalid first_player_user_id.');
        }

        $patch = [
            'turn_user_id' => $first,
        ];

        $events = [[
            'type' => 'whois_turn_chosen',
            'user_id' => null,
            'payload' => [
                'first_user_id' => $first,
            ],
        ]];

        return PvpRoundResult::ongoing($patch, $events);
    }

    /**
     * Handle a yes/no question and eliminate candidates accordingly.
     *
     * @param PvpMatch     $match  Match instance.
     * @param int          $userId Acting user id.
     * @param array $action Action payload.
     *
     * @return PvpRoundResult
     */
    private function handleAsk(PvpMatch $match, int $userId, array $action): PvpRoundResult
    {
        $state = $match->state ?? [];
        $data = (array) Arr::get($state, 'round_data.whois', []);
        $secretId = (int) ($data['secret_player_id'] ?? 0);

        if ($secretId <= 0) {
            abort(500, 'Round not initialized.');
        }

        $q = $this->questions->validate((array) ($action['question'] ?? []));

        $secretWrapper = Player::resolvePlayerModel((string) $match->game, $secretId);
        if (!$secretWrapper) {
            abort(500, 'Secret player not found.');
        }

        $answer = $this->questions->evaluate($secretWrapper, $q['key'], $q['op'], $q['value']);

        $candidateIds = (array) ($data['candidate_ids'] ?? []);
        $filtered = [];

        foreach ($candidateIds as $cid) {
            $cid = (int) $cid;
            $wrapper = Player::resolvePlayerModel((string) $match->game, $cid);
            if (!$wrapper) {
                continue;
            }

            $same = $this->questions->evaluate($wrapper, $q['key'], $q['op'], $q['value']);
            if ($same === $answer) {
                $filtered[] = $cid;
            }
        }

        $data['candidate_ids'] = array_values(array_unique($filtered));

        $players = (array) ($data['players'] ?? []);
        if (!isset($players[$userId])) {
            abort(403, 'Not a participant.');
        }

        $players[$userId]['asked'] = ((int) ($players[$userId]['asked'] ?? 0)) + 1;
        $data['players'] = $players;

        [$u1, $u2] = $this->participants->getTwoUserIds((int) $match->id);
        $nextTurn = $this->participants->opponentOf([$u1, $u2], $userId);

        $patch = [
            'turn_user_id' => $nextTurn,
            'round_data' => [
                'whois' => $data,
            ],
        ];

        $events = [[
            'type' => 'whois_question',
            'user_id' => null,
            'payload' => [
                'actor_user_id' => $userId,
                'question' => [
                    'key' => $q['key'],
                    'op' => $q['op'],
                    'value' => $q['value'],
                ],
                'answer' => $answer,
                'remaining_count' => count($data['candidate_ids']),
            ],
        ]];

        return PvpRoundResult::ongoing($patch, $events);
    }

    /**
     * Handle a direct guess.
     *
     * @param PvpMatch     $match  Match instance.
     * @param int          $userId Acting user id.
     * @param array $action Action payload.
     *
     * @return PvpRoundResult
     */
    private function handleGuess(PvpMatch $match, int $userId, array $action): PvpRoundResult
    {
        $state = $match->state ?? [];
        $data = (array) Arr::get($state, 'round_data.whois', []);
        $secretId = (int) ($data['secret_player_id'] ?? 0);

        if ($secretId <= 0) {
            abort(500, 'Round not initialized.');
        }

        $guessId = (int) ($action['player_id'] ?? 0);
        if ($guessId <= 0) {
            abort(422, 'Invalid player_id.');
        }

        $correct = $guessId === $secretId;

        $events = [[
            'type' => 'whois_guess',
            'user_id' => null,
            'payload' => [
                'actor_user_id' => $userId,
                'player_id' => $guessId,
                'correct' => $correct,
            ],
        ]];

        if ($correct) {
            $data['winner_user_id'] = $userId;

            $patch = [
                'round_data' => [
                    'whois' => $data,
                ],
            ];

            $events[] = [
                'type' => 'whois_round_resolved',
                'user_id' => null,
                'payload' => [
                    'winner_user_id' => $userId,
                ],
            ];

            return PvpRoundResult::ended($userId, $patch, $events);
        }

        $players = (array) ($data['players'] ?? []);
        if (!isset($players[$userId])) {
            abort(403, 'Not a participant.');
        }

        $players[$userId]['wrong_guesses'] = ((int) ($players[$userId]['wrong_guesses'] ?? 0)) + 1;
        $data['players'] = $players;

        $banned = (array) ($data['banned_ids'] ?? []);
        $banned[] = $guessId;
        $data['banned_ids'] = array_values(array_unique(array_map('intval', $banned)));

        $candidateIds = (array) ($data['candidate_ids'] ?? []);
        $data['candidate_ids'] = array_values(array_filter(
            array_map('intval', $candidateIds),
            fn ($id) => $id !== $guessId
        ));

        [$u1, $u2] = $this->participants->getTwoUserIds((int) $match->id);
        $nextTurn = $this->participants->opponentOf([$u1, $u2], $userId);

        $patch = [
            'turn_user_id' => $nextTurn,
            'round_data' => [
                'whois' => $data,
            ],
        ];

        $events[] = [
            'type' => 'whois_eliminated',
            'user_id' => null,
            'payload' => [
                'player_id' => $guessId,
                'remaining_count' => count($data['candidate_ids']),
            ],
        ];

        return PvpRoundResult::ongoing($patch, $events);
    }

    /**
     * Determine whether the current user can choose the first player.
     *
     * @param PvpMatch $match  Match instance.
     * @param int      $userId Current user id.
     *
     * @return bool
     */
    private function canChooseTurn(PvpMatch $match, int $userId): bool
    {
        $state = $match->state ?? [];
        if (($state['turn_user_id'] ?? null) !== null) {
            return false;
        }

        $chooser = (int) (($state['chooser_user_id'] ?? 0));
        return $chooser > 0 && $chooser === $userId;
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
