export type PvpGame = 'kcdle' | 'lecdle' | 'lfldle'
export type BestOf = 1 | 3 | 5

export type PvpQueueJoinStatus = 'queued' | 'matched' | 'in_match'

export interface PvpQueueJoinResponse {
  status: PvpQueueJoinStatus
  match_id?: number
}

export interface PvpResumeResponse {
  status: 'none' | 'in_match'
  match_id?: number
}

export interface PvpEvent {
  id: number
  type: string
  user_id: number | null
  created_at: string
  payload: any
}

export interface PvpEventsResponse {
  events: PvpEvent[]
  last_id: number
}

export interface PvpLobbyUser {
  id: number
  name: string
}

export interface PvpLobby {
  id: number
  code: string
  game: PvpGame
  best_of: BestOf
  status: 'open' | 'started' | 'closed'
  match_id: number | null
  host: PvpLobbyUser
  guest: PvpLobbyUser | null
  is_host: boolean
}

export type PvpLobbyMeResponse =
  | { status: 'none' }
  | { status: 'in_lobby'; lobby: PvpLobby }

export interface PvpLobbyPeekResponse {
  code: string
  game: PvpGame
  best_of: BestOf
  status: 'open'
  host: { name: string }
  created_at: string | null
}

export interface PvpLobbyEventsResponse {
  events: Array<{
    id: number
    lobby_id: number
    user_id: number | null
    type: string
    payload: any
    created_at: string | null
  }>
}
