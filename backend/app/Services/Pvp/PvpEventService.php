<?php

namespace App\Services\Pvp;

use App\Models\PvpMatch;
use App\Models\PvpMatchEvent;

/**
 * Emits persistent match events for network synchronization.
 *
 * Events are used by clients via polling to stay synchronized.
 */
class PvpEventService
{
    /**
     * Persist a batch of events for a match.
     *
     * @param int $matchId Match identifier.
     * @param array<int, array{type:string, payload:array|null, user_id:int|null}> $events Events to persist.
     *
     * @return void
     */
    public function emitMany(int $matchId, array $events): void
    {
        $match = PvpMatch::query()->find($matchId);
        $round = (int) ($match?->current_round ?? 0);
        $state = is_array($match?->state) ? (array) $match->state : [];
        $roundType = (string) ($state['round_type'] ?? ($match?->rounds[($round - 1)] ?? ''));

        foreach ($events as $event) {
            $payload = $event['payload'] ?? null;

            if (is_array($payload)) {
                if (!array_key_exists('round', $payload) && $round > 0) {
                    $payload['round'] = $round;
                }

                if (!array_key_exists('round_type', $payload) && $roundType !== '') {
                    $payload['round_type'] = $roundType;
                }
            }

            PvpMatchEvent::create([
                'match_id' => $matchId,
                'user_id' => $event['user_id'] ?? null,
                'type' => $event['type'],
                'payload' => $payload,
                'created_at' => now(),
            ]);
        }
    }

    /**
     * Persist a single event for a match.
     *
     * @param int              $matchId Match identifier.
     * @param string           $type    Event type.
     * @param array|null $payload Payload.
     * @param int|null         $userId  Optional emitting user id.
     *
     * @return void
     */
    public function emit(int $matchId, string $type, ?array $payload = null, ?int $userId = null): void
    {
        $this->emitMany($matchId, [[
            'type' => $type,
            'payload' => $payload,
            'user_id' => $userId,
        ]]);
    }
}
