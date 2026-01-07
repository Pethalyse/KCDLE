<?php

namespace App\Services\Pvp\Rounds;

/**
 * Represents the normalized outcome of a round action.
 *
 * This result is produced by a round handler and consumed by the engine.
 */
readonly class PvpRoundResult
{
    /**
     * @param bool        $roundEnded            Whether the round has ended due to this action.
     * @param int|null    $roundWinnerUserId     Winner user id when round ended.
     * @param array $statePatch           State fragment to merge into match state.
     * @param array<int, array{type:string, payload:array|null, user_id:int|null}> $events Events to emit.
     */
    public function __construct(
        public bool  $roundEnded,
        public ?int  $roundWinnerUserId,
        public array $statePatch,
        public array $events
    ) {
    }

    /**
     * Create a non-terminal result with optional state patch and events.
     *
     * @param array $statePatch State fragment to merge.
     * @param array<int, array{type:string, payload:array|null, user_id:int|null}> $events Events to emit.
     *
     * @return self
     */
    public static function ongoing(array $statePatch = [], array $events = []): self
    {
        return new self(false, null, $statePatch, $events);
    }

    /**
     * Create a terminal result indicating the round winner.
     *
     * @param int          $winnerUserId Winner user id.
     * @param array $statePatch   State fragment to merge.
     * @param array<int, array{type:string, payload:array|null, user_id:int|null}> $events Events to emit.
     *
     * @return self
     */
    public static function ended(int $winnerUserId, array $statePatch = [], array $events = []): self
    {
        return new self(true, $winnerUserId, $statePatch, $events);
    }
}
