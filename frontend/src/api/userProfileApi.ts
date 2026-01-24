import api from '@/api'
import type { UserProfileResponse } from '@/types/userProfile'

export async function fetchUserProfile() {
  const { data } = await api.get<UserProfileResponse>('/user/profile')
  return data
}

export async function updateUserProfile(payload: {
  avatar?: File | null
  avatar_frame_color?: string | null
}): Promise<UserProfileResponse> {
  const form = new FormData()

  if (payload.avatar) {
    form.append('avatar', payload.avatar)
  }
  if (payload.avatar_frame_color !== undefined && payload.avatar_frame_color !== null) {
    form.append('avatar_frame_color', payload.avatar_frame_color)
  }

  const { data } = await api.post<UserProfileResponse>('/user/profile', form, {
    headers: {
      'Content-Type': 'multipart/form-data',
    },
  })

  return data
}
