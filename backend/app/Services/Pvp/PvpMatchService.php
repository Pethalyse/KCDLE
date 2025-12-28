<?php

namespace App\Services\Pvp;

use App\Models\PvpActiveMatchLock;
use App\Models\PvpMatch;
use App\Models\PvpMatchEvent;
use App\Models\PvpMatchPlayer;
use Illuminate\Support\Arr;

/**
 * Handles PvP match read operations for the network layer.
 *
 * This service centralizes participant authorization, match payload building, event polling and heartbeat updates.
 */
class PvpMatchService
{
    /**
     * Ensure a given user is a participant of a match.
     *
     * @param int $matchId Match identifier.
     * @param int $userId  User identifier.
     *
     * @return void
     */
    public function assertParticipant(int $matchId, int $userId): void
    {
        $isParticipant = PvpMatchPlayer::where('match_id', $matchId)
            ->where('user_id', $userId)
            ->exists();

        if (! $isParticipant) {
            abort(403, 'Not a participant of this match.');
        }
    }

    /**
     * Find an active PvP match for a given user, if any.
     *
     * This method relies on the active match lock table which enforces
     * a single active match per user at the database level.
     *
     * @param int $userId User identifier.
     *
     * @return PvpMatch|null Active match or null if none exists.
     */
    public function findActiveMatchForUser(int $userId): ?PvpMatch
    {
        $matchId = PvpActiveMatchLock::where('user_id', $userId)->value('match_id');

        if (! $matchId) {
            return null;
        }

        return PvpMatch::find((int) $matchId);
    }

    /**
     * Build a minimal resume payload for the authenticated user.
     *
     * This is meant for UX flows like "Resume my match" from the home screen,
     * without requiring the user to join a queue.
     *
     * @param int $userId Authenticated user id.
     *
     * @return array{status:string, match_id?:int}
     */
    public function buildResumePayload(int $userId): array
    {
        $match = $this->findActiveMatchForUser($userId);

        if ($match === null) {
            return ['status' => 'none'];
        }

        return ['status' => 'in_match', 'match_id' => $match->id];
    }

    /**
     * Build the payload required by the frontend to load the match screen.
     *
     * @param PvpMatch $match  Match instance.
     * @param int      $userId Authenticated user id.
     *
     * @return array{
     *   id:int,
     *   game:string,
     *   status:string,
     *   best_of:int,
     *   current_round:int,
     *   rounds:array<int, string>,
     *   started_at:string|null,
     *   state:array{round_type:mixed},
     *   players:array<int, array{seat:int, user_id:int, name:string|null, points:int, last_seen_at:mixed}>,
     *   last_event_id:int,
     *   finished_at?:string|null,
     *   result?:array{winner_user_id:int|null, ended_reason:string|null, forfeiting_user_id:int|null},
     *   round_history?:array<int, array{round:int, round_type:string, winner_user_id:int}>,
     *   round_recaps?:array<int, array{round:int, round_type:string, timeline:array<int, array{id:int, type:string, created_at:string|null, payload:array|null}>}>
     * }
     */
    public function buildBaseMatchPayload(PvpMatch $match, int $userId): array
    {
        $this->assertParticipant($match->id, $userId);

        $players = $match->players()->with('user:id,name')->orderBy('seat')->get();
        $lastEventId = $match->events()->max('id') ?? 0;

        $state = is_array($match->state) ? $match->state : [];

        $payload = [
            'id' => $match->id,
            'game' => $match->game,
            'status' => $match->status,
            'best_of' => $match->best_of,
            'current_round' => $match->current_round,
            'rounds' => $match->rounds,
            'started_at' => $match->started_at?->toISOString(),
            'state' => [
                'round_type' => Arr::get($state, 'round_type'),
            ],
            'players' => $players->map(fn (PvpMatchPlayer $p) => [
                'seat' => $p->seat,
                'user_id' => $p->user_id,
                'name' => $p->user?->name,
                'points' => $p->points,
                'last_seen_at' => $p->last_seen_at,
            ])->all(),
            'last_event_id' => $lastEventId,
        ];

        if ($match->status === 'finished') {
            $payload['finished_at'] = $match->finished_at?->toISOString();
            $payload['result'] = [
                'winner_user_id' => Arr::get($state, 'winner_user_id'),
                'ended_reason' => Arr::get($state, 'ended_reason'),
                'forfeiting_user_id' => Arr::get($state, 'forfeiting_user_id'),
            ];

            $roundEvents = PvpMatchEvent::query()
                ->where('match_id', $match->id)
                ->where('type', 'round_finished')
                ->orderBy('id')
                ->get(['payload']);

            $payload['round_history'] = $roundEvents
                ->map(function (PvpMatchEvent $e) {
                    $p = is_array($e->payload) ? $e->payload : [];
                    return [
                        'round' => (int) ($p['round'] ?? 0),
                        'round_type' => (string) ($p['round_type'] ?? ''),
                        'winner_user_id' => (int) ($p['winner_user_id'] ?? 0),
                    ];
                })
                ->filter(fn (array $x) => $x['round'] > 0 && $x['round_type'] !== '' && $x['winner_user_id'] > 0)
                ->values()
                ->all();

            $payload['round_recaps'] = $this->buildRoundRecaps($match);
        }

        return $payload;
    }

    /**
     * Build grouped per-round timelines from persistent match events.
     *
     * @param PvpMatch $match Finished match instance.
     *
     * @return array<int, array{round:int, round_type:string, timeline:array<int, array{id:int, type:string, created_at:string|null, payload:array|null}>}>
     */
    private function buildRoundRecaps(PvpMatch $match): array
    {
        $roundHistory = Arr::get($this->buildBaseRoundHistory($match), 'round_history', []);

        $historyByRound = [];
        foreach ($roundHistory as $h) {
            $r = (int) ($h['round'] ?? 0);
            $t = (string) ($h['round_type'] ?? '');
            if ($r > 0 && $t !== '') {
                $historyByRound[$r] = $t;
            }
        }

        $events = PvpMatchEvent::query()
            ->where('match_id', $match->id)
            ->orderBy('id')
            ->get(['id', 'type', 'payload', 'created_at']);

        $grouped = [];
        foreach ($events as $e) {
            $p = is_array($e->payload) ? $e->payload : null;
            $round = is_array($p) ? (int) ($p['round'] ?? 0) : 0;
            if ($round <= 0) {
                continue;
            }

            $roundType = is_array($p) ? (string) ($p['round_type'] ?? '') : '';
            if ($roundType === '' && isset($historyByRound[$round])) {
                $roundType = (string) $historyByRound[$round];
            }

            if (!isset($grouped[$round])) {
                $grouped[$round] = [
                    'round' => $round,
                    'round_type' => $roundType,
                    'timeline' => [],
                ];
            }

            $grouped[$round]['timeline'][] = [
                'id' => (int) $e->id,
                'type' => (string) $e->type,
                'created_at' => $e->created_at?->toISOString(),
                'payload' => $p,
            ];
        }

        ksort($grouped);

        return array_values($grouped);
    }

    /**
     * Build only the base round history array for internal reuse.
     *
     * @param PvpMatch $match Finished match instance.
     *
     * @return array{round_history:array<int, array{round:int, round_type:string, winner_user_id:int}>}
     */
    private function buildBaseRoundHistory(PvpMatch $match): array
    {
        $roundEvents = PvpMatchEvent::query()
            ->where('match_id', $match->id)
            ->where('type', 'round_finished')
            ->orderBy('id')
            ->get(['payload']);

        $roundHistory = $roundEvents
            ->map(function (PvpMatchEvent $e) {
                $p = is_array($e->payload) ? $e->payload : [];
                return [
                    'round' => (int) ($p['round'] ?? 0),
                    'round_type' => (string) ($p['round_type'] ?? ''),
                    'winner_user_id' => (int) ($p['winner_user_id'] ?? 0),
                ];
            })
            ->filter(fn (array $x) => $x['round'] > 0 && $x['round_type'] !== '' && $x['winner_user_id'] > 0)
            ->values()
            ->all();

        return ['round_history' => $roundHistory];
    }
}
