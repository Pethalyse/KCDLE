<?php

namespace App\Services\Pvp;

use App\Models\PvpMatchEvent;

/**
 * Queries PvP match events for client synchronization.
 *
 * This service provides read operations for match events, enabling clients to poll
 * incrementally using an "after_id" cursor without reloading full match state.
 */
class PvpEventQueryService
{
    /**
     * Fetch events for a match after a given event id.
     *
     * The result is ordered by id ascending to ensure deterministic client replay.
     *
     * @param int      $matchId Match identifier.
     * @param int      $afterId Only events with id strictly greater than this value are returned.
     * @param int      $limit   Maximum number of events to return.
     *
     * @return array{events: array<int, array{id:int,type:string,user_id:int|null,created_at:string,payload:mixed}>, last_id:int}
     */
    public function fetchAfter(int $matchId, int $afterId, int $limit): array
    {
        $limit = max(1, min($limit, 200));

        $rows = PvpMatchEvent::query()
            ->where('match_id', $matchId)
            ->where('id', '>', $afterId)
            ->orderBy('id')
            ->limit($limit)
            ->get(['id', 'type', 'user_id', 'payload', 'created_at']);

        $events = [];
        $lastId = $afterId;

        foreach ($rows as $row) {
            $id = (int) $row->id;
            $lastId = max($lastId, $id);

            $events[] = [
                'id' => $id,
                'type' => (string) $row->type,
                'user_id' => $row->user_id !== null ? (int) $row->user_id : null,
                'created_at' => (string) $row->created_at,
                'payload' => $row->payload,
            ];
        }

        return [
            'events' => $events,
            'last_id' => $lastId,
        ];
    }
}
