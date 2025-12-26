<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import PvpGuessWithHints from '@/components/pvp/PvpGuessWithHints.vue'

type GameCode = 'kcdle' | 'lecdle' | 'lfldle'

const props = defineProps<{
  matchId: number
  game: GameCode
  players: Array<{ user_id: number; name?: string | null; points: number }>
  round: any
}>()

const emit = defineEmits<{
  (e: 'guess', playerId: number): void
  (e: 'tick'): void
}>()

function computeNextRevealMs(round: any): number {
  const direct = Number(round?.next_reveal_in_ms ?? 0)
  if (Number.isFinite(direct) && direct > 0) return direct

  const next = typeof round?.next_reveal_at === 'string' ? Date.parse(round.next_reveal_at) : NaN
  if (!Number.isFinite(next)) return 0

  const server = typeof round?.server_time === 'string' ? Date.parse(round.server_time) : Date.now()
  if (!Number.isFinite(server)) return Math.max(0, next - Date.now())

  return Math.max(0, next - server)
}

function computeBlockedMs(round: any): number {
  const direct = Number(round?.you?.blocked_ms ?? 0)
  const directOk = Number.isFinite(direct) && direct > 0 ? direct : 0

  const untilIso = typeof round?.you?.lock_blocked_until === 'string' ? round.you.lock_blocked_until : ''
  const until = untilIso ? Date.parse(untilIso) : NaN

  const server = typeof round?.server_time === 'string' ? Date.parse(round.server_time) : Date.now()
  const serverOk = Number.isFinite(server) ? server : Date.now()

  const fromUntil = Number.isFinite(until) ? Math.max(0, until - serverOk) : 0

  return Math.max(directOk, fromUntil)
}

const nextRevealMsBackend = computed(() => computeNextRevealMs(props.round))
const blockedMsBackend = computed(() => computeBlockedMs(props.round))

const nextRevealMsUi = ref(0)
const blockedMsUi = ref(0)

let timer: number | null = null
function stop() {
  if (timer !== null) {
    window.clearInterval(timer)
    timer = null
  }
}

function startTimers(nextMs: number, blockedMs: number) {
  stop()
  nextRevealMsUi.value = Math.max(0, Math.floor(nextMs))
  blockedMsUi.value = Math.max(0, Math.floor(blockedMs))

  if (nextRevealMsUi.value <= 0 && blockedMsUi.value <= 0) return

  timer = window.setInterval(() => {
    const beforeNext = nextRevealMsUi.value
    const beforeBlocked = blockedMsUi.value

    if (beforeNext > 0) nextRevealMsUi.value = Math.max(0, beforeNext - 250)
    if (beforeBlocked > 0) blockedMsUi.value = Math.max(0, beforeBlocked - 250)

    if (beforeNext > 0 && nextRevealMsUi.value === 0) emit('tick')

    if (nextRevealMsUi.value <= 0 && blockedMsUi.value <= 0) stop()
  }, 250)
}

watch(
  () => [nextRevealMsBackend.value, blockedMsBackend.value],
  ([n, b]) => startTimers(Number(n ?? 0), Number(b ?? 0)),
  { immediate: true }
)

onMounted(() => startTimers(nextRevealMsBackend.value, blockedMsBackend.value))
onBeforeUnmount(() => stop())

const timerText = computed(() => {
  const hasSchedule = !!props.round?.next_reveal_in_ms || !!props.round?.next_reveal_at
  if (!hasSchedule) return null

  const ms = nextRevealMsUi.value
  const total = Math.ceil(ms / 1000)

  if (total <= 0) return 'Prochain indice imminent…'

  const m = Math.floor(total / 60)
  const s = total % 60
  return m > 0 ? `Prochain indice dans ${m}m ${String(s).padStart(2, '0')}s` : `Prochain indice dans ${s}s`
})

const blockedText = computed(() => {
  if (blockedMsUi.value <= 0) return null
  const s = Math.ceil(blockedMsUi.value / 1000)
  return `Bloqué ${s}s`
})

const isBlocked = computed(() => blockedMsUi.value > 0)

const backendSolved = computed(() => !!props.round?.you?.solved_at)
const guessCount = computed(() => {
  const list = Array.isArray(props.round?.you?.guesses) ? props.round.you.guesses : []
  return list.length
})

const optimisticLock = ref(false)
const optimisticGuessId = ref<number | null>(null)
let optimisticSafety: number | null = null

function clearOptimistic() {
  optimisticLock.value = false
  optimisticGuessId.value = null
  if (optimisticSafety !== null) {
    window.clearTimeout(optimisticSafety)
    optimisticSafety = null
  }
}

watch(
  () => blockedMsBackend.value,
  (b) => {
    if (Number(b ?? 0) > 0) {
      clearOptimistic()
    }
  }
)

watch(
  () => backendSolved.value,
  (s) => {
    if (s) {
      clearOptimistic()
    }
  }
)

watch(
  () => guessCount.value,
  (n, p) => {
    if (n > p) {
      clearOptimistic()
    }
  }
)

const isUiLocked = computed(() => optimisticLock.value || isBlocked.value)

function onGuess(playerId: number) {
  if (isUiLocked.value) return
  optimisticLock.value = true
  optimisticGuessId.value = playerId
  emit('guess', playerId)

  if (optimisticSafety !== null) window.clearTimeout(optimisticSafety)
  optimisticSafety = window.setTimeout(() => {
    if (!optimisticLock.value) return
    if (isBlocked.value) return
    if (backendSolved.value) return
    clearOptimistic()
  }, 4000)
}
</script>

<template>
  <div class="reveal-wrap">
    <div v-if="timerText" class="reveal-timer">{{ timerText }}</div>

    <div class="guess-zone" :class="{ 'guess-zone--blocked': isUiLocked }">
      <PvpGuessWithHints
        :match-id="matchId"
        :game="game"
        :players="players"
        :round="round"
        @guess="onGuess"
      />

      <div v-if="isUiLocked" class="blocked-overlay">
        <div class="blocked-badge">
          <template v-if="isBlocked">
            <div class="blocked-title">Mauvais guess</div>
            <div class="blocked-sub">{{ blockedText }}</div>
          </template>
          <template v-else>
            <div class="blocked-title">Envoi du guess…</div>
            <div class="blocked-sub">Patiente une seconde.</div>
          </template>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.reveal-wrap {
  width: 100%;
}

.reveal-timer {
  width: 100%;
  max-width: 100%;
  box-sizing: border-box;
  margin: 0 auto 10px;
  padding: 10px 12px;
  border-radius: 14px;
  border: 1px solid rgba(255, 255, 255, 0.08);
  background: rgba(0, 0, 0, 0.22);
  color: rgba(255, 255, 255, 0.92);
  text-align: center;
  line-height: 1.2;
  word-break: break-word;
}

.guess-zone {
  position: relative;
  width: 100%;
}

.blocked-overlay {
  position: absolute;
  inset: 0;
  border-radius: 14px;
  background: rgba(0, 0, 0, 0.55);
  backdrop-filter: blur(2px);
  display: flex;
  align-items: flex-start;
  justify-content: center;
  padding: 12px;
  box-sizing: border-box;
  pointer-events: all;
}

.blocked-badge {
  width: 100%;
  max-width: 520px;
  box-sizing: border-box;
  padding: 12px 12px;
  border-radius: 14px;
  border: 1px solid rgba(255, 255, 255, 0.10);
  background: rgba(0, 0, 0, 0.35);
  text-align: center;
}

.blocked-title {
  font-weight: 700;
  letter-spacing: 0.02em;
}

.blocked-sub {
  margin-top: 4px;
  opacity: 0.92;
}

.guess-zone--blocked :deep(input),
.guess-zone--blocked :deep(textarea),
.guess-zone--blocked :deep(select) {
  display: none !important;
}

.guess-zone--blocked :deep([role="search"]),
.guess-zone--blocked :deep(.search),
.guess-zone--blocked :deep(.searchbar),
.guess-zone--blocked :deep(.search-tab),
.guess-zone--blocked :deep(.searchTab),
.guess-zone--blocked :deep(.search-wrap),
.guess-zone--blocked :deep(.searchWrap),
.guess-zone--blocked :deep(.search-container),
.guess-zone--blocked :deep(.searchContainer) {
  display: none !important;
}
</style>
