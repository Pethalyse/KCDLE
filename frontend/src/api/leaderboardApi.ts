import api from '@/api'
import type { GameCode } from '@/types/gameGuess'
import type { LeaderboardResponse } from '@/types/leaderboard'

export async function fetchLeaderboard(game: GameCode, page = 1, perPage = 50) {
  const { data } = await api.get<LeaderboardResponse>(`/leaderboards/${game}`, {
    params: {
      page,
      per_page: perPage,
    },
  })

  return data
}
