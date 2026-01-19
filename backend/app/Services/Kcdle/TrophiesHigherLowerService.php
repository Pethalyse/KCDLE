<?php

namespace App\Services\Kcdle;

use App\Models\KcdlePlayer;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Str;
use Psr\SimpleCache\InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Service implementing the KCDLE trophies Higher/Lower solo game mode.
 *
 * State is stored in cache by session id. The service:
 * - Creates a new session with 2 random active KCDLE players.
 * - Evaluates guesses and returns reveal values.
 * - When correct, shifts the right player to the left and draws a new right player.
 * - When incorrect, ends the session and returns game_over=true.
 */
class TrophiesHigherLowerService
{
    /**
     * @var int
     */
    private const SESSION_TTL_SECONDS = 1800;

    /**
     * @var string
     */
    private const CACHE_KEY_PREFIX = 'kcdle:trophies_hl:';

    /**
     * @param CacheRepository $cache
     */
    public function __construct(private readonly CacheRepository $cache)
    {
    }

    /**
     * Start a new session.
     *
     * @return array<string, mixed>
     */
    public function start(): array
    {
        $sessionId = (string) Str::uuid();

        $left = $this->pickRandomPlayerId([]);
        $right = $this->pickRandomPlayerId([$left]);

        $state = [
            'session_id' => $sessionId,
            'score' => 0,
            'round' => 1,
            'left_id' => $left,
            'right_id' => $right,
            'used_ids' => [$left, $right],
        ];

        $this->putState($sessionId, $state);

        return $this->publicState($state, false);
    }

    /**
     * End a session explicitly (clears cache state).
     *
     * @param string $sessionId
     *
     * @return void
     */
    public function end(string $sessionId): void
    {
        $this->cache->forget($this->cacheKey($sessionId));
    }

    /**
     * Submit a guess for a session.
     *
     * @param string $sessionId
     * @param string $choice 'left'|'right'
     *
     * @return array<string, mixed>
     */
    public function guess(string $sessionId, string $choice): array
    {
        $state = $this->getState($sessionId);

        $left = KcdlePlayer::query()
            ->with('player')
            ->whereKey($state['left_id'])
            ->firstOrFail();

        $right = KcdlePlayer::query()
            ->with('player')
            ->whereKey($state['right_id'])
            ->firstOrFail();

        $leftTrophies = (int) $left->getAttribute('trophies_count');
        $rightTrophies = (int) $right->getAttribute('trophies_count');

        $isTie = $leftTrophies === $rightTrophies;

        $correctSide = $isTie
            ? 'tie'
            : ($leftTrophies > $rightTrophies ? 'left' : 'right');

        $isCorrect = $isTie || $choice === $correctSide;

        $reveal = [
            'left' => $leftTrophies,
            'right' => $rightTrophies,
        ];

        if (!$isCorrect) {
            $this->cache->forget($this->cacheKey($sessionId));

            return [
                'session_id' => $sessionId,
                'clicked' => $choice,
                'correct' => false,
                'reveal' => $reveal,
                'score' => (int) $state['score'],
                'round' => (int) $state['round'],
                'game_over' => true,
                'next' => null,
            ];
        }

        $nextState = $this->computeNextState($state);
        $this->putState($sessionId, $nextState);

        return [
            'session_id' => $sessionId,
            'clicked' => $choice,
            'correct' => true,
            'reveal' => $reveal,
            'score' => (int) $nextState['score'],
            'round' => (int) $state['round'],
            'game_over' => false,
            'next' => $this->publicState($nextState, true),
        ];
    }

    /**
     * Compute the next state after a correct guess.
     *
     * The right player becomes the new left player. A new right player is drawn.
     *
     * @param array<string, mixed> $state
     *
     * @return array<string, mixed>
     */
    private function computeNextState(array $state): array
    {
        $leftId = (int) $state['left_id'];
        $newLeftId = (int) $state['right_id'];

        $usedIds = array_values(array_unique(array_map('intval', (array) ($state['used_ids'] ?? []))));

        if (!in_array($newLeftId, $usedIds, true)) {
            $usedIds[] = $newLeftId;
        }

        $excludeForPick = $usedIds;
        if (!in_array($newLeftId, $excludeForPick, true)) {
            $excludeForPick[] = $newLeftId;
        }

        $newRightId = $this->pickRandomPlayerId($excludeForPick);

        if ($newRightId === null) {
            $usedIds = [$newLeftId];
            $newRightId = $this->pickRandomPlayerId([$newLeftId]);
        } else {
            $usedIds[] = $newRightId;
        }

        return [
            'session_id' => (string) $state['session_id'],
            'score' => (int) $state['score'] + 1,
            'round' => (int) $state['round'] + 1,
            'left_id' => $newLeftId,
            'right_id' => $newRightId,
            'used_ids' => array_values(array_unique($usedIds)),
        ];
    }

    /**
     * Get the cached state for a session id.
     *
     * @param string $sessionId
     *
     * @return array<string, mixed>
     * @throws InvalidArgumentException
     */
    private function getState(string $sessionId): array
    {
        $state = $this->cache->get($this->cacheKey($sessionId));

        if (!is_array($state) || empty($state['left_id']) || empty($state['right_id'])) {
            throw new NotFoundHttpException('Session not found.');
        }

        return $state;
    }

    /**
     * Save state to cache.
     *
     * @param string $sessionId
     * @param array<string, mixed> $state
     *
     * @return void
     */
    private function putState(string $sessionId, array $state): void
    {
        $this->cache->put($this->cacheKey($sessionId), $state, self::SESSION_TTL_SECONDS);
    }

    /**
     * Build the cache key for a given session id.
     *
     * @param string $sessionId
     *
     * @return string
     */
    private function cacheKey(string $sessionId): string
    {
        return self::CACHE_KEY_PREFIX . $sessionId;
    }

    /**
     * Pick a random KCDLE player id excluding a given list.
     *
     * @param array<int> $excludeIds
     *
     * @return int|null
     */
    private function pickRandomPlayerId(array $excludeIds): ?int
    {
        $excludeIds = array_values(array_unique(array_map('intval', $excludeIds)));

        $query = KcdlePlayer::query()
            ->where('active', true)
            ->whereNotNull('trophies_count');

        if (!empty($excludeIds)) {
            $query->whereNotIn('id', $excludeIds);
        }

        $id = $query
            ->inRandomOrder()
            ->limit(1)
            ->value('id');

        return $id !== null ? (int) $id : null;
    }

    /**
     * Convert internal state to a public payload for the frontend.
     *
     * @param array<string, mixed> $state
     * @param bool $leftKnown Whether the left player trophies must be included.
     *
     * @return array<string, mixed>
     */
    private function publicState(array $state, bool $leftKnown): array
    {
        $left = KcdlePlayer::query()
            ->with('player')
            ->whereKey((int) $state['left_id'])
            ->firstOrFail();

        $right = KcdlePlayer::query()
            ->with('player')
            ->whereKey((int) $state['right_id'])
            ->firstOrFail();

        return [
            'session_id' => (string) $state['session_id'],
            'score' => (int) $state['score'],
            'round' => (int) $state['round'],
            'left' => $this->mapPlayer($left, $leftKnown),
            'right' => $this->mapPlayer($right, false),
        ];
    }

    /**
     * Map a KCDLE player to the frontend payload.
     *
     * @param KcdlePlayer $kcdlePlayer
     * @param bool $includeTrophies
     *
     * @return array<string, mixed>
     */
    private function mapPlayer(KcdlePlayer $kcdlePlayer, bool $includeTrophies): array
    {
        $player = $kcdlePlayer->player;

        return [
            'id' => (int) $kcdlePlayer->getAttribute('id'),
            'name' => (string) ($player?->getAttribute('display_name') ?? ''),
            'image_url' => $player?->getAttribute('image_url'),
            'trophies_count' => $includeTrophies ? (int) $kcdlePlayer->getAttribute('trophies_count') : null,
        ];
    }
}
