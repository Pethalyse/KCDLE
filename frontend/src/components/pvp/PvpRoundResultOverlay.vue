<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'

const props = defineProps<{
  winnerName: string
  leftName: string
  rightName: string
  fromLeft: number
  fromRight: number
  toLeft: number
  toRight: number
  delayMs?: number
  scoreAnimMs?: number
}>()

const visible = ref(false)

const animMs = computed(() => {
  const v = Number(props.scoreAnimMs ?? 650)
  return Number.isFinite(v) ? Math.max(250, Math.min(1500, v)) : 650
})

const left = ref<number>(Number(props.fromLeft ?? 0))
const right = ref<number>(Number(props.fromRight ?? 0))

function animateScore(fromA: number, toA: number, fromB: number, toB: number) {
  left.value = fromA
  right.value = fromB

  const start = performance.now()
  const duration = animMs.value

  const step = (t: number) => {
    const p = Math.min(1, (t - start) / duration)
    const eased = 1 - Math.pow(1 - p, 3)

    const curA = Math.round(fromA + (toA - fromA) * eased)
    const curB = Math.round(fromB + (toB - fromB) * eased)

    left.value = curA
    right.value = curB

    if (p < 1) requestAnimationFrame(step)
  }

  requestAnimationFrame(step)
}

onMounted(() => {
  requestAnimationFrame(() => {
    visible.value = true
  })

  window.setTimeout(() => {
    animateScore(
      Number(props.fromLeft ?? 0),
      Number(props.toLeft ?? 0),
      Number(props.fromRight ?? 0),
      Number(props.toRight ?? 0),
    )
  }, 1000)
})

watch(
  () => [props.fromLeft, props.fromRight, props.toLeft, props.toRight],
  ([fa, fb, ta, tb]) => {
    animateScore(Number(fa ?? 0), Number(ta ?? 0), Number(fb ?? 0), Number(tb ?? 0))
  },
)
</script>

<template>
  <div class="overlay" :class="{ 'is-visible': visible }" aria-live="polite">
    <div class="panel">
      <div class="kicker">Résultat du round</div>
      <div class="title">{{ winnerName }} remporte le round</div>

      <div class="scoreboard">
        <div class="name left" :title="leftName">{{ leftName }}</div>
        <div class="num">{{ left }}</div>
        <div class="dash">-</div>
        <div class="num">{{ right }}</div>
        <div class="name right" :title="rightName">{{ rightName }}</div>
      </div>

      <div class="sub">Round suivant…</div>

      <div class="loader" aria-hidden="true">
        <span class="dot" />
        <span class="dot" />
        <span class="dot" />
      </div>
    </div>
  </div>
</template>

<style scoped>
.overlay,
.overlay * {
  box-sizing: border-box;
}

.overlay {
  position: fixed;
  inset: 0;
  z-index: 9999;
  display: grid;
  place-items: center;
  padding: 18px 12px;
  background: rgba(0, 0, 0, 0.55);
  backdrop-filter: blur(6px);
  opacity: 0;
  transform: scale(1.02);
  transition: opacity 260ms ease, transform 260ms ease;
  overflow-x: hidden;
}

.overlay.is-visible {
  opacity: 1;
  transform: scale(1);
}

.panel {
  width: min(680px, 92vw);
  max-width: calc(100vw - 24px);
  border-radius: 18px;
  padding: 18px 16px 16px;
  border: 1px solid rgba(255, 255, 255, 0.10);
  background: radial-gradient(circle at top, #20263a, rgba(10, 12, 22, 0.92) 65%);
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.65);
  text-align: center;
  animation: pop 520ms ease both;
  overflow: hidden;
}

@keyframes pop {
  0% { transform: translateY(10px) scale(0.98); opacity: 0; }
  100% { transform: translateY(0) scale(1); opacity: 1; }
}

.kicker {
  font-size: 0.85rem;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  opacity: 0.85;
}

.title {
  margin-top: 8px;
  font-size: 1.25rem;
  font-weight: 900;
}

.scoreboard {
  margin-top: 10px;
  display: flex;
  align-items: baseline;
  justify-content: center;
  gap: 10px;
  flex-wrap: nowrap;
  padding: 10px 12px;
  border-radius: 14px;
  border: 1px solid rgba(255, 255, 255, 0.10);
  background: rgba(0, 0, 0, 0.22);
  max-width: 100%;
  min-width: 0;
}

.name {
  font-size: 0.95rem;
  opacity: 0.95;
  max-width: 32vw;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  min-width: 0;
  flex: 1 1 0;
}

.num {
  font-size: 1.45rem;
  font-weight: 950;
  min-width: 22px;
  text-align: center;
  flex: 0 0 auto;
}

.dash {
  opacity: 0.8;
  font-weight: 700;
  flex: 0 0 auto;
}

.sub {
  margin-top: 10px;
  font-size: 0.95rem;
  opacity: 0.85;
}

.loader {
  margin-top: 12px;
  display: inline-flex;
  gap: 8px;
  align-items: center;
  justify-content: center;
}

.dot {
  width: 9px;
  height: 9px;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.85);
  animation: bounce 900ms infinite ease-in-out;
}

.dot:nth-child(2) { animation-delay: 120ms; }
.dot:nth-child(3) { animation-delay: 240ms; }

@keyframes bounce {
  0%, 100% { transform: translateY(0); opacity: 0.65; }
  50% { transform: translateY(-6px); opacity: 1; }
}

@media (max-width: 520px) {
  .title { font-size: 1.1rem; }
  .panel { padding: 16px 14px 14px; }
  .num { font-size: 1.25rem; }
  .name { max-width: 28vw; }
}
</style>
