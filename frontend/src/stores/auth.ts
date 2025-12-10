import { defineStore } from 'pinia'
import api from '@/api'
import {computed} from "vue";

export interface AuthUser {
  id: number
  name: string
  email: string
}

interface AuthState {
  user: AuthUser | null
  token: string | null
  loading: boolean
}

const TOKEN_STORAGE_KEY = 'kcdle_auth_token'
const USER_STORAGE_KEY = 'kcdle_auth_user'

const dleCode = [
  'KCDLE',
  'LECDLE',
  'LFLDLE',
]

export const useAuthStore = defineStore('auth', {
  state: (): AuthState => ({
    token: localStorage.getItem(TOKEN_STORAGE_KEY) || null,
    user: JSON.parse(localStorage.getItem(USER_STORAGE_KEY) || 'null'),
    loading: false,
  }),

  getters: {
    isAuthenticated: (state) => !!state.user && !!state.token,
  },

  actions: {
    setAuth(user: any, token: string) {
      this.token = token
      this.user = user

      localStorage.setItem(TOKEN_STORAGE_KEY, token)
      localStorage.setItem(USER_STORAGE_KEY, JSON.stringify(user))

      api.defaults.headers.common['Authorization'] = `Bearer ${token}`
    },

    async login(payload: { email: string; password: string }) {
      this.loading = true
      try {
        const { data } = await api.post('/auth/login', payload)
        this.setAuth(data.user, data.token)
        for (const string of dleCode) {
          localStorage.removeItem(string)
          localStorage.removeItem(string + '_win')
        }
      } finally {
        this.loading = false
      }
    },

    async register(payload: { name: string; email: string; password: string }) {
      this.loading = true
      try {
        const { data } = await api.post('/auth/register', payload)
        this.setAuth(data.user, data.token)
      } finally {
        this.loading = false
      }
    },

    logout() {
      this.token = null
      this.user = null
      localStorage.removeItem(TOKEN_STORAGE_KEY)
      localStorage.removeItem(USER_STORAGE_KEY)

      delete api.defaults.headers.common['Authorization']
    },

    restore() {
      if (this.token) {
        api.defaults.headers.common['Authorization'] = `Bearer ${this.token}`
      }
    },
  },
})

export function initAuthStore() {
  const store = useAuthStore()
  store.restore()
}


