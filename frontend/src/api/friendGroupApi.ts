// frontend/src/api/friendGroupApi.ts
import api from '@/api'
import type { GameCode } from '@/types/gameGuess'
import type {
  FriendGroupCreateOrJoinResponse,
  FriendGroupCreatePayload,
  FriendGroupDetailResponse,
  FriendGroupLeaderboardResponse,
  FriendGroupListResponse,
  FriendGroupMessageResponse,
} from '@/types/friendGroup'

export async function fetchFriendGroups() {
  const { data } = await api.get<FriendGroupListResponse>('/friend-groups')
  return data
}

export async function createFriendGroup(payload: FriendGroupCreatePayload) {
  const { data } = await api.post<FriendGroupCreateOrJoinResponse>('/friend-groups', payload)
  return data
}

export async function joinFriendGroup(joinCode: string) {
  const { data } = await api.post<FriendGroupCreateOrJoinResponse>('/friend-groups/join', {
    join_code: joinCode,
  })
  return data
}

export async function leaveFriendGroup(slug: string) {
  const { data } = await api.post<FriendGroupMessageResponse>(`/friend-groups/${slug}/leave`)
  return data
}

export async function deleteFriendGroup(slug: string) {
  const { data } = await api.delete<FriendGroupMessageResponse>(`/friend-groups/${slug}`)
  return data
}

export async function fetchFriendGroup(slug: string) {
  const { data } = await api.get<FriendGroupDetailResponse>(`/friend-groups/${slug}`)
  return data
}

export async function fetchFriendGroupLeaderboard(
  slug: string,
  game: GameCode,
  page = 1,
  perPage = 50,
) {
  const { data } = await api.get<FriendGroupLeaderboardResponse>(
    `/friend-groups/${slug}/leaderboards/${game}`,
    {
      params: {
        page,
        per_page: perPage,
      },
    },
  )

  return data
}
