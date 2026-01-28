import type { GameCode } from '@/types/gameGuess'

export interface UserProfileUser {
  id: number
  name: string
  email: string
  is_admin?: boolean
  avatar_url?: string | null
  avatar_frame_color?: string | null
  discord_id?: string | null
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
  users_count: number
  owner: UserProfileFriendGroupOwner
}

export interface UserProfilePvpTotals {
  matches: number
  wins: number
  losses: number
  winrate: number
}

export interface UserProfilePvpOpponent {
  user_id: number
  name: string
  matches: number
  wins: number
  losses: number
  winrate: number
}

export interface UserProfilePvpStats {
  queue: UserProfilePvpTotals
  private: UserProfilePvpTotals
  total: UserProfilePvpTotals
  private_opponents: UserProfilePvpOpponent[]
}

export interface UserProfileResponse {
  user: UserProfileUser
  global_stats: UserProfileGlobalStats
  games: Record<GameCode, UserProfileGameStats>
  achievements: UserProfileAchievements
  friend_groups: UserProfileFriendGroup[]
  pvp: UserProfilePvpStats
}
