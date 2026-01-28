import api from '@/api'

export type DiscordAuthMode = 'login' | 'link'

export interface DiscordAuthUrlResponse {
  url: string
  state: string
}

export interface DiscordExchangeLoginResponse {
  user: any
  token: string
  unlocked_achievements?: any[]
}

export interface DiscordExchangeLinkResponse {
  user: any
}

export type DiscordExchangeResponse = DiscordExchangeLoginResponse | DiscordExchangeLinkResponse

export async function fetchDiscordAuthUrl(mode: DiscordAuthMode): Promise<DiscordAuthUrlResponse> {
  const { data } = await api.get<DiscordAuthUrlResponse>('/auth/discord/url', {
    params: { mode },
  })
  return data
}

export async function exchangeDiscordCode(payload: { code: string; state: string }): Promise<DiscordExchangeResponse> {
  const { data } = await api.post<DiscordExchangeResponse>('/auth/discord/exchange', payload)
  return data
}

export async function unlinkDiscord(): Promise<{ user: any }> {
  const { data } = await api.post<{ user: any }>('/auth/discord/unlink')
  return data
}
