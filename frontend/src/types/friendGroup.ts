import type { LeaderboardMeta, LeaderboardRow } from '@/types/leaderboard'

export type FriendGroupRole = 'owner' | 'member'

export interface FriendGroupOwner {
  id: number
  name: string
}

export interface FriendGroupSummary {
  id: number
  name: string
  slug: string
  join_code: string
  role: FriendGroupRole | null
  owner: FriendGroupOwner | null
}

export interface FriendGroupListResponse {
  groups: FriendGroupSummary[]
}

export interface FriendGroupBase {
  id: number
  name: string
  slug: string
  join_code: string
}

export interface FriendGroupCreatePayload {
  name: string
}

export interface FriendGroupJoinPayload {
  join_code: string
}

export interface FriendGroupCreateOrJoinResponse {
  group: FriendGroupBase
}

export interface FriendGroupDetail {
  id: number
  name: string
  slug: string
  join_code: string
  owner_id: number
}

export interface FriendGroupMember {
  id: number
  name: string
  email: string | null
  role: FriendGroupRole
}

export interface FriendGroupDetailResponse {
  group: FriendGroupDetail
  members: FriendGroupMember[]
}

export interface FriendGroupMessageResponse {
  message: string
}

export interface FriendGroupLeaderboardGroup {
  id: number
  name: string
  slug: string
}

export interface FriendGroupLeaderboardResponse {
  group: FriendGroupLeaderboardGroup
  data: LeaderboardRow[]
  meta: LeaderboardMeta
}
