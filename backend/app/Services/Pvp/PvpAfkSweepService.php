<?php

namespace App\Services\Pvp;

use App\Models\PvpMatch;
use App\Models\PvpMatchPlayer;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Sweeps active PvP matches to resolve AFK participants.
 *
 * Two independent timeouts are enforced:
 * - Presence timeout based on last_seen_at (client heartbeat)
 * - Idle timeout based on last_action_at (no gameplay action)
 *
 * For turn-based rounds, the idle timeout is enforced only for the player whose turn it is.
 */
class PvpAfkSweepService
{
    /**
     * Execute an AFK sweep over active matches.
     *
     * @return array{checked:int, forfeited:int}
     * @throws Throwable
     */
    public function sweep(): array
    {
        $checked = 0;
        $forfeited = 0;

        $matchIds = PvpMatch::query()
            ->where('status', 'active')
            ->orderBy('id')
            ->pluck('id')
            ->all();

        foreach ($matchIds as $matchId) {
            $checked++;

            $didForfeit = $this->sweepMatch((int) $matchId);
            if ($didForfeit) {
                $forfeited++;
            }
        }

        return [
            'checked' => $checked,
            'forfeited' => $forfeited,
        ];
    }

    /**
     * Sweep a single match and forfeit at most one player if AFK conditions are met.
     *
     * @param int $matchId Match identifier.
     *
     * @return bool True when a forfeit occurred.
     * @throws Throwable
     */
    private function sweepMatch(int $matchId): bool
    {
        return DB::transaction(function () use ($matchId) {
            $match = PvpMatch::whereKey($matchId)->lockForUpdate()->first();

            if (! $match || $match->status !== 'active') {
                return false;
            }

            $players = PvpMatchPlayer::where('match_id', $match->id)->lockForUpdate()->get();

            if ($players->count() !== 2) {
                return false;
            }

            $presenceSeconds = (int) config('pvp.afk_seconds', 90);
            $idleSeconds = (int) config('pvp.idle_seconds', 300);

            $roundType = $this->getRoundType($match);
            $turnUserId = $this->getTurnUserId($match, $roundType);
            $turnBased = $this->isTurnBasedRound($roundType);

            $presenceCutoff = now()->subSeconds($presenceSeconds);
            $idleCutoff = now()->subSeconds($idleSeconds);

            foreach ($players as $player) {
                $userId = (int) $player->user_id;

                $last_seen_at = $this->toCarbon($player->last_seen_at);
                if ($last_seen_at === null || $last_seen_at->lt($presenceCutoff)) {
                    $this->forfeitAfk($match, $userId);
                    return true;
                }

                $shouldCheckIdle = !($turnBased && $turnUserId !== null) || $turnUserId === $userId;

                if ($shouldCheckIdle) {
                    $last_action_at = $this->toCarbon($player->last_action_at);
                    if ($last_action_at === null || $last_action_at->lt($idleCutoff)) {
                        $this->forfeitAfk($match, $userId);
                        return true;
                    }
                }
            }

            return false;
        });
    }

    /**
     * Forfeit a match with reason "afk" for the given user.
     *
     * @param PvpMatch $match Locked match instance.
     * @param int $userId AFK user id.
     *
     * @return void
     * @throws Throwable
     */
    private function forfeitAfk(PvpMatch $match, int $userId): void
    {
        app(PvpMatchLifecycleService::class)->forfeit($match, $userId, 'afk');
    }

    /**
     * Determine the current round type from match state.
     *
     * @param PvpMatch $match Match instance.
     *
     * @return string Round type identifier.
     */
    private function getRoundType(PvpMatch $match): string
    {
        $state = $match->state ?? [];
        $type = (string) ($state['round_type'] ?? '');

        if ($type !== '') {
            return $type;
        }

        $index = (int) $match->current_round;
        return (string) ($match->rounds[$index - 1] ?? '');
    }

    /**
     * Determine whether a round type is considered turn-based for idle enforcement.
     *
     * @param string $roundType Round type identifier.
     *
     * @return bool
     */
    private function isTurnBasedRound(string $roundType): bool
    {
        return in_array($roundType, ['whois', 'draft'], true);
    }

    /**
     * Attempt to resolve the current turn user id for turn-based rounds from match state.
     *
     * This method supports multiple possible state locations to stay flexible across handler versions:
     * - state.turn_user_id
     * - state.round_data.{type}.turn_user_id
     * - state.round_data.{type}.turn.actor_user_id
     *
     * @param PvpMatch $match     Match instance.
     * @param string   $roundType Round type identifier.
     *
     * @return int|null Turn user id when available.
     */
    private function getTurnUserId(PvpMatch $match, string $roundType): ?int
    {
        $state = $match->state ?? [];

        $direct = Arr::get($state, 'turn_user_id');
        if (is_numeric($direct)) {
            return (int) $direct;
        }

        $path = "round_data.$roundType.turn_user_id";
        $nested = Arr::get($state, $path);
        if (is_numeric($nested)) {
            return (int) $nested;
        }

        $path2 = "round_data.$roundType.turn.actor_user_id";
        $nested2 = Arr::get($state, $path2);
        if (is_numeric($nested2)) {
            return (int) $nested2;
        }

        return null;
    }

    /**
     * Convert a datetime value (Carbon/DateTime/string) to Carbon.
     *
     * @param mixed $value
     * @return Carbon|null
     */
    private function toCarbon(mixed $value): ?Carbon
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value;
        }

        if ($value instanceof CarbonInterface) {
            return Carbon::instance($value);
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value);
        }

        if (is_string($value) && $value !== '') {
            try {
                return Carbon::parse($value);
            } catch (Throwable) {
                return null;
            }
        }

        return null;
    }
}
