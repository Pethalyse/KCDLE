import api from '@/api'
import type { AchievementListResponse } from '@/types/achievement'

export async function fetchAchievements() {
  const { data } = await api.get<AchievementListResponse>('/achievements')
  return data
}
