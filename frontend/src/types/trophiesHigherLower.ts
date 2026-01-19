export type HigherLowerSide = 'left' | 'right'

export interface HigherLowerPlayer {
  id: number
  name: string
  image_url: string | null
  trophies_count: number | null
}

export interface HigherLowerState {
  session_id: string
  score: number
  round: number
  left: HigherLowerPlayer
  right: HigherLowerPlayer
}

export interface HigherLowerGuessResponse {
  session_id: string
  clicked: HigherLowerSide
  correct: boolean
  reveal: {
    left: number
    right: number
  }
  score: number
  round: number
  game_over: boolean
  next: HigherLowerState | null
}
