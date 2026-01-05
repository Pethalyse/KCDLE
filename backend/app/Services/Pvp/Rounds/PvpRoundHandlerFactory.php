<?php

namespace App\Services\Pvp\Rounds;

/**
 * Resolves round handlers by round type identifier.
 *
 * This factory enables handler lookup without coupling the engine to specific implementations.
 */
readonly class PvpRoundHandlerFactory
{
    /**
     * @param array<int, PvpRoundHandlerInterface> $handlers Registered handlers.
     */
    public function __construct(private array $handlers)
    {
    }

    /**
     * Resolve a handler for a given round type.
     *
     * @param string $type Round type identifier.
     *
     * @return PvpRoundHandlerInterface
     */
    public function forType(string $type): PvpRoundHandlerInterface
    {
        foreach ($this->handlers as $handler) {
            if ($handler->type() === $type) {
                return $handler;
            }
        }

        abort(500, 'Unknown round type.');
    }
}
