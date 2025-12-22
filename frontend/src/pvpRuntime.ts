import type { Router } from 'vue-router'
import { watch } from 'vue'
import { usePvpStore } from '@/stores/pvp'
import { useAuthStore } from '@/stores/auth'
import { useFlashStore } from '@/stores/flash'
import { pvpJoinQueue, pvpLeaveQueue, pvpResume } from '@/api/pvpApi'
import type { PvpGame } from '@/types/pvp'
import api from '@/api'

let queueInterval: number | null = null
let queuedResumeInterval: number | null = null
let runtimeInstalled = false
let resumeInFlight = false

function stopQueueLoop() {
  if (queueInterval !== null) {
    window.clearInterval(queueInterval)
    queueInterval = null
  }
}

function stopQueuedResumeLoop() {
  if (queuedResumeInterval !== null) {
    window.clearInterval(queuedResumeInterval)
    queuedResumeInterval = null
  }
}

function authHeaderValue(): string | null {
  const raw = localStorage.getItem('kcdle_auth_token')
  if (!raw) return null
  return `Bearer ${raw}`
}

async function cancelQueueBestEffort(game: PvpGame) {
  try {
    await pvpLeaveQueue(game)
  } catch {
  }
}

function cancelQueueOnClose(game: PvpGame) {
  const token = authHeaderValue()
  if (!token) return

  const base = (import.meta.env.VITE_API_BASE_URL || '').replace(/\/$/, '')
  const url = `${base}/pvp/games/${game}/queue/leave`

  try {
    fetch(url, {
      method: 'POST',
      headers: { Authorization: token },
      keepalive: true,
    })
  } catch {
  }
}

async function resumeOnce(router: Router) {
  if (resumeInFlight) return
  resumeInFlight = true

  const pvp = usePvpStore()
  const auth = useAuthStore()

  try {
    if (!auth.isAuthenticated) return

    const res = await pvpResume()
    if (res.status === 'in_match' && typeof res.match_id === 'number') {
      pvp.setMatch(res.match_id)
      const name = router.currentRoute.value.name
      const mid = router.currentRoute.value.params.matchId
      const onMatchRoute = name === 'pvp_match' || name === 'pvp_match_play'
      if (!onMatchRoute || String(mid) !== String(res.match_id)) {
        router.push({ name: 'pvp_match', params: { matchId: res.match_id } })
      }
    } else {
      if (pvp.isInMatch) {
        pvp.clearMatch()
      }
    }
  } catch {
  } finally {
    resumeInFlight = false
  }
}

export function initPvpRuntime(router: Router) {
  if (runtimeInstalled) return
  runtimeInstalled = true

  const pvp = usePvpStore()
  const auth = useAuthStore()
  const flash = useFlashStore()

  router.beforeEach((to) => {
    if (pvp.isInMatch && pvp.matchId !== null) {
      const allowed = to.name === 'pvp_match' || to.name === 'pvp_match_play'
      const sameMatch = String(to.params.matchId ?? '') === String(pvp.matchId)

      if (!allowed || !sameMatch) {
        return { name: 'pvp_match', params: { matchId: pvp.matchId } }
      }
    }
    return true
  })

  const ensureQueueFlash = () => {
    if (!pvp.isQueued || !pvp.queue) return
    if (pvp.queueFlashId !== null) return

    const id = flash.push(
      'info',
      `En queue PvP sur ${pvp.queue.game.toUpperCase()} (BO${pvp.queue.bestOf}).`,
      'PvP',
      0,
    )
    pvp.setQueueFlashId(id)
  }

  const clearQueueFlash = () => {
    if (pvp.queueFlashId !== null) {
      flash.remove(pvp.queueFlashId)
      pvp.setQueueFlashId(null)
    }
  }

  const redirectToMatch = (matchId: number) => {
    pvp.setMatch(matchId)
    if (!pvp.redirecting) {
      pvp.setRedirecting(true)
      flash.success('Un match a été trouvé. Redirection...', 'PvP', 3000)
      window.setTimeout(() => {
        router.push({ name: 'pvp_match', params: { matchId } })
      }, 3000)
    }
  }

  const startQueueLoop = () => {
    stopQueueLoop()
    stopQueuedResumeLoop()

    if (!pvp.queue) return
    if (!auth.isAuthenticated) return

    ensureQueueFlash()

    queueInterval = window.setInterval(async () => {
      if (!pvp.queue) return
      if (!auth.isAuthenticated) return

      try {
        const res = await pvpJoinQueue(pvp.queue.game, pvp.queue.bestOf)

        if ((res.status === 'matched' || res.status === 'in_match') && typeof res.match_id === 'number') {
          clearQueueFlash()
          pvp.clearQueue()
          redirectToMatch(res.match_id)
        }
      } catch {
      }
    }, 1300)

    queuedResumeInterval = window.setInterval(async () => {
      if (!auth.isAuthenticated) return
      if (pvp.redirecting) return
      if (!pvp.isQueued) return
      await resumeOnce(router)
    }, 8000)
  }

  watch(
    () => auth.isAuthenticated,
    async () => {
      stopQueueLoop()
      stopQueuedResumeLoop()
      clearQueueFlash()

      if (!auth.isAuthenticated) {
        if (pvp.queue) pvp.clearQueue()
        if (pvp.isInMatch) pvp.clearMatch()
        return
      }

      await resumeOnce(router)
      if (pvp.isQueued) startQueueLoop()
    },
    { immediate: true },
  )

  watch(
    () => pvp.isQueued,
    (queued) => {
      if (queued && auth.isAuthenticated) {
        startQueueLoop()
      } else {
        stopQueueLoop()
        stopQueuedResumeLoop()
        clearQueueFlash()
      }
    },
    { immediate: true },
  )

  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') {
      resumeOnce(router)
    }
  })

  window.addEventListener('online', () => {
    resumeOnce(router)
  })

  window.addEventListener('beforeunload', (e) => {
    if (pvp.isQueued) {
      e.preventDefault()
      e.returnValue = ''
      return ''
    }
    return
  })

  window.addEventListener('pagehide', () => {
    if (pvp.queue) {
      const game = pvp.queue.game
      pvp.clearQueue()
      clearQueueFlash()
      stopQueueLoop()
      stopQueuedResumeLoop()
      cancelQueueOnClose(game)
    }
  })

  api.interceptors.response.use(
    (r) => r,
    (err) => {
      if (err?.response?.status === 401) {
        stopQueueLoop()
        stopQueuedResumeLoop()
        clearQueueFlash()
        if (pvp.queue) {
          const game = pvp.queue.game
          pvp.clearQueue()
          cancelQueueBestEffort(game)
        }
        if (pvp.isInMatch) {
          pvp.clearMatch()
        }
      }
      return Promise.reject(err)
    },
  )

  resumeOnce(router)
}
