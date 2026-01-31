import type { GameCode } from '@/types/gameGuess'

export interface LeaderboardUser {
  id: number
  name: string
  email: string | null
  is_admin: boolean
  is_streamer: boolean
  avatar_url: string | null
  avatar_frame_color: string | null
}

export interface LeaderboardRow {
  rank: number
  user: LeaderboardUser | null
  wins: number
  average_guesses: number | null
  base_score: number
  weight: number
  final_score: number
}

export interface LeaderboardMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
}

export interface LeaderboardResponse {
  game: GameCode
  data: LeaderboardRow[]
  meta: LeaderboardMeta
}
