import api from '@/api'
import type { HigherLowerGuessResponse, HigherLowerState, HigherLowerSide } from '@/types/trophiesHigherLower'

const base = '/games/kcdle/trophies-higher-lower'

export async function startTrophiesHigherLower() {
  const { data } = await api.post<HigherLowerState>(`${base}/start`)
  return data
}

export async function guessTrophiesHigherLower(sessionId: string, choice: HigherLowerSide) {
  const { data } = await api.post<HigherLowerGuessResponse>(`${base}/guess`, {
    session_id: sessionId,
    choice,
  })
  return data
}

export async function endTrophiesHigherLower(sessionId: string) {
  const { data } = await api.post<{ ended: boolean }>(`${base}/end`, {
    session_id: sessionId,
  })
  return data
}
