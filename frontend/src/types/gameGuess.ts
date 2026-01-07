export type GameCode = 'kcdle' | 'lecdle' | 'lfldle'

export interface GuessStats {
  solvers_count: number
  total_guesses: number
  average_guesses: number
}

export interface GuessResponse {
  correct: boolean
  comparison: any
  stats: GuessStats
  unlocked_achievements: []
}

export interface ApiGuessEntry {
  player_id: number
  correct: boolean
  comparison: any
  stats: GuessStats
}

export interface StoredGuess {
  player_id: number
  correct: boolean
  comparison: any
  stats: GuessStats
  player: any
}

export interface TodayGuessResult {
  has_result: boolean
  won: boolean
  guesses_count: number
  guesses: ApiGuessEntry[]
}
