<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { usePvpStore } from '@/stores/pvp'
import { useFlashStore } from '@/stores/flash'
import { pvpGetMatch, pvpHeartbeat, pvpLeaveMatch, pvpPollEvents, pvpPostAction } from '@/api/pvpApi'
import PvpClassicRound from '@/components/pvp/rounds/PvpClassicRound.vue'
import PvpWhoisRound from '@/components/pvp/rounds/PvpWhoisRound.vue'
import PvpScoreboard from '@/components/pvp/PvpScoreboard.vue'
import PvpRoundResultOverlay from '@/components/pvp/PvpRoundResultOverlay.vue'

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
const round = computed(() => match.value?.round ?? null)
const roundType = computed(() => {
  const rt = match.value?.round_type
  return typeof rt === 'string' && rt.length > 0 ? rt : 'classic'
})

const navigating = ref(false)

const overlayDurationMs = ref(4500)
const overlayAnimationMs = ref(1000)
const roundWinBanner = ref<{ winnerUserId: number; winnerName: string } | null>(null)

const scoreLeftName = ref('J1')
const scoreRightName = ref('J2')
const scoreFromLeft = ref(0)
const scoreFromRight = ref(0)
const scoreToLeft = ref(0)
const scoreToRight = ref(0)

const whoisEvents = ref<any[]>([])

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

function winnerNameFromMatch(winnerId: number): string {
  const list = Array.isArray(match.value?.players) ? match.value.players : []
  const p = list.find((x: any) => Number(x?.user_id) === Number(winnerId))
  return p?.name ?? 'Joueur'
}

function extractScore(m: any) {
  const list = Array.isArray(m?.players) ? m.players : []
  const a = list[0] ?? {}
  const b = list[1] ?? {}

  return {
    leftName: a?.name ?? 'J1',
    rightName: b?.name ?? 'J2',
    leftPts: Number(a?.points ?? 0),
    rightPts: Number(b?.points ?? 0),
  }
}

function goTransitionAfterDelay(ms: number) {
  if (!matchId.value) return
  window.setTimeout(() => {
    router.replace({ name: 'pvp_match', params: { matchId: matchId.value } })
  }, ms)
}

function pushWhoisEvents(events: any[]) {
  const allowed = new Set(['whois_question', 'whois_guess', 'whois_eliminated', 'whois_turn_chosen'])
  const add = (events ?? []).filter(e => allowed.has(String(e?.type ?? '')))
  if (add.length === 0) return
  whoisEvents.value = [...whoisEvents.value, ...add].slice(-120)
}

async function hydrateWhoisEvents() {
  if (!matchId.value) return
  const targetLastId = typeof match.value?.last_event_id === 'number' ? match.value.last_event_id : null
  if (!targetLastId || targetLastId <= 0) return

  whoisEvents.value = []
  let afterId = 0
  let safety = 0

  while (afterId < targetLastId && safety < 30) {
    safety += 1
    const res = await pvpPollEvents(matchId.value, afterId, 200)
    if (!Array.isArray(res.events) || res.events.length === 0) break
    pushWhoisEvents(res.events)
    const next = Number(res.last_id ?? 0)
    if (!Number.isFinite(next) || next <= afterId) break
    afterId = next
    if (afterId >= targetLastId) break
  }
}

async function loadAll() {
  if (!matchId.value) return
  loading.value = true
  error.value = null

  try {
    const m = await pvpGetMatch(matchId.value)
    match.value = m
    pvp.setMatch(matchId.value)

    if (typeof m?.last_event_id === 'number') {
      pvp.setLastEventId(m.last_event_id)
    } else {
      pvp.setLastEventId(0)
    }

    await hydrateWhoisEvents()
  } catch {
    error.value = 'Impossible de charger le match.'
  } finally {
    loading.value = false
  }
}

async function poll() {
  if (!matchId.value || navigating.value) return

  try {
    const res = await pvpPollEvents(matchId.value, pvp.lastEventId, 50)
    if (!Array.isArray(res.events) || res.events.length === 0) return

    pvp.setLastEventId(res.last_id)
    pushWhoisEvents(res.events)

    const roundFinishedEv = res.events.find(ev => ev.type === 'round_finished')
    if (roundFinishedEv) {
      navigating.value = true
      stopTimers()

      const before = extractScore(match.value)

      const m = await pvpGetMatch(matchId.value)
      match.value = m

      const after = extractScore(m)

      scoreLeftName.value = after.leftName
      scoreRightName.value = after.rightName
      scoreFromLeft.value = before.leftPts
      scoreFromRight.value = before.rightPts
      scoreToLeft.value = after.leftPts
      scoreToRight.value = after.rightPts

      const wid = Number(roundFinishedEv?.payload?.winner_user_id ?? 0)
      roundWinBanner.value = {
        winnerUserId: wid,
        winnerName: wid > 0 ? winnerNameFromMatch(wid) : 'Résultat du round',
      }

      goTransitionAfterDelay(overlayDurationMs.value)
      return
    }

    const m = await pvpGetMatch(matchId.value)
    const prevRound = match.value?.current_round
    match.value = m

    const nextRound = m?.current_round
    if (prevRound !== nextRound) {
      whoisEvents.value = []
      await hydrateWhoisEvents()
    }

    const finished = res.events.some(ev => ev.type === 'match_finished') || m?.status === 'finished'
    const forfeited = res.events.some(ev => ev.type === 'player_forfeited')
    if (finished || forfeited) {
      pvp.clearMatch()
      stopTimers()
      flash.info('Match terminé.', 'PvP', 3000)
      await router.push({ name: 'pvp' })
    }
  } catch {
  }
}

async function beat() {
  if (!matchId.value || navigating.value) return
  try {
    await pvpHeartbeat(matchId.value)
  } catch {
  }
}

async function leave() {
  if (!matchId.value) return

  if (confirm('Voulez-vous abandonner le match ?')) {
    try {
      await pvpLeaveMatch(matchId.value)
    } catch {
    }
    pvp.clearMatch()
    stopTimers()
    flash.info('Tu as quitté le match.', 'PvP', 3000)
    await router.push({ name: 'pvp' })
  }
}

async function onClassicGuess(playerId: number): Promise<boolean> {
  if (!matchId.value || navigating.value) return false
  try {
    await pvpPostAction(matchId.value, { type: 'guess', player_id: playerId })
    return true
  } catch {
    flash.error("Impossible d'envoyer le guess.", 'PvP')
    return false
  }
}

async function onWhoisChooseTurn(firstUserId: number): Promise<boolean> {
  if (!matchId.value || navigating.value) return false
  try {
    await pvpPostAction(matchId.value, { type: 'choose_turn', first_player_user_id: firstUserId })
    return true
  } catch {
    flash.error("Impossible d'envoyer le choix du tour.", 'PvP')
    return false
  }
}

async function onWhoisGuess(playerId: number): Promise<boolean> {
  if (!matchId.value || navigating.value) return false
  try {
    await pvpPostAction(matchId.value, { type: 'guess', player_id: playerId })
    return true
  } catch {
    flash.error("Impossible d'envoyer le guess.", 'PvP')
    return false
  }
}

async function onWhoisAsk(question: { key: string; op: string; value: any }): Promise<boolean> {
  if (!matchId.value || navigating.value) return false
  try {
    await pvpPostAction(matchId.value, { type: 'ask', question })
    return true
  } catch {
    flash.error("Impossible d'envoyer l'indice.", 'PvP')
    return false
  }
}

watch(
  () => roundType.value,
  async (t) => {
    if (t !== 'whois') return
    await hydrateWhoisEvents()
  }
)

onMounted(async () => {
  if (!matchId.value) {
    flash.error('Match introuvable.', 'PvP')
    await router.push({ name: 'pvp' })
    return
  }

  await loadAll()

  eventsTimer = window.setInterval(poll, 1200)
  heartbeatTimer = window.setInterval(beat, 25000)
})

onBeforeUnmount(() => stopTimers())
</script>

<template>
  <div class="match-page">
    <main class="match-main">
      <div v-if="loading" class="state">Chargement du match…</div>
      <div v-else-if="error" class="state state--error">{{ error }}</div>

      <template v-else>
        <div v-if="match" class="top">
          <PvpScoreboard
            :game="match.game"
            :best-of="match.best_of"
            :current-round="match.current_round"
            :players="match.players || []"
            @leave="leave"
          />
        </div>

        <PvpRoundResultOverlay
          v-if="roundWinBanner"
          :winner-name="roundWinBanner.winnerName"
          :left-name="scoreLeftName"
          :right-name="scoreRightName"
          :from-left="scoreFromLeft"
          :from-right="scoreFromRight"
          :to-left="scoreToLeft"
          :to-right="scoreToRight"
          :delay-ms="overlayDurationMs"
          :score-anim-ms="overlayAnimationMs"
        />

        <PvpClassicRound
          v-if="!roundWinBanner && roundType === 'classic'"
          :match-id="matchId!"
          :game="match.game"
          :players="match.players"
          :round="round"
          @guess="async (id: number) => { await onClassicGuess(id) }"
        />

        <PvpWhoisRound
          v-else-if="!roundWinBanner && roundType === 'whois'"
          :game="match.game"
          :players="match.players"
          :round="round"
          :events="whoisEvents"
          @chooseTurn="async (uid: number) => { await onWhoisChooseTurn(uid) }"
          @guess="async (id: number) => { await onWhoisGuess(id) }"
          @ask="async (q: any) => { await onWhoisAsk(q) }"
        />

        <section v-else-if="!roundWinBanner" class="card">
          <div class="card-title">Round</div>
          <div class="state">Ce type de round n’est pas encore intégré côté front.</div>
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

.match-main {
  width: 100%;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.top {
  width: 100%;
  margin: 0 auto;
}

.card {
  width: 100%;
  margin: 0 auto;
  background: rgba(6, 8, 18, 0.92);
  border-radius: 14px;
  padding: 16px 12px 18px;
  border: 1px solid rgba(255, 255, 255, 0.06);
}

.card-title {
  font-size: 0.9rem;
  opacity: 0.85;
  margin-bottom: 10px;
  text-transform: uppercase;
  letter-spacing: 0.12em;
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
</style>
