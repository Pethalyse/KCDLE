<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'

type Phase = 'show' | 'flipAll' | 'shuffle' | 'reveal' | 'countdown'

type CardItem = {
  id: string
  label: string
}

const props = defineProps<{
  pool: string[]
  revealed: string
}>()

const emit = defineEmits<{
  (e: 'done'): void
}>()

const phase = ref<Phase>('show')
const countdown = ref<number | null>(null)
const revealIndex = ref(0)

const basePool = computed(() => {
  const uniq = Array.from(new Set((props.pool ?? []).filter((x) => x.length > 0)))
  return uniq.length > 0 ? uniq : ['classic', 'whois', 'locked_infos', 'draft', 'reveal_race']
})

const fullPool = computed(() => {
  const arr = basePool.value.slice()
  if (props.revealed && !arr.includes(props.revealed)) arr.push(props.revealed)
  return arr
})

const cards = ref<CardItem[]>([])
const pickedLabel = computed(() => props.revealed || 'classic')

let timeouts: number[] = []
let shuffleTimer: number | null = null

function clearAll() {
  timeouts.forEach((t) => window.clearTimeout(t))
  timeouts = []
  if (shuffleTimer !== null) {
    window.clearInterval(shuffleTimer)
    shuffleTimer = null
  }
}

function later(fn: () => void, ms: number) {
  const t = window.setTimeout(fn, ms)
  timeouts.push(t)
}

function resetCards() {
  cards.value = fullPool.value.map((label, i) => ({
    id: `${label}-${i}-${Math.random().toString(16).slice(2)}`,
    label,
  }))
  revealIndex.value = 0
}

function shuffleOnce() {
  if (cards.value.length <= 1) return
  const a = Math.floor(Math.random() * cards.value.length)
  let b = Math.floor(Math.random() * cards.value.length)
  if (b === a) b = (b + 1) % cards.value.length

  const next = cards.value.slice()

  const itemA = next[a]
  const itemB = next[b]
  if (!itemA || !itemB) return

  next[a] = itemB
  next[b] = itemA
  cards.value = next
}


function startShuffle(durationMs: number, stepMs: number) {
  const steps = Math.max(1, Math.floor(durationMs / stepMs))
  let done = 0
  shuffleTimer = window.setInterval(() => {
    shuffleOnce()
    done += 1
    if (done >= steps) {
      if (shuffleTimer !== null) {
        window.clearInterval(shuffleTimer)
        shuffleTimer = null
      }
    }
  }, stepMs)
}

const title = computed(() => {
  if (phase.value === 'show') return 'Tirage du round...'
  if (phase.value === 'flipAll') return 'Préparation...'
  if (phase.value === 'shuffle') return 'Mélange...'
  if (phase.value === 'reveal') return 'Round révélé'
  if (phase.value === 'countdown') return 'Prépare-toi'
  return ''
})

function isFrontVisible(i: number) {
  if (phase.value === 'show') return true
  if (phase.value === 'flipAll') return false
  if (phase.value === 'shuffle') return false
  if (phase.value === 'reveal') return i === revealIndex.value
  if (phase.value === 'countdown') return i === revealIndex.value
  return false
}

function cardInnerStyle(i: number) {
  return {
    transform: isFrontVisible(i) ? 'rotateY(180deg)' : 'rotateY(0deg)',
  }
}

function frontLabelFor(i: number) {
  const isPicked = i === revealIndex.value && (phase.value === 'reveal' || phase.value === 'countdown')
  return isPicked ? pickedLabel.value : cards.value[i]?.label ?? ''
}

function start() {
  clearAll()
  resetCards()
  countdown.value = null
  phase.value = 'show'

  later(() => {
    phase.value = 'flipAll'
  }, 1400)

  later(() => {
    phase.value = 'shuffle'
    startShuffle(2600, 220)
  }, 2200)

  later(() => {
    phase.value = 'reveal'
  }, 5200)

  later(() => {
    phase.value = 'countdown'
    countdown.value = 3
  }, 6200)

  later(() => {
    countdown.value = 2
  }, 7200)

  later(() => {
    countdown.value = 1
  }, 8200)

  later(() => {
    emit('done')
  }, 9200)
}

onMounted(start)
onBeforeUnmount(clearAll)

watch(
  () => [props.revealed, props.pool],
  () => start(),
  { deep: true },
)
</script>

<template>
  <div class="overlay">
    <div class="wrap" :class="{ 'wrap--focus': phase === 'reveal' || phase === 'countdown' }">
      <div class="top" v-if="!(phase === 'reveal' || phase === 'countdown')">
        <div class="headline">{{ title }}</div>
      </div>

      <div class="table" v-if="!(phase === 'reveal' || phase === 'countdown')">
        <TransitionGroup name="card-move" tag="div" class="cards" :data-phase="phase">
          <div
            v-for="(c, i) in cards"
            :key="c.id"
            class="card"
          >
            <div class="inner" :style="cardInnerStyle(i)">
              <div class="face back">
                <div class="back-glow"></div>
<!--                <div class="back-mark">PVP</div>-->
              </div>

              <div class="face front">
                <div class="front-label">{{ frontLabelFor(i) }}</div>
              </div>
            </div>
          </div>
        </TransitionGroup>
      </div>

      <div v-if="phase === 'reveal' || phase === 'countdown'" class="focus-layer">
        <div class="focus-card">
          <div class="focus-inner">
            <div class="focus-label">{{ pickedLabel }}</div>
          </div>

          <div v-if="phase === 'countdown' && countdown !== null" class="focus-count">
            {{ countdown }}
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.overlay {
  position: fixed;
  inset: 0;
  z-index: 9999;
  background: radial-gradient(circle at top, rgba(32, 38, 58, 0.96) 0, rgba(5, 6, 10, 0.98) 78%);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 14px;
  color: #f3f3f3;
}

.wrap {
  width: 100%;
  max-width: 980px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.top {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 12px;
}

.headline {
  font-size: 1.2rem;
  letter-spacing: 0.02em;
  opacity: 0.92;
}

.table {
  width: 100%;
  background: rgba(6, 8, 18, 0.92);
  border-radius: 16px;
  padding: 18px 12px 16px;
  box-shadow: 0 12px 30px rgba(0, 0, 0, 0.65);
  border: 1px solid rgba(255, 255, 255, 0.06);
}

.cards {
  position: relative;
  min-height: 190px;
  display: flex;
  gap: 10px;
  align-items: center;
  justify-content: center;
  flex-wrap: wrap;
  padding: 8px 4px 10px;
}

.card {
  width: 152px;
  height: 110px;
  perspective: 1200px;
  opacity: 1;
  transform: translateY(0);
}

.card-move-move {
  transition: transform 240ms ease;
}

.cards[data-phase="show"] .card {
  animation: deal 0.7s ease-out both;
}

.inner {
  width: 100%;
  height: 100%;
  position: relative;
  transform-style: preserve-3d;
  border-radius: 14px;
  transition: transform 650ms ease-in-out;
}

.face {
  position: absolute;
  inset: 0;
  border-radius: 14px;
  backface-visibility: hidden;
  border: 1px solid rgba(255, 255, 255, 0.10);
  overflow: hidden;
  display: flex;
  align-items: center;
  justify-content: center;
}

.back {
  background: linear-gradient(135deg, rgba(0, 166, 255, 0.16), rgba(255, 255, 255, 0.05));
}

.back-glow {
  position: absolute;
  inset: -40px;
  background: radial-gradient(circle, rgba(0, 166, 255, 0.35), transparent 55%);
  filter: blur(10px);
  opacity: 0.7;
}

.back-mark {
  position: relative;
  font-weight: 800;
  letter-spacing: 0.18em;
  opacity: 0.9;
  text-transform: uppercase;
}

.front {
  background: linear-gradient(135deg, rgba(255, 255, 255, 0.10), rgba(0, 0, 0, 0.35));
  transform: rotateY(180deg);
}

.front-label {
  padding: 0 12px;
  text-align: center;
  font-weight: 800;
  font-size: 0.95rem;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  text-shadow: 0 0 12px rgba(0,0,0,0.6);
}

.focus-layer {
  position: fixed;
  inset: 0;
  display: grid;
  place-items: center;
  padding: 18px;
}

.focus-card {
  width: min(720px, 92vw);
  height: min(420px, 56vh);
  border-radius: 22px;
  background: linear-gradient(135deg, rgba(255, 255, 255, 0.10), rgba(0, 0, 0, 0.45));
  border: 1px solid rgba(255, 255, 255, 0.12);
  box-shadow: 0 20px 60px rgba(0,0,0,0.75);
  position: relative;
  overflow: hidden;
  animation: focusIn 520ms ease-out both;
}

.focus-inner {
  position: absolute;
  inset: 0;
  display: grid;
  place-items: center;
}

.focus-label {
  font-weight: 900;
  letter-spacing: 0.10em;
  text-transform: uppercase;
  text-align: center;
  font-size: clamp(1.4rem, 4vw, 3.2rem);
  text-shadow: 0 0 18px rgba(0, 166, 255, 0.30);
  padding: 0 16px;
}

.focus-count {
  position: absolute;
  inset: 0;
  display: grid;
  place-items: center;
  font-weight: 900;
  font-size: clamp(3.2rem, 10vw, 7rem);
  text-shadow: 0 0 26px rgba(0, 166, 255, 0.35);
  background: radial-gradient(circle, rgba(0, 0, 0, 0.30), rgba(0, 0, 0, 0.55));
}

@keyframes deal {
  0% {
    transform: translateY(14px) scale(0.98);
    opacity: 0;
  }
  100% {
    transform: translateY(0) scale(1);
    opacity: 1;
  }
}

@keyframes focusIn {
  0% {
    transform: translateY(10px) scale(0.98);
    opacity: 0;
  }
  100% {
    transform: translateY(0) scale(1);
    opacity: 1;
  }
}

@media (max-width: 520px) {
  .card {
    width: 140px;
    height: 104px;
  }
  .headline {
    font-size: 1.05rem;
  }
}
</style>
