<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { usePvpStore } from '@/stores/pvp'
import { useFlashStore } from '@/stores/flash'
import { pvpGetMatch, pvpGetRound, pvpHeartbeat, pvpLeaveMatch, pvpPollEvents } from '@/api/pvpApi'

const route = useRoute()
const router = useRouter()
const pvp = usePvpStore()
const flash = useFlashStore()

const matchId = computed(() => {
  const raw = route.params.matchId
  const n = typeof raw === 'string' ? Number(raw) : Array.isArray(raw) ? Number(raw[0]) : Number(raw)
  return Number.isFinite(n) ? n : null
})

const loading = ref(true)
const error = ref<string | null>(null)

const match = ref<any | null>(null)
const round = ref<any | null>(null)

let eventsTimer: number | null = null
let heartbeatTimer: number | null = null

function stopTimers() {
  if (eventsTimer !== null) {
    window.clearInterval(eventsTimer)
    eventsTimer = null
  }
  if (heartbeatTimer !== null) {
    window.clearInterval(heartbeatTimer)
    heartbeatTimer = null
  }
}

async function loadAll() {
  if (!matchId.value) return
  loading.value = true
  error.value = null

  try {
    const m = await pvpGetMatch(matchId.value)
    match.value = m

    if (typeof m?.last_event_id === 'number') {
      pvp.setLastEventId(m.last_event_id)
    }

    const r = await pvpGetRound(matchId.value)
    round.value = r

    pvp.setMatch(matchId.value)
  } catch {
    error.value = 'Impossible de charger le match.'
  } finally {
    loading.value = false
  }
}

async function poll() {
  if (!matchId.value) return
  try {
    const res = await pvpPollEvents(matchId.value, pvp.lastEventId, 50)
    if (Array.isArray(res.events) && res.events.length > 0) {
      pvp.setLastEventId(res.last_id)

      const finished = res.events.some(ev => ev.type === 'match_finished')
      const forfeited = res.events.some(ev => ev.type === 'player_forfeited')
      const roundChanged = res.events.some(ev => ev.type === 'round_started' || ev.type === 'round_changed' || ev.type === 'round_advanced')

      const m = await pvpGetMatch(matchId.value)
      match.value = m

      const r = await pvpGetRound(matchId.value)
      round.value = r

      if (finished || forfeited || m?.status === 'finished') {
        pvp.clearMatch()
        stopTimers()
        flash.info('Match terminé.', 'PvP', 3000)
        router.push({ name: 'pvp' })
        return
      }

      if (roundChanged) {
        router.replace({ name: 'pvp_match', params: { matchId: matchId.value } })
        return
      }
    }
  } catch {
  }
}

async function beat() {
  if (!matchId.value) return
  try {
    await pvpHeartbeat(matchId.value)
  } catch {
  }
}

async function leave() {
  if (!matchId.value) return
  try {
    await pvpLeaveMatch(matchId.value)
  } catch {
  }
  pvp.clearMatch()
  stopTimers()
  flash.info('Tu as quitté le match.', 'PvP', 3000)
  router.push({ name: 'pvp' })
}

onMounted(async () => {
  if (!matchId.value) {
    flash.error('Match introuvable.', 'PvP')
    router.push({ name: 'pvp' })
    return
  }

  pvp.setMatch(matchId.value)
  await loadAll()

  eventsTimer = window.setInterval(poll, 1200)
  heartbeatTimer = window.setInterval(beat, 25000)
})

onBeforeUnmount(() => {
  stopTimers()
})
</script>

<template>
  <div class="match-page">
    <header class="match-header">
      <div class="header-left">
        <h1>Match PvP</h1>
        <p v-if="match">
          {{ (match.game || '').toUpperCase() }} • BO{{ match.best_of }} • Round {{ match.current_round }} / {{ match.best_of }}
        </p>
        <p v-else>Chargement…</p>
      </div>

      <div class="header-actions">
        <button type="button" class="btn danger" @click="leave">Quitter le match</button>
      </div>
    </header>

    <main class="match-main">
      <div v-if="loading" class="state">Chargement du match…</div>
      <div v-else-if="error" class="state state--error">{{ error }}</div>

      <template v-else>
        <section class="card">
          <div class="card-title">Joueurs</div>
          <div class="players" v-if="Array.isArray(match?.players)">
            <div class="player" v-for="p in match.players" :key="p.user_id">
              <div class="player-name">{{ p.name || 'Joueur' }}</div>
              <div class="player-meta">Points : {{ p.points }}</div>
            </div>
          </div>
          <div v-else class="state">—</div>
        </section>

        <section class="card">
          <div class="card-title">Round</div>
          <div class="round-meta">
            <div v-if="round?.round_type" class="chip">Type : {{ round.round_type }}</div>
            <div v-if="round?.phase" class="chip">Phase : {{ round.phase }}</div>
          </div>

          <pre class="json">{{ round }}</pre>
        </section>
      </template>
    </main>
  </div>
</template>

<style scoped>
.match-page {
  min-height: 100vh;
  padding: 20px 12px 28px;
  display: flex;
  flex-direction: column;
  align-items: center;
  color: #f3f3f3;
  background: radial-gradient(circle at top, #20263a 0, #05060a 75%);
}

.match-header {
  width: 100%;
  gap: 10px;
  max-width: 900px;
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  margin-bottom: 18px;
  flex-wrap: wrap;
}

.header-left h1 {
  font-size: 1.8rem;
  margin: 0 0 4px;
}

.header-left p {
  font-size: 0.95rem;
  margin: 0;
  opacity: 0.85;
}

.header-actions {
  display: flex;
  gap: 10px;
}

.btn {
  padding: 9px 14px;
  border-radius: 10px;
  border: 1px solid rgba(255, 255, 255, 0.14);
  background: rgba(255, 255, 255, 0.08);
  color: #f3f3f3;
  cursor: pointer;
  transition: transform 0.12s ease, background 0.12s ease;
  white-space: nowrap;
}

.btn:hover {
  transform: translateY(-1px);
  background: rgba(255, 255, 255, 0.14);
}

.btn.danger {
  background: rgba(255, 66, 66, 0.18);
  border-color: rgba(255, 66, 66, 0.35);
}

.btn.danger:hover {
  background: rgba(255, 66, 66, 0.28);
}

.match-main {
  width: 100%;
  max-width: 900px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.card {
  width: 100%;
  background: rgba(6, 8, 18, 0.92);
  border-radius: 14px;
  padding: 16px 12px 18px;
  box-shadow: 0 12px 28px rgba(0, 0, 0, 0.6);
  border: 1px solid rgba(255, 255, 255, 0.06);
  text-align: start;
}

.card-title {
  font-size: 0.9rem;
  opacity: 0.85;
  margin-bottom: 10px;
  text-transform: uppercase;
  letter-spacing: 0.12em;
}

.players {
  display: grid;
  grid-template-columns: 1fr;
  gap: 10px;
}

.player {
  padding: 10px 10px;
  border-radius: 12px;
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.08);
}

.player-name {
  font-weight: 600;
  margin-bottom: 4px;
}

.player-meta {
  font-size: 0.9rem;
  opacity: 0.85;
}

.round-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-bottom: 10px;
}

.chip {
  font-size: 0.82rem;
  padding: 5px 10px;
  border-radius: 999px;
  border: 1px solid rgba(255, 255, 255, 0.12);
  background: rgba(255, 255, 255, 0.06);
}

.json {
  margin: 0;
  padding: 12px;
  border-radius: 12px;
  background: rgba(0, 0, 0, 0.35);
  border: 1px solid rgba(255, 255, 255, 0.08);
  overflow: auto;
  font-size: 0.82rem;
  line-height: 1.35;
  white-space: pre-wrap;
  word-break: break-word;
}

.state {
  text-align: center;
  opacity: 0.85;
  padding: 18px 10px;
  background: rgba(6, 8, 18, 0.92);
  border-radius: 14px;
  border: 1px solid rgba(255, 255, 255, 0.06);
}

.state--error {
  color: #ffb4b4;
}

@media (min-width: 720px) {
  .players {
    grid-template-columns: 1fr 1fr;
  }
}
</style>
