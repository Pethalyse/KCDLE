import api from '@/api'
import type { BestOf, PvpEventsResponse, PvpGame, PvpQueueJoinResponse, PvpResumeResponse } from '@/types/pvp'

export async function pvpJoinQueue(game: PvpGame, bestOf: BestOf): Promise<PvpQueueJoinResponse> {
  const { data } = await api.post(`/pvp/games/${game}/queue/join`, { best_of: bestOf })
  return data
}

export async function pvpLeaveQueue(game: PvpGame): Promise<{ status: string }> {
  const { data } = await api.post(`/pvp/games/${game}/queue/leave`)
  return data
}

export async function pvpResume(): Promise<PvpResumeResponse> {
  const { data } = await api.get(`/pvp/resume`)
  return data
}

export async function pvpGetMatch(matchId: number): Promise<any> {
  const { data } = await api.get(`/pvp/matches/${matchId}`)
  return data
}

export async function pvpGetRound(matchId: number): Promise<any> {
  const { data } = await api.get(`/pvp/matches/${matchId}/round`)
  return data
}

export async function pvpPostAction(matchId: number, action: any): Promise<any> {
  const { data } = await api.post(`/pvp/matches/${matchId}/round/action`, { action })
  return data
}

export async function pvpPollEvents(matchId: number, afterId: number, limit = 50): Promise<PvpEventsResponse> {
  const { data } = await api.get(`/pvp/matches/${matchId}/events`, {
    params: {
      after_id: afterId,
      limit,
    },
  })
  return data
}

export async function pvpHeartbeat(matchId: number): Promise<any> {
  const { data } = await api.post(`/pvp/matches/${matchId}/heartbeat`)
  return data
}

export async function pvpLeaveMatch(matchId: number): Promise<any> {
  const { data } = await api.post(`/pvp/matches/${matchId}/leave`)
  return data
}
