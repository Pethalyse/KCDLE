export interface Achievement {
  id: number
  key: string
  name: string
  description: string
  game: string | null
  unlocked: boolean
  unlocked_at: string | null
  unlocked_percentage: number
}

export interface AchievementListResponse {
  data: Achievement[]
}
