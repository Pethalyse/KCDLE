<script setup lang="ts">
import {computed, onBeforeUnmount, onMounted, ref, watch} from 'vue'
import {useRoute, useRouter} from 'vue-router'
import {usePvpStore} from '@/stores/pvp'
import {useFlashStore} from '@/stores/flash'
import {pvpGetMatch, pvpHeartbeat, pvpLeaveMatch, pvpPollEvents, pvpPostAction} from '@/api/pvpApi'
import PvpClassicRound from '@/components/pvp/rounds/PvpClassicRound.vue'
import PvpLockedInfosRound from '@/components/pvp/rounds/PvpLockedInfosRound.vue'
import PvpDraftRound from '@/components/pvp/rounds/PvpDraftRound.vue'
import PvpWhoisRound from '@/components/pvp/rounds/PvpWhoisRound.vue'
import PvpRevealRaceRound from '@/components/pvp/rounds/PvpRevealRaceRound.vue'
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

const revealRaceLastRevealedSig = ref<string>('')

function revealedSignature(r: any): string {
  const obj = r?.revealed
  if (!obj || typeof obj !== 'object') return ''
  const keys = Object.keys(obj).sort()
  return keys.join('|')
}

async function smoothRevealRaceTick(): Promise<void> {
  if (!matchId.value || navigating.value) return

  const beforeSig = revealedSignature(match.value?.round ?? null)
  revealRaceLastRevealedSig.value = beforeSig

  const delays = [0, 300, 800, 1500]
  for (const d of delays) {
    if (navigating.value) return
    if (d > 0) {
      await new Promise(resolve => window.setTimeout(resolve, d))
    }

    try {
      const res = await pvpPollEvents(matchId.value, pvp.lastEventId, 50)
      if (Array.isArray(res.events) && res.events.length > 0) {
        pvp.setLastEventId(res.last_id)
      }

      const m2 = await pvpGetMatch(matchId.value)
      match.value = m2

      const afterSig = revealedSignature(m2?.round ?? null)
      if (afterSig !== beforeSig) {
        return
      }

      const gotRevealEvent = Array.isArray(res.events)
        ? res.events.some((e: any) => String(e?.type ?? '').startsWith('reveal_race_'))
        : false

      if (gotRevealEvent) {
        return
      }
    } catch {
    }
  }
}

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
    leftUserId: Number(a?.user_id ?? 0),
    rightUserId: Number(b?.user_id ?? 0),
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

function hasRevealRaceEvents(events: any[]): boolean {
  return (events ?? []).some((e: any) => String(e?.type ?? '').startsWith('reveal_race_'))
}

function inferWinnerFromScore(before: any, after: any): number {
  const dl = Number(after?.leftPts ?? 0) - Number(before?.leftPts ?? 0)
  const dr = Number(after?.rightPts ?? 0) - Number(before?.rightPts ?? 0)

  if (dl > 0 && dr <= 0) return Number(after?.leftUserId ?? 0)
  if (dr > 0 && dl <= 0) return Number(after?.rightUserId ?? 0)
  return 0
}

function showOverlayFromScores(before: any, after: any, winnerUserId: number) {
  navigating.value = true
  stopTimers()

  scoreLeftName.value = after.leftName
  scoreRightName.value = after.rightName
  scoreFromLeft.value = before.leftPts
  scoreFromRight.value = before.rightPts
  scoreToLeft.value = after.leftPts
  scoreToRight.value = after.rightPts

  roundWinBanner.value = {
    winnerUserId,
    winnerName: winnerUserId > 0 ? winnerNameFromMatch(winnerUserId) : 'Résultat du round',
  }

  goTransitionAfterDelay(overlayDurationMs.value)
}

async function loadAll() {
  if (!matchId.value) return
  loading.value = true
  error.value = null

  try {
    const m = await pvpGetMatch(matchId.value)
    match.value = m
    pvp.setMatch(matchId.value)

    if (m?.status === 'finished') {
      navigating.value = true
      stopTimers()
      await router.replace({ name: 'pvp_match_end', params: { matchId: matchId.value } })
      return
    }

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

let revealRaceIdleRefreshTick = 0

async function poll() {
  if (!matchId.value || navigating.value) return

  try {
    const res = await pvpPollEvents(matchId.value, pvp.lastEventId, 50)
    const events = Array.isArray(res.events) ? res.events : []

    if (events.length === 0) {
      if (roundType.value === 'reveal_race' || roundType.value === 'reveal_face') {
        revealRaceIdleRefreshTick += 1
        if (revealRaceIdleRefreshTick >= 3) {
          revealRaceIdleRefreshTick = 0
          match.value = await pvpGetMatch(matchId.value)
        }
      }
      return
    }

    revealRaceIdleRefreshTick = 0

    pvp.setLastEventId(res.last_id)
    pushWhoisEvents(events)

    const roundFinishedEv = events.find(ev => ev.type === 'round_finished')
    if (roundFinishedEv) {
      navigating.value = true
      stopTimers()

      const before = extractScore(match.value)
      const m = await pvpGetMatch(matchId.value)
      match.value = m
      const after = extractScore(m)

      const wid = Number(roundFinishedEv?.payload?.winner_user_id ?? 0)
      showOverlayFromScores(before, after, wid)
      return
    }

    const beforeRound = match.value?.current_round
    const beforeScore = extractScore(match.value)

    if (hasRevealRaceEvents(events) || true) {
      match.value = await pvpGetMatch(matchId.value)
    }

    const afterRound = match.value?.current_round
    const afterScore = extractScore(match.value)

    if (beforeRound !== afterRound) {
      whoisEvents.value = []
      await hydrateWhoisEvents()

      const winnerFromDelta = inferWinnerFromScore(beforeScore, afterScore)
      const scoreChanged = beforeScore.leftPts !== afterScore.leftPts || beforeScore.rightPts !== afterScore.rightPts

      if (scoreChanged && !roundWinBanner.value) {
        showOverlayFromScores(beforeScore, afterScore, winnerFromDelta)
        return
      }
    }

    const finished = events.some(ev => ev.type === 'match_finished') || match.value?.status === 'finished'
    const forfeited = events.some(ev => ev.type === 'player_forfeited')
    if (finished || forfeited) {
      stopTimers()
      await router.replace({ name: 'pvp_match_end', params: { matchId: matchId.value } })
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
    stopTimers()
    await router.replace({ name: 'pvp_match_end', params: { matchId: matchId.value } })
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

async function onDraftChooseOrder(firstPickerUserId: number): Promise<boolean> {
  if (!matchId.value || navigating.value) return false
  try {
    await pvpPostAction(matchId.value, { type: 'choose_draft_order', first_picker_user_id: firstPickerUserId })
    return true
  } catch {
    flash.error("Impossible d'envoyer le choix du draft.", 'PvP')
    return false
  }
}

async function onDraftPickHint(key: string): Promise<boolean> {
  if (!matchId.value || navigating.value) return false
  try {
    await pvpPostAction(matchId.value, { type: 'pick_hint', key })
    return true
  } catch {
    flash.error("Impossible d'envoyer l'indice.", 'PvP')
    return false
  }
}

async function onDraftGuess(playerId: number): Promise<boolean> {
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

async function onRevealRaceGuess(playerId: number): Promise<boolean> {
  if (!matchId.value || navigating.value) return false
  try {
    await pvpPostAction(matchId.value, { type: 'guess', player_id: playerId })
    return true
  } catch {
    flash.error("Impossible d'envoyer le guess.", 'PvP')
    return false
  }
}

async function onRevealRaceTick(): Promise<void> {
  await smoothRevealRaceTick()
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

  if (navigating.value) {
    return
  }

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
            :show-leave="true"
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

        <PvpLockedInfosRound
          v-else-if="!roundWinBanner && roundType === 'locked_infos'"
          :match-id="matchId!"
          :game="match.game"
          :players="match.players"
          :round="round"
          @guess="async (id: number) => { await onClassicGuess(id) }"
        />

        <PvpDraftRound
          v-else-if="!roundWinBanner && roundType === 'draft'"
          :match-id="matchId!"
          :game="match.game"
          :players="match.players"
          :round="round"
          @chooseOrder="async (uid: number) => { await onDraftChooseOrder(uid) }"
          @pickHint="async (k: string) => { await onDraftPickHint(k) }"
          @guess="async (id: number) => { await onDraftGuess(id) }"
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

        <PvpRevealRaceRound
          v-else-if="!roundWinBanner && (roundType === 'reveal_race' || roundType === 'reveal_face')"
          :match-id="matchId!"
          :game="match.game"
          :players="match.players"
          :round="round"
          @guess="async (id: number) => { await onRevealRaceGuess(id) }"
          @tick="onRevealRaceTick"
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
