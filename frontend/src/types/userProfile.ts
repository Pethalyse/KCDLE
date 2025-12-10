import type { GameCode } from '@/types/gameGuess'

export interface UserProfileUser {
  id: number
  name: string
  email: string
  created_at: string | null
}

export interface UserProfileGlobalStats {
  total_wins: number
  global_average_guesses: number | null
  first_win_at: string | null
  last_win_at: string | null
  distinct_days_played: number
}

export interface UserProfileGameStats {
  wins: number
  average_guesses: number | null
  current_streak: number
  max_streak: number
}

export interface UserProfileAchievements {
  total: number
  unlocked: number
}

export interface UserProfileFriendGroupOwner {
  id: number | null
  name: string | null
}

export interface UserProfileFriendGroup {
  id: number
  name: string
  slug: string
  join_code: string
  owner: UserProfileFriendGroupOwner
}

export interface UserProfileResponse {
  user: UserProfileUser
  global_stats: UserProfileGlobalStats
  games: Record<GameCode, UserProfileGameStats>
  achievements: UserProfileAchievements
  friend_groups: UserProfileFriendGroup[]
}
