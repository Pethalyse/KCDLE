import { defineStore } from 'pinia'
import type { BestOf, PvpGame } from '@/types/pvp'

type QueueState = {
  game: PvpGame
  bestOf: BestOf
  startedAt: number
}

type MatchState = {
  matchId: number
  startedAt: number
}

const QUEUE_STORAGE_KEY = 'kcdle_pvp_queue'
const MATCH_STORAGE_KEY = 'kcdle_pvp_match'

function readJson<T>(key: string): T | null {
  try {
    const raw = localStorage.getItem(key)
    if (!raw) return null
    return JSON.parse(raw) as T
  } catch {
    return null
  }
}

export const usePvpStore = defineStore('pvp', {
  state: () => ({
    queue: readJson<QueueState>(QUEUE_STORAGE_KEY) as QueueState | null,
    match: readJson<MatchState>(MATCH_STORAGE_KEY) as MatchState | null,
    queueFlashId: null as number | null,
    lastEventId: 0,
    redirecting: false,
  }),

  getters: {
    isQueued: state => state.queue !== null,
    isInMatch: state => state.match !== null,
    queuedGame: state => state.queue?.game ?? null,
    queuedBestOf: state => state.queue?.bestOf ?? null,
    matchId: state => state.match?.matchId ?? null,
  },

  actions: {
    setQueued(game: PvpGame, bestOf: BestOf) {
      const q: QueueState = { game, bestOf, startedAt: Date.now() }
      this.queue = q
      localStorage.setItem(QUEUE_STORAGE_KEY, JSON.stringify(q))
    },

    clearQueue() {
      this.queue = null
      localStorage.removeItem(QUEUE_STORAGE_KEY)
    },

    setMatch(matchId: number) {
      const m: MatchState = { matchId, startedAt: Date.now() }
      this.match = m
      localStorage.setItem(MATCH_STORAGE_KEY, JSON.stringify(m))
    },

    clearMatch() {
      this.match = null
      this.lastEventId = 0
      this.redirecting = false
      localStorage.removeItem(MATCH_STORAGE_KEY)
    },

    setQueueFlashId(id: number | null) {
      this.queueFlashId = id
    },

    setLastEventId(id: number) {
      this.lastEventId = id
    },

    setRedirecting(v: boolean) {
      this.redirecting = v
    },
  },
})
