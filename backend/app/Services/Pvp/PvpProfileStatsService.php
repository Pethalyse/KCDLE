<?php

namespace App\Services\Pvp;

use App\Models\PvpLobby;
use App\Models\PvpMatch;
use App\Models\User;
use Illuminate\Support\Collection;

class PvpProfileStatsService
{
    /**
     * @return array{
     *   queue: array{matches:int,wins:int,losses:int,winrate:float},
     *   private: array{matches:int,wins:int,losses:int,winrate:float},
     *   total: array{matches:int,wins:int,losses:int,winrate:float},
     *   private_opponents: array<int, array{user_id:int,name:string,matches:int,wins:int,losses:int,winrate:float}>
     * }
     */
    public function getForUser(User $user): array
    {
        $userId = (int) $user->getAttribute('id');

        $finishedMatches = PvpMatch::query()
            ->select(['pvp_matches.id', 'pvp_matches.state', 'pvp_matches.status'])
            ->join('pvp_match_players as me', 'pvp_matches.id', '=', 'me.match_id')
            ->where('me.user_id', $userId)
            ->where('pvp_matches.status', 'finished')
            ->get();

        $lobbyMatchIds = PvpLobby::query()
            ->whereNotNull('match_id')
            ->pluck('match_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $lobbyIdSet = array_fill_keys($lobbyMatchIds, true);

        $total = $this->buildTotals($finishedMatches, $userId);
        $private = $this->buildTotals($finishedMatches->filter(fn (PvpMatch $m) => isset($lobbyIdSet[(int) $m->getAttribute('id')])), $userId);
        $queue = $this->buildTotals($finishedMatches->filter(fn (PvpMatch $m) => ! isset($lobbyIdSet[(int) $m->getAttribute('id')])), $userId);

        $privateOpponents = $this->buildPrivateOpponents($userId, $lobbyIdSet);

        return [
            'queue' => $queue,
            'private' => $private,
            'total' => $total,
            'private_opponents' => $privateOpponents,
        ];
    }

    /**
     * @param Collection<int, PvpMatch> $matches
     * @return array{matches:int,wins:int,losses:int,winrate:float}
     */
    private function buildTotals(Collection $matches, int $userId): array
    {
        $matchesCount = $matches->count();

        $wins = 0;
        foreach ($matches as $match) {
            $state = $match->getAttribute('state') ?? [];
            $winnerId = isset($state['winner_user_id']) ? (int) $state['winner_user_id'] : 0;
            if ($winnerId === $userId) {
                $wins++;
            }
        }

        $losses = max(0, $matchesCount - $wins);
        $winrate = $matchesCount > 0 ? round(($wins / $matchesCount) * 100, 2) : 0.0;

        return [
            'matches' => $matchesCount,
            'wins' => $wins,
            'losses' => $losses,
            'winrate' => $winrate,
        ];
    }

    /**
     * @param array<int, bool> $lobbyIdSet
     * @return array<int, array{user_id:int,name:string,matches:int,wins:int,losses:int,winrate:float}>
     */
    private function buildPrivateOpponents(int $userId, array $lobbyIdSet): array
    {
        $rows = PvpMatch::query()
            ->select([
                'pvp_matches.id as match_id',
                'pvp_matches.state',
                'opp.user_id as opponent_user_id',
                'u.name as opponent_name',
            ])
            ->join('pvp_match_players as me', 'pvp_matches.id', '=', 'me.match_id')
            ->join('pvp_match_players as opp', function ($join) use ($userId) {
                $join->on('pvp_matches.id', '=', 'opp.match_id')
                    ->where('opp.user_id', '!=', $userId);
            })
            ->join('users as u', 'u.id', '=', 'opp.user_id')
            ->where('me.user_id', $userId)
            ->where('pvp_matches.status', 'finished')
            ->get();

        $agg = [];

        foreach ($rows as $row) {
            $matchId = (int) $row->match_id;
            if (! isset($lobbyIdSet[$matchId])) {
                continue;
            }

            $oppId = (int) $row->opponent_user_id;
            $oppName = (string) $row->opponent_name;

            if (! isset($agg[$oppId])) {
                $agg[$oppId] = [
                    'user_id' => $oppId,
                    'name' => $oppName,
                    'matches' => 0,
                    'wins' => 0,
                    'losses' => 0,
                    'winrate' => 0.0,
                ];
            }

            $agg[$oppId]['matches']++;

            $state = $row->state ?? [];
            $winnerId = isset($state['winner_user_id']) ? (int) $state['winner_user_id'] : 0;
            if ($winnerId === $userId) {
                $agg[$oppId]['wins']++;
            } else {
                $agg[$oppId]['losses']++;
            }
        }

        $list = array_values($agg);

        foreach ($list as &$item) {
            $m = (int) $item['matches'];
            $w = (int) $item['wins'];
            $item['winrate'] = $m > 0 ? round(($w / $m) * 100, 2) : 0.0;
        }
        unset($item);

        usort($list, function ($a, $b) {
            if ($a['matches'] === $b['matches']) {
                return $b['winrate'] <=> $a['winrate'];
            }
            return $b['matches'] <=> $a['matches'];
        });

        return $list;
    }
}
