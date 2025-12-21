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
