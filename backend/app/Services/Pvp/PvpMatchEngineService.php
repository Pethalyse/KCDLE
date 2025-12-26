<?php

namespace App\Services\Pvp;

use App\Models\PvpMatch;
use App\Models\PvpMatchPlayer;
use App\Services\Pvp\Rounds\PvpRoundHandlerFactory;
use App\Services\Pvp\Rounds\PvpRoundHandlerInterface;
use App\Services\Pvp\Rounds\PvpRoundResult;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Orchestrates PvP matches and rounds.
 *
 * The engine is format-aware (BO1/BO3/BO5) and round-type agnostic.
 * It delegates round-specific rules to handlers and performs generic transitions:
 * points update, next round selection, chooser selection, passive tick, and match completion.
 */
readonly class PvpMatchEngineService
{
    public function __construct(
        private PvpMatchService          $matches,
        private PvpMatchLifecycleService $lifecycle,
        private PvpEventService          $events,
        private PvpRoundHandlerFactory   $factory
    ) {
    }

    /**
     * Build the canonical match payload for a participant.
     *
     * This payload is used across the PvP network API to avoid shape drift between endpoints.
     * When the match is active, this method also ensures:
     * - the current round is initialized once,
     * - the chooser is computed,
     * - an optional passive tick is applied for time-driven rounds.
     *
     * @param PvpMatch $match Match instance.
     * @param int $userId Requesting user id.
     *
     * @return array Match payload enriched with the current round public state.
     * @throws Throwable
     */
    public function buildMatchPayload(PvpMatch $match, int $userId): array
    {
        return DB::transaction(function () use ($match, $userId) {
            $match = PvpMatch::whereKey($match->id)->lockForUpdate()->firstOrFail();

            $this->matches->assertParticipant($match->id, $userId);

            if ($match->status === 'active') {
                [$roundIndex, $roundType] = $this->resolveRound($match);
                $handler = $this->factory->forType($roundType);

                $state = $match->state ?? [];
                $state = $this->ensureRoundInitialized($match, $state, $roundIndex, $roundType, $handler);

                $tick = $this->tickIfSupported($match, $handler);
                if (! empty($tick['statePatch'])) {
                    $state = $this->mergeState($state, (array) $tick['statePatch']);
                    $match->state = $state;
                    $match->save();
                }

                if (! empty($tick['events'])) {
                    $this->events->emitMany($match->id, (array) $tick['events']);
                }
            }

            $fresh = PvpMatch::findOrFail($match->id);

            return $this->buildUnifiedPayload($fresh, $userId);
        });
    }

    /**
     * Apply a passive tick on the current round if the handler supports it.
     *
     * This is intended for server-side scheduling so time-driven rounds (ex: reveal_race)
     * remain fluid even when clients are idle.
     *
     * @param int $matchId Match identifier.
     *
     * @return array{ok:bool, ticked:bool, events_emitted:int, state_updated:bool, round_type:string|null}
     * @throws Throwable
     */
    public function passiveTick(int $matchId): array
    {
        return DB::transaction(function () use ($matchId) {
            $match = PvpMatch::whereKey($matchId)->lockForUpdate()->first();
            if (! $match) {
                return [
                    'ok' => false,
                    'ticked' => false,
                    'events_emitted' => 0,
                    'state_updated' => false,
                    'round_type' => null,
                ];
            }

            if ($match->status !== 'active') {
                return [
                    'ok' => true,
                    'ticked' => false,
                    'events_emitted' => 0,
                    'state_updated' => false,
                    'round_type' => null,
                ];
            }

            [$roundIndex, $roundType] = $this->resolveRound($match);

            $handler = $this->factory->forType($roundType);

            $state = $match->state ?? [];
            $state = $this->ensureRoundInitialized($match, $state, $roundIndex, $roundType, $handler);

            $tick = $this->tickIfSupported($match, $handler);
            if (empty($tick)) {
                return [
                    'ok' => true,
                    'ticked' => false,
                    'events_emitted' => 0,
                    'state_updated' => false,
                    'round_type' => $roundType,
                ];
            }

            $stateUpdated = false;
            $eventsEmitted = 0;

            if (! empty($tick['statePatch'])) {
                $state = $this->mergeState($state, (array) $tick['statePatch']);
                $match->state = $state;
                $match->save();
                $stateUpdated = true;
            }

            if (! empty($tick['events'])) {
                $events = (array) $tick['events'];
                $this->events->emitMany($match->id, $events);
                $eventsEmitted = count($events);
            }

            return [
                'ok' => true,
                'ticked' => true,
                'events_emitted' => $eventsEmitted,
                'state_updated' => $stateUpdated,
                'round_type' => $roundType,
            ];
        });
    }

    /**
     * Return the current round public state for a participant.
     *
     * @param PvpMatch $match Match instance.
     * @param int $userId Requesting user id.
     *
     * @return array Current round payload.
     * @throws Throwable
     */
    public function currentRoundState(PvpMatch $match, int $userId): array
    {
        return $this->buildMatchPayload($match, $userId);
    }

    /**
     * Handle an action for the current round, update match state, emit events, and advance if needed.
     *
     * @param PvpMatch $match Match instance.
     * @param int $userId Acting user id.
     * @param array $action Action payload.
     *
     * @return array Action result payload for the client.
     * @throws Throwable
     */
    public function handleRoundAction(PvpMatch $match, int $userId, array $action): array
    {
        return DB::transaction(function () use ($match, $userId, $action) {
            $match = PvpMatch::whereKey($match->id)->lockForUpdate()->firstOrFail();

            if ($match->status !== 'active') {
                abort(409, 'Match is not active.');
            }

            $this->matches->assertParticipant($match->id, $userId);

            [$roundIndex, $roundType] = $this->resolveRound($match);
            $handler = $this->factory->forType($roundType);

            $state = $match->state ?? [];
            $state = $this->ensureRoundInitialized($match, $state, $roundIndex, $roundType, $handler);

            $tick = $this->tickIfSupported($match, $handler);
            if (! empty($tick['statePatch'])) {
                $state = $this->mergeState($state, (array) $tick['statePatch']);
                $match->state = $state;
                $match->save();
            }

            if (! empty($tick['events'])) {
                $this->events->emitMany($match->id, (array) $tick['events']);
            }

            $result = $handler->handleAction($match, $userId, $action);
            $this->touchLastAction($match->id, $userId);

            $state = $this->mergeState($match->state ?? [], $result->statePatch);
            $match->state = $state;
            $match->save();

            $this->events->emitMany($match->id, $result->events);

            if (! $result->roundEnded) {
                $fresh = PvpMatch::findOrFail($match->id);

                return [
                    'ok' => true,
                    'round_ended' => false,
                    'state' => $this->buildUnifiedPayload($fresh, $userId),
                ];
            }

            if ($result->roundWinnerUserId === null) {
                abort(500, 'Round ended without winner.');
            }

            $this->applyRoundWin($match->id, $result->roundWinnerUserId);

            $state = $match->state ?? [];
            $state['last_round_winner_user_id'] = $result->roundWinnerUserId;

            $this->events->emit($match->id, 'round_finished', [
                'round' => $roundIndex,
                'round_type' => $roundType,
                'winner_user_id' => $result->roundWinnerUserId,
            ]);

            $matchFinished = $this->isMatchFinished($match->id, (int) $match->best_of);

            if ($matchFinished) {
                $match->state = $state;
                $match->save();
                $finish = $this->finishByPoints($match);
                return [
                    'ok' => true,
                    'round_ended' => true,
                    'match_finished' => true,
                    'finish' => $finish,
                ];
            }

            $match->current_round = $roundIndex + 1;

            $nextType = (string) ($match->rounds[$match->current_round - 1] ?? '');
            $state['round'] = $match->current_round;
            $state['round_type'] = $nextType;
            unset($state['chooser_user_id']);
            unset($state['round_initialized']);

            $match->state = $state;
            $match->save();

            $fresh = PvpMatch::findOrFail($match->id);

            return [
                'ok' => true,
                'round_ended' => true,
                'next_round' => $fresh->current_round,
                'state' => $this->buildUnifiedPayload($fresh, $userId),
            ];
        });
    }

    private function buildUnifiedPayload(PvpMatch $match, int $userId): array
    {
        $base = $this->matches->buildBaseMatchPayload($match, $userId);

        $state = $match->state ?? [];
        $roundType = (string) ($state['round_type'] ?? ($match->rounds[($match->current_round - 1)] ?? ''));

        $round = null;
        if ($match->status === 'active' && $roundType !== '') {
            $handler = $this->factory->forType($roundType);
            $round = $handler->publicState($match, $userId);
        }

        $base['round_type'] = $roundType;
        $base['chooser_user_id'] = $state['chooser_user_id'] ?? null;
        $base['round'] = $round;
        $base['match_id'] = $base['id'];

        return $base;
    }

    private function resolveRound(PvpMatch $match): array
    {
        $state = $match->state ?? [];
        $roundIndex = (int) $match->current_round;

        $roundType = (string) ($state['round_type'] ?? ($match->rounds[$roundIndex - 1] ?? ''));
        if ($roundType === '') {
            abort(500, 'Round type not found.');
        }

        return [$roundIndex, $roundType];
    }

    private function ensureRoundInitialized(PvpMatch $match, array $state, int $roundIndex, string $roundType, PvpRoundHandlerInterface $handler): array
    {
        if (isset($state['round_initialized']) && (int) $state['round_initialized'] === $roundIndex) {
            return $state;
        }

        $initPatch = $handler->initialize($match);
        $state = $this->mergeState($state, (array) $initPatch);
        $state['round_initialized'] = $roundIndex;
        $state = $this->ensureChooser($match, $state);

        $match->state = $state;
        $match->save();

        $this->events->emit($match->id, 'round_started', [
            'round' => $roundIndex,
            'round_type' => $roundType,
            'chooser_user_id' => $state['chooser_user_id'] ?? null,
        ]);

        return $state;
    }

    private function tickIfSupported(PvpMatch $match, PvpRoundHandlerInterface $handler): array
    {
        if (! method_exists($handler, 'tick')) {
            return [];
        }

        $res = $handler->tick($match);

        $statePatch = (array) ($res['statePatch'] ?? []);
        $events = (array) ($res['events'] ?? []);

        if (empty($statePatch) && empty($events)) {
            return [];
        }

        return [
            'statePatch' => $statePatch,
            'events' => $events,
        ];
    }

    private function mergeState(array $base, array $patch): array
    {
        foreach ($patch as $k => $v) {
            if (is_array($v) && isset($base[$k]) && is_array($base[$k])) {
                $base[$k] = $this->mergeState($base[$k], $v);
                continue;
            }
            $base[$k] = $v;
        }
        return $base;
    }

    private function ensureChooser(PvpMatch $match, array $state): array
    {
        if (isset($state['chooser_user_id'])) {
            return $state;
        }

        $players = PvpMatchPlayer::where('match_id', $match->id)->orderBy('seat')->get();
        if ($players->count() !== 2) {
            abort(500, 'Invalid match players.');
        }

        if ((int) $match->current_round === 1) {
            $chooser = $players->random();
            $state['chooser_user_id'] = (int) $chooser->user_id;
            return $state;
        }

        $lastWinner = $state['last_round_winner_user_id'] ?? null;
        if ($lastWinner !== null) {
            $state['chooser_user_id'] = (int) $lastWinner;
            return $state;
        }

        $chooser = $players->random();
        $state['chooser_user_id'] = (int) $chooser->user_id;
        return $state;
    }

    private function applyRoundWin(int $matchId, int $winnerUserId): void
    {
        PvpMatchPlayer::where('match_id', $matchId)
            ->where('user_id', $winnerUserId)
            ->increment('points');
    }

    private function isMatchFinished(int $matchId, int $bestOf): bool
    {
        $toWin = (int) floor($bestOf / 2) + 1;

        $maxPoints = (int) PvpMatchPlayer::where('match_id', $matchId)->max('points');

        return $maxPoints >= $toWin;
    }

    private function finishByPoints(PvpMatch $match): array
    {
        $players = PvpMatchPlayer::where('match_id', $match->id)->orderByDesc('points')->get();
        if ($players->count() !== 2) {
            abort(500, 'Invalid match players.');
        }

        $winner = $players->first();
        $loser = $players->last();

        return $this->lifecycle->finish($match, (int) $winner->user_id, [
            'reason' => 'points',
            'winner_points' => (int) $winner->points,
            'loser_points' => (int) $loser->points,
        ]);
    }

    private function touchLastAction(int $matchId, int $userId): void
    {
        $p = PvpMatchPlayer::where('match_id', $matchId)->where('user_id', $userId)->first();
        if (! $p) {
            return;
        }
        $p->last_action_at = now();
        $p->save();
    }
}
