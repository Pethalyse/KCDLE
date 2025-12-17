<?php

namespace App\Services\Pvp\Rounds;

use App\Models\PvpMatch;

/**
 * Defines a PvP round handler contract.
 *
 * Each handler encapsulates round-specific rules and state transitions.
 * The engine remains generic and delegates to handlers for validation and outcome.
 */
interface PvpRoundHandlerInterface
{
    /**
     * Return the unique round type identifier handled by this implementation.
     *
     * @return string Round type identifier.
     */
    public function type(): string;

    /**
     * Initialize round state when the round starts.
     *
     * @param PvpMatch $match Match instance (locked by engine transaction).
     *
     * @return array State fragment to merge into match state.
     */
    public function initialize(PvpMatch $match): array;

    /**
     * Return the public round state for a participant.
     *
     * @param PvpMatch $match  Match instance.
     * @param int      $userId Requesting user id.
     *
     * @return array Public payload for the round.
     */
    public function publicState(PvpMatch $match, int $userId): array;

    /**
     * Handle a participant action for this round.
     *
     * The handler returns a normalized result which the engine uses to update points and advance rounds.
     *
     * @param PvpMatch      $match  Match instance (locked by engine transaction).
     * @param int           $userId Acting user id.
     * @param array $action Action payload.
     *
     * @return PvpRoundResult Normalized action result.
     */
    public function handleAction(PvpMatch $match, int $userId, array $action): PvpRoundResult;
}
