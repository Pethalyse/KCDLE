import api from '@/api'
import type { UserProfileResponse } from '@/types/userProfile'

export async function fetchUserProfile() {
  const { data } = await api.get<UserProfileResponse>('/user/profile')
  return data
}
