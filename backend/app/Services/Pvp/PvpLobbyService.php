<?php

namespace App\Services\Pvp;

use App\Models\PvpActiveMatchLock;
use App\Models\PvpLobby;
use App\Models\PvpLobbyEvent;
use App\Models\PvpMatch;
use App\Models\PvpMatchEvent;
use App\Models\PvpMatchPlayer;
use App\Models\PvpQueueEntry;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

readonly class PvpLobbyService
{
    public function __construct(private PvpMatchService $matches)
    {
    }

    /**
     * @param User $host
     * @param string $game
     * @param int $bestOf
     * @return PvpLobby
     * @throws Throwable
     */
    public function createLobby(User $host, string $game, int $bestOf): PvpLobby
    {
        $this->validateGame($game);
        $this->validateBestOf($bestOf);

        $activeMatch = $this->matches->findActiveMatchForUser((int) $host->id);
        if ($activeMatch !== null) {
            abort(409, 'You are already in an active match.');
        }

        return DB::transaction(function () use ($host, $game, $bestOf) {
            $this->assertUserNotInOtherOpenLobby((int) $host->id);

            PvpLobby::where('host_user_id', $host->id)
                ->where('status', 'open')
                ->lockForUpdate()
                ->update([
                    'status' => 'closed',
                    'closed_at' => now(),
                    'updated_at' => now(),
                ]);

            PvpQueueEntry::where('user_id', $host->id)->delete();

            $code = $this->generateUniqueCode();

            $lobby = PvpLobby::create([
                'host_user_id' => (int) $host->id,
                'guest_user_id' => null,
                'game' => $game,
                'best_of' => $bestOf,
                'status' => 'open',
                'code' => $code,
                'match_id' => null,
                'started_at' => null,
                'closed_at' => null,
            ]);

            $this->emitEvent((int) $lobby->id, (int) $host->id, 'lobby_created', [
                'game' => $lobby->game,
                'best_of' => (int) $lobby->best_of,
            ]);

            return $lobby;
        });
    }

    /**
     * @param User $user
     * @param string $code
     * @return PvpLobby
     * @throws Throwable
     */
    public function joinLobby(User $user, string $code): PvpLobby
    {
        return DB::transaction(function () use ($user, $code) {
            $lobby = PvpLobby::where('code', $code)->lockForUpdate()->firstOrFail();
            $this->assertUserNotInOtherOpenLobby((int) $user->id, (int) $lobby->id);

            if ($lobby->status !== 'open') {
                abort(409, 'Lobby is not open.');
            }

            if ((int) $lobby->host_user_id === (int) $user->id) {
                return $lobby;
            }

            $activeMatch = $this->matches->findActiveMatchForUser((int) $user->id);
            if ($activeMatch !== null) {
                abort(409, 'You are already in an active match.');
            }

            PvpQueueEntry::where('user_id', $user->id)->delete();

            if ($lobby->guest_user_id === null) {
                $lobby->guest_user_id = (int) $user->id;
                $lobby->save();
                $this->emitEvent((int) $lobby->id, (int) $user->id, 'guest_joined', [
                    'guest_user_id' => (int) $user->id,
                ]);
                return $lobby;
            }

            if ((int) $lobby->guest_user_id !== (int) $user->id) {
                abort(409, 'Lobby already has a guest.');
            }

            return $lobby;
        });
    }

    /**
     * @param User $user
     * @return PvpLobby|null
     */
    public function findMyOpenLobby(User $user): ?PvpLobby
    {
        return PvpLobby::query()
            ->where('status', 'open')
            ->where(function ($q) use ($user) {
                $q->where('host_user_id', (int) $user->id)
                    ->orWhere('guest_user_id', (int) $user->id);
            })
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @param User $user
     * @param PvpLobby $lobby
     * @return PvpLobby
     * @throws Throwable
     */
    public function leaveLobby(User $user, PvpLobby $lobby): PvpLobby
    {
        return DB::transaction(function () use ($user, $lobby) {
            $lobby = PvpLobby::whereKey($lobby->id)->lockForUpdate()->firstOrFail();

            if ($lobby->status !== 'open') {
                abort(409, 'Lobby is not open.');
            }

            if ((int) $lobby->host_user_id === (int) $user->id) {
                abort(409, 'Host cannot leave. Close the lobby instead.');
            }

            if ((int) ($lobby->guest_user_id ?? 0) !== (int) $user->id) {
                abort(403, 'Not a participant of this lobby.');
            }

            $lobby->guest_user_id = null;
            $lobby->save();
            $this->emitEvent((int) $lobby->id, (int) $user->id, 'guest_left', [
                'guest_user_id' => (int) $user->id,
            ]);

            return $lobby;
        });
    }

    /**
     * @param User $host
     * @param PvpLobby $lobby
     * @return PvpLobby
     * @throws Throwable
     */
    public function closeLobby(User $host, PvpLobby $lobby): PvpLobby
    {
        return DB::transaction(function () use ($host, $lobby) {
            $lobby = PvpLobby::whereKey($lobby->id)->lockForUpdate()->firstOrFail();

            if ((int) $lobby->host_user_id !== (int) $host->id) {
                abort(403, 'Not the lobby host.');
            }

            if ($lobby->status !== 'open') {
                abort(409, 'Lobby is not open.');
            }

            $lobby->status = 'closed';
            $lobby->closed_at = now();
            $lobby->save();
            $this->emitEvent((int) $lobby->id, (int) $host->id, 'lobby_closed', [
                'by' => 'host',
            ]);

            return $lobby;
        });
    }

    /**
     * @param User $host
     * @param PvpLobby $lobby
     * @return array{match_id:int, lobby_id:int}
     * @throws Throwable
     */
    public function startLobby(User $host, PvpLobby $lobby): array
    {
        if ((int) $lobby->host_user_id !== (int) $host->id) {
            abort(403, 'Not the lobby host.');
        }

        return DB::transaction(function () use ($lobby) {
            $lobby = PvpLobby::whereKey($lobby->id)->lockForUpdate()->firstOrFail();

            if ($lobby->status !== 'open') {
                abort(409, 'Lobby is not open.');
            }

            if ($lobby->guest_user_id === null) {
                abort(409, 'Lobby has no guest.');
            }

            $u1 = (int) $lobby->host_user_id;
            $u2 = (int) $lobby->guest_user_id;

            $this->assertUserNotInOtherOpenLobby($u1, (int) $lobby->id);
            $this->assertUserNotInOtherOpenLobby($u2, (int) $lobby->id);

            if ($this->matches->findActiveMatchForUser($u1) !== null) {
                abort(409, 'Host is already in an active match.');
            }

            if ($this->matches->findActiveMatchForUser($u2) !== null) {
                abort(409, 'Guest is already in an active match.');
            }

            $roundPool = (array) config('pvp.round_pool', []);
            if (count($roundPool) < (int) $lobby->best_of) {
                abort(500, 'PvP round pool is smaller than requested best-of format.');
            }

            if (! config('pvp.disable_shuffle', false)) {
                shuffle($roundPool);
            }
            $selectedRounds = array_values(array_slice($roundPool, 0, (int) $lobby->best_of));

            $match = PvpMatch::create([
                'game' => $lobby->game,
                'status' => 'active',
                'best_of' => (int) $lobby->best_of,
                'current_round' => 1,
                'rounds' => $selectedRounds,
                'state' => [
                    'round' => 1,
                    'round_type' => $selectedRounds[0],
                    'chooser_rule' => 'random_first_then_last_winner',
                    'chooser_user_id' => null,
                    'last_round_winner_user_id' => null,
                    'source' => 'lobby',
                    'lobby_id' => (int) $lobby->id,
                ],
                'started_at' => now(),
            ]);

            PvpMatchPlayer::create([
                'match_id' => $match->id,
                'user_id' => $u1,
                'seat' => 1,
                'points' => 0,
                'last_seen_at' => now(),
                'last_action_at' => now(),
            ]);

            PvpMatchPlayer::create([
                'match_id' => $match->id,
                'user_id' => $u2,
                'seat' => 2,
                'points' => 0,
                'last_seen_at' => now(),
                'last_action_at' => now(),
            ]);

            PvpActiveMatchLock::create([
                'user_id' => $u1,
                'match_id' => $match->id,
                'created_at' => now(),
            ]);

            PvpActiveMatchLock::create([
                'user_id' => $u2,
                'match_id' => $match->id,
                'created_at' => now(),
            ]);

            PvpMatchEvent::create([
                'match_id' => $match->id,
                'user_id' => null,
                'type' => 'match_created',
                'payload' => [
                    'game' => $lobby->game,
                    'best_of' => (int) $lobby->best_of,
                    'rounds' => $selectedRounds,
                    'source' => 'lobby',
                    'lobby_id' => (int) $lobby->id,
                ],
                'created_at' => now(),
            ]);

            $lobby->status = 'started';
            $lobby->match_id = (int) $match->id;
            $lobby->started_at = now();
            $lobby->closed_at = now();
            $lobby->save();

            $this->emitEvent((int) $lobby->id, (int) $lobby->host_user_id, 'lobby_closed', [
                'by' => 'start',
            ]);

            $this->emitEvent((int) $lobby->id, null, 'match_started', [
                'match_id' => (int) $match->id,
            ]);

            return [
                'match_id' => (int) $match->id,
                'lobby_id' => (int) $lobby->id,
            ];
        });
    }

    /**
     * @param string $code
     * @return PvpLobby
     */
    public function getByCode(string $code): PvpLobby
    {
        return PvpLobby::where('code', $code)->firstOrFail();
    }

    /**
     * @return string
     */
    private function generateUniqueCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (PvpLobby::where('code', $code)->exists());

        return $code;
    }

    /**
     * @param string $game
     * @return void
     */
    private function validateGame(string $game): void
    {
        if (! in_array($game, ['kcdle', 'lfldle', 'lecdle'], true)) {
            abort(404, 'Unknown game.');
        }
    }

    /**
     * @param int $bestOf
     * @return void
     */
    private function validateBestOf(int $bestOf): void
    {
        $allowed = (array) config('pvp.allowed_best_of', []);
        if (! in_array($bestOf, $allowed, true)) {
            abort(422, 'Invalid best-of format.');
        }
    }

    /**
     * @param PvpLobby $lobby
     * @param int $userId
     * @return void
     */
    public function assertParticipant(PvpLobby $lobby, int $userId): void
    {
        $isHost = (int) $lobby->host_user_id === $userId;
        $isGuest = (int) ($lobby->guest_user_id ?? 0) === $userId;

        if (! $isHost && ! $isGuest) {
            abort(403, 'Not a participant of this lobby.');
        }
    }

    /**
     * @param PvpLobby $lobby
     * @param int $userId
     * @return array<string, mixed>
     */
    public function buildLobbyPayload(PvpLobby $lobby, int $userId): array
    {
        $this->assertParticipant($lobby, $userId);

        $lobby->loadMissing(['host:id,name', 'guest:id,name']);

        return [
            'id' => (int) $lobby->id,
            'code' => $lobby->code,
            'game' => $lobby->game,
            'best_of' => (int) $lobby->best_of,
            'status' => $lobby->status,
            'match_id' => $lobby->match_id !== null ? (int) $lobby->match_id : null,
            'host' => [
                'id' => (int) $lobby->host_user_id,
                'name' => (string) ($lobby->host?->name ?? ''),
            ],
            'guest' => $lobby->guest_user_id === null ? null : [
                'id' => (int) $lobby->guest_user_id,
                'name' => (string) ($lobby->guest?->name ?? ''),
            ],
            'is_host' => (int) $lobby->host_user_id === $userId,
        ];
    }

    /**
     * @param int $lobbyId
     * @param int|null $userId
     * @param string $type
     * @param array<string, mixed>|null $payload
     * @return void
     */
    private function emitEvent(int $lobbyId, ?int $userId, string $type, ?array $payload = null): void
    {
        PvpLobbyEvent::create([
            'lobby_id' => $lobbyId,
            'user_id' => $userId,
            'type' => $type,
            'payload' => $payload,
            'created_at' => now(),
        ]);
    }

    /**
     * @param PvpLobby $lobby
     * @param int $afterId
     * @param int $limit
     * @return array<int, array<string, mixed>>
     */
    public function listEvents(PvpLobby $lobby, int $afterId, int $limit): array
    {
        $limit = max(1, min($limit, 200));

        $events = PvpLobbyEvent::query()
            ->where('lobby_id', (int) $lobby->id)
            ->where('id', '>', $afterId)
            ->orderBy('id')
            ->limit($limit)
            ->get();

        return $events->map(static function (PvpLobbyEvent $e): array {
            return [
                'id' => (int) $e->id,
                'lobby_id' => (int) $e->lobby_id,
                'user_id' => $e->user_id !== null ? (int) $e->user_id : null,
                'type' => $e->type,
                'payload' => $e->payload,
                'created_at' => $e->created_at?->toISOString(),
            ];
        })->all();
    }

    /**
     * @param int $userId
     * @param int|null $exceptLobbyId
     * @return PvpLobby|null
     */
    private function findAnyOpenLobbyForUser(int $userId, ?int $exceptLobbyId = null): ?PvpLobby
    {
        $q = PvpLobby::query()
            ->where('status', 'open')
            ->where(function ($qq) use ($userId) {
                $qq->where('host_user_id', $userId)->orWhere('guest_user_id', $userId);
            });

        if ($exceptLobbyId !== null) {
            $q->where('id', '!=', $exceptLobbyId);
        }

        return $q->orderByDesc('id')->first();
    }

    /**
     * @param int $userId
     * @param int|null $exceptLobbyId
     * @return void
     */
    private function assertUserNotInOtherOpenLobby(int $userId, ?int $exceptLobbyId = null): void
    {
        $other = $this->findAnyOpenLobbyForUser($userId, $exceptLobbyId);
        if ($other !== null) {
            abort(409, 'You are already in an open lobby.');
        }
    }

}
