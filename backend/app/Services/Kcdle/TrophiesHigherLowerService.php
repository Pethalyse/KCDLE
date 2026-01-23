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
 * Session state is stored in cache by session id:
 * - left_id: current left player id
 * - right_id: current right player id
 * - used_ids: already used player ids (to reduce repetitions)
 * - score: number of consecutive correct guesses
 * - round: current round number (1-based)
 *
 * Choice rules:
 * - If left trophies > right trophies: correct choice is 'left'
 * - If right trophies > left trophies: correct choice is 'right'
 * - If equal: correct choice is 'equal'
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

        $leftId = $this->pickRandomPlayerId([]);
        $rightId = $this->pickRandomPlayerId([$leftId]);

        $state = [
            'session_id' => $sessionId,
            'score' => 0,
            'round' => 1,
            'left_id' => $leftId,
            'right_id' => $rightId,
            'used_ids' => [$leftId, $rightId],
        ];

        $this->putState($sessionId, $state);

        return $this->publicState($state, false);
    }

    /**
     * End a session explicitly.
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
     * Response structure:
     * - session_id: string
     * - clicked: 'left'|'right'|'equal'
     * - correct: bool
     * - reveal: { left:int, right:int }
     * - score: int
     * - round: int
     * - game_over: bool
     * - next: null|{ session_id, score, round, left, right }
     *
     * @param string $sessionId
     * @param string $choice
     *
     * @return array<string, mixed>
     * @throws InvalidArgumentException
     */
    public function guess(string $sessionId, string $choice): array
    {
        $state = $this->getState($sessionId);

        $left = KcdlePlayer::query()
            ->with('player')
            ->whereKey((int) $state['left_id'])
            ->firstOrFail();

        $right = KcdlePlayer::query()
            ->with('player')
            ->whereKey((int) $state['right_id'])
            ->firstOrFail();

        $leftTrophies = (int) $left->getAttribute('trophies_count');
        $rightTrophies = (int) $right->getAttribute('trophies_count');

        $correctChoice = $this->computeCorrectChoice($leftTrophies, $rightTrophies);
        $isCorrect = $choice === $correctChoice;

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
     * Compute the correct choice based on trophy values.
     *
     * @param int $left
     * @param int $right
     *
     * @return string
     */
    private function computeCorrectChoice(int $left, int $right): string
    {
        if ($left === $right) {
            return 'equal';
        }

        return $left > $right ? 'left' : 'right';
    }

    /**
     * Compute the next state after a correct guess.
     *
     * The right player becomes the new left player and a new right player is drawn.
     *
     * @param array<string, mixed> $state
     *
     * @return array<string, mixed>
     */
    private function computeNextState(array $state): array
    {
        $newLeftId = (int) $state['right_id'];

        $usedIds = array_values(array_unique(array_map('intval', (array) ($state['used_ids'] ?? []))));
        if (!in_array($newLeftId, $usedIds, true)) {
            $usedIds[] = $newLeftId;
        }

        $newRightId = $this->pickRandomPlayerId(array_values(array_unique(array_merge($usedIds, [$newLeftId]))));

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
     * Retrieve cached state.
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
     * Build cache key.
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
     * Pick a random active KCDLE player id excluding a set of ids.
     *
     * @param array<int> $excludeIds
     *
     * @return int|null
     */
    private function pickRandomPlayerId(array $excludeIds): ?int
    {
        $excludeIds = array_values(array_unique(array_map('intval', $excludeIds)));

        $query = KcdlePlayer::query()
            ->where('active', true);

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
     * Convert internal state into a frontend payload.
     *
     * @param array<string, mixed> $state
     * @param bool $leftKnown
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
