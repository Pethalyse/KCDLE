import type { Pinia } from 'pinia'
import type { Router } from 'vue-router'
import api from '@/api'
import { useAuthStore } from '@/stores/auth'
import { useFlashStore } from '@/stores/flash'

const VERSION_STORAGE_KEY = 'kcdle_app_version'

function storeVersion(version: string): void {
  if (version) {
    localStorage.setItem(VERSION_STORAGE_KEY, version)
  }
}

function getStoredVersion(): string | null {
  return localStorage.getItem(VERSION_STORAGE_KEY)
}

function extractVersion(headers: any): string | null {
  const v = headers?.['x-kcdle-version']
  return typeof v === 'string' && v.length > 0 ? v : null
}

async function handleVersionChange(pinia: Pinia, version: string): Promise<void> {
  const previous = getStoredVersion()
  if (!previous) {
    storeVersion(version)
    return
  }

  if (previous === version) {
    return
  }

  const auth = useAuthStore(pinia)
  try {
    if (auth.isAuthenticated) {
      await auth.refreshToken()
    }
  } catch {
  }

  storeVersion(version)
  window.location.reload()
}

export function initApi(pinia: Pinia, router: Router): void {
  const auth = useAuthStore(pinia)
  const flash = useFlashStore(pinia)

  api.interceptors.response.use(
    async (response) => {
      const version = extractVersion(response.headers)
      if (version) {
        await handleVersionChange(pinia, version)
      }
      return response
    },
    async (error) => {
      const version = extractVersion(error?.response?.headers)
      if (version) {
        await handleVersionChange(pinia, version)
      }

      const status = error?.response?.status
      if (status === 401 && auth.isAuthenticated) {
        auth.logout()
        flash.error('Votre session a expiré. Veuillez vous reconnecter.', 'Connexion')
        if (router.currentRoute.value.name !== 'login') {
          router.push({ name: 'login' }).then()
        }
      }

      return Promise.reject(error)
    },
  )
}
