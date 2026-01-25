import { defineStore } from 'pinia'
import api from '@/api'

export interface AuthUser {
  id: number
  name: string
  email: string
  email_verified?: boolean
  is_admin?: boolean
  avatar_url?: string | null
  avatar_frame_color?: string | null
}

interface AuthState {
  user: AuthUser | null
  token: string | null
  loading: boolean
}

const TOKEN_STORAGE_KEY = 'kcdle_auth_token'
const USER_STORAGE_KEY = 'kcdle_auth_user'

const dleCode = ['KCDLE', 'LECDLE', 'LFLDLE']

export const useAuthStore = defineStore('auth', {
  state: (): AuthState => ({
    token: localStorage.getItem(TOKEN_STORAGE_KEY) || null,
    user: JSON.parse(localStorage.getItem(USER_STORAGE_KEY) || 'null'),
    loading: false,
  }),

  getters: {
    isAuthenticated: (state) => Boolean(state.user) && Boolean(state.token),
  },

  actions: {
    setAuth(user: any, token: string) {
      this.token = token
      this.user = user

      localStorage.setItem(TOKEN_STORAGE_KEY, token)
      localStorage.setItem(USER_STORAGE_KEY, JSON.stringify(user))

      api.defaults.headers.common.Authorization = `Bearer ${token}`
    },

    updateUser(user: AuthUser) {
      this.user = user
      localStorage.setItem(USER_STORAGE_KEY, JSON.stringify(user))
    },

    setToken(token: string) {
      this.token = token
      localStorage.setItem(TOKEN_STORAGE_KEY, token)
      api.defaults.headers.common.Authorization = `Bearer ${token}`
    },

    async fetchMe() {
      if (!this.token) return null
      const { data } = await api.get('/auth/me')
      this.user = data?.user ?? null
      localStorage.setItem(USER_STORAGE_KEY, JSON.stringify(this.user))
      return data
    },

    async login(payload: { email: string; password: string }) {
      this.loading = true
      try {
        const { data } = await api.post('/auth/login', payload)
        this.setAuth(data.user, data.token)
        for (const string of dleCode) {
          localStorage.removeItem(string)
          localStorage.removeItem(`${string}_win`)
        }
        return data
      } finally {
        this.loading = false
      }
    },

    async register(payload: { name: string; email: string; password: string; password_confirmation: string }) {
      this.loading = true
      try {
        const { data } = await api.post('/auth/register', payload)
        return data
      } finally {
        this.loading = false
      }
    },

    async loginWithToken(token: string) {
      this.loading = true
      try {
        this.setToken(token)
        await this.fetchMe()
        return { user: this.user, token }
      } finally {
        this.loading = false
      }
    },

    async resendEmailVerification(payload: { email: string }) {
      const { data } = await api.post('/auth/email/verification-notification', payload)
      return data
    },

    async forgotPassword(payload: { email: string }) {
      const { data } = await api.post('/auth/password/forgot', payload)
      return data
    },

    async resetPassword(payload: { token: string; email: string; password: string; password_confirmation: string }) {
      const { data } = await api.post('/auth/password/reset', payload)
      return data
    },

    async refreshToken() {
      if (!this.token) return null
      const { data } = await api.post('/auth/refresh')
      if (data?.token) {
        this.setToken(data.token)
      }
      if (data?.user) {
        this.user = data.user
        localStorage.setItem(USER_STORAGE_KEY, JSON.stringify(this.user))
      }
      return data
    },

    logout() {
      this.token = null
      this.user = null

      localStorage.removeItem(TOKEN_STORAGE_KEY)
      localStorage.removeItem(USER_STORAGE_KEY)

      delete api.defaults.headers.common.Authorization
    },

    restore() {
      if (this.token) {
        api.defaults.headers.common.Authorization = `Bearer ${this.token}`
      }
    },
  },
})

export function initAuthStore() {
  const store = useAuthStore()
  store.restore()
}
