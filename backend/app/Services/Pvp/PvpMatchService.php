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
     *   state:array{round_type:mixed},
     *   players:array<int, array{seat:int, user_id:int, name:string|null, points:int, last_seen_at:mixed}>,
     *   last_event_id:int
     * }
     */
    public function buildBaseMatchPayload(PvpMatch $match, int $userId): array
    {
        $this->assertParticipant($match->id, $userId);

        $players = $match->players()->with('user:id,name')->orderBy('seat')->get();
        $lastEventId = $match->events()->max('id') ?? 0;

        $state = is_array($match->state) ? $match->state : [];

        return [
            'id' => $match->id,
            'game' => $match->game,
            'status' => $match->status,
            'best_of' => $match->best_of,
            'current_round' => $match->current_round,
            'rounds' => $match->rounds,
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
    }
}
