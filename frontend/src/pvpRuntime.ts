import type { Router } from 'vue-router'
import { watch } from 'vue'
import { usePvpStore } from '@/stores/pvp'
import { useAuthStore } from '@/stores/auth'
import { useFlashStore } from '@/stores/flash'
import {
  pvpJoinQueue,
  pvpLeaveQueue,
  pvpResume,
  pvpLobbyMe,
  pvpPollLobbyEvents,
} from '@/api/pvpApi'
import type { PvpGame } from '@/types/pvp'
import api from '@/api'
import router from "@/router";

let queueInterval: number | null = null
let queuedResumeInterval: number | null = null
let lobbyResumeInterval: number | null = null
let lobbyEventsInterval: number | null = null
let runtimeInstalled = false
let resumeInFlight = false
let lobbyResumeInFlight = false

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

function stopLobbyLoops() {
  if (lobbyResumeInterval !== null) {
    window.clearInterval(lobbyResumeInterval)
    lobbyResumeInterval = null
  }
  if (lobbyEventsInterval !== null) {
    window.clearInterval(lobbyEventsInterval)
    lobbyEventsInterval = null
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
    }).then()
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
        await router.push({ name: 'pvp_match', params: { matchId: res.match_id } })
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

async function lobbyResumeOnce() {
  if (lobbyResumeInFlight) return
  lobbyResumeInFlight = true

  const pvp = usePvpStore()
  const auth = useAuthStore()

  try {
    if (!auth.isAuthenticated) return
    const res = await pvpLobbyMe()
    if (res.status === 'in_lobby') {
      pvp.setLobby(res.lobby)
    } else {
      if (pvp.isInLobby) pvp.clearLobby()
    }
  } catch {
  } finally {
    lobbyResumeInFlight = false
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
      const allowed = to.name === 'pvp_match' || to.name === 'pvp_match_play' || to.name === 'pvp_match_end'
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

  const ensureLobbyFlash = () => {
    if (!pvp.isInLobby || !pvp.lobby) return
    if (pvp.lobbyFlashId !== null) flash.remove(pvp.lobbyFlashId)

    const msg = pvp.lobby.guest ? `Lobby ${pvp.lobby.code} (2/2)` : `Lobby ${pvp.lobby.code} (1/2)`
    const id = flash.push('info', msg, 'PvP', 0)
    pvp.setLobbyFlashId(id)
  }

  const clearLobbyFlash = () => {
    if (pvp.lobbyFlashId !== null) {
      flash.remove(pvp.lobbyFlashId)
      pvp.setLobbyFlashId(null)
    }
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
        router.push({ name: 'pvp_match', params: { matchId } }).then()
      }, 3000)
    }
  }

  const startQueueLoop = () => {
    stopQueueLoop()
    stopQueuedResumeLoop()
    stopLobbyLoops()

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

  const startLobbyLoops = () => {
    stopLobbyLoops()
    stopQueueLoop()
    stopQueuedResumeLoop()
    clearQueueFlash()

    if (!auth.isAuthenticated) return

    lobbyResumeInterval = window.setInterval(async () => {
      if (!auth.isAuthenticated) return
      if (pvp.isInMatch) return
      await lobbyResumeOnce()
    }, 10000)

    lobbyEventsInterval = window.setInterval(async () => {
      if (!auth.isAuthenticated) return
      if (!pvp.lobby) return

      try {
        const res = await pvpPollLobbyEvents(pvp.lobby.id, pvp.lobbyLastEventId, 50)
        for (const e of res.events) {
          pvp.setLobbyLastEventId(Math.max(pvp.lobbyLastEventId, e.id))

          if (e.type === 'guest_joined' || e.type === 'guest_left' || e.type === 'lobby_created') {
            await lobbyResumeOnce()
            ensureLobbyFlash()
          }

          if (e.type === 'lobby_closed') {
            await lobbyResumeOnce()
            clearLobbyFlash()
            if (!pvp.isInLobby) flash.info('Le lobby a été fermé.', 'PvP')
          }

          if (e.type === 'match_started' && typeof e.payload?.match_id === 'number') {
            const matchId = Number(e.payload.match_id)
            clearLobbyFlash()
            pvp.clearLobby()
            pvp.setMatch(matchId)
            router.push({ name: 'pvp_match', params: { matchId } }).then()
          }
        }
      } catch {
      }
    }, 1400)
  }

  watch(
    () => auth.isAuthenticated,
    async () => {
      stopQueueLoop()
      stopQueuedResumeLoop()
      stopLobbyLoops()
      clearQueueFlash()
      clearLobbyFlash()

      if (!auth.isAuthenticated) {
        if (pvp.queue) pvp.clearQueue()
        if (pvp.isInMatch) pvp.clearMatch()
        if (pvp.isInLobby) pvp.clearLobby()
        return
      }

      await resumeOnce(router)
      await lobbyResumeOnce()
      if (pvp.isQueued) startQueueLoop()
      if (pvp.isInLobby) {
        ensureLobbyFlash()
        startLobbyLoops()
      } else {
        startLobbyLoops()
      }
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

  watch(
    () => pvp.isInLobby,
    (inLobby) => {
      if (!auth.isAuthenticated) return

      if (inLobby) {
        if (pvp.isQueued) {
          pvp.clearQueue()
          clearQueueFlash()
        }
        ensureLobbyFlash()
      } else {
        clearLobbyFlash()
      }

      startLobbyLoops()
    },
    { immediate: true },
  )

  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') {
      resumeOnce(router).then()
      lobbyResumeOnce().then()
    }
  })

  window.addEventListener('online', () => {
    resumeOnce(router).then()
    lobbyResumeOnce().then()
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
        stopLobbyLoops()
        clearQueueFlash()
        clearLobbyFlash()
        if (pvp.queue) pvp.clearQueue()
        if (pvp.isInMatch) pvp.clearMatch()
        if (pvp.isInLobby) pvp.clearLobby()
      }
      return Promise.reject(err)
    },
  )
}
