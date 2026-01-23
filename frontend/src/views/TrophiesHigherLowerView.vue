<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import SimpleImg from '@/components/SimpleImg.vue'
import { startTrophiesHigherLower, guessTrophiesHigherLower, endTrophiesHigherLower } from '@/api/trophiesHigherLowerApi'
import type { HigherLowerGuessResponse, HigherLowerSide, HigherLowerState } from '@/types/trophiesHigherLower'
import { handleError } from '@/utils/handleError'

const modeTitle = 'Plus ou Moins'
const modeSubtitle = 'Trophées'

const state = ref<HigherLowerState | null>(null)
const busy = ref(false)

const revealStage = ref<0 | 1 | 2>(0)
const clicked = ref<HigherLowerSide | null>(null)
const firstReveal = ref<'left' | 'right'>('left')
const revealValues = ref<{ left: number | null; right: number | null }>({ left: null, right: null })
const lastGuessCorrect = ref<boolean | null>(null)
const gameOver = ref(false)

const hasStarted = ref(false)
const transitioning = ref(false)

const REVEAL_FIRST_DELAY = 650
const REVEAL_SECOND_DELAY = 1900
const AFTER_REVEAL_DELAY = 2600

const TRANSITION_OUT_DURATION = 380
const TRANSITION_IN_DURATION = 420

const canInteract = computed(() => hasStarted.value && !busy.value && !gameOver.value && !transitioning.value)

function trophiesText(side: 'left' | 'right'): string {
  const s = state.value
  if (!s) return '—'

  const isRevealedNow = revealStage.value === 2 || (revealStage.value === 1 && firstReveal.value === side)

  if (isRevealedNow) {
    const v = side === 'left' ? revealValues.value.left : revealValues.value.right
    return v === null ? '—' : `${v}`
  }

  const known = side === 'left' ? s.left.trophies_count : s.right.trophies_count
  if (known !== null) return `${known}`

  return '??'
}

function computeFirstReveal(choice: HigherLowerSide): 'left' | 'right' {
  return choice === 'right' ? 'right' : 'left'
}

function sideClass(side: 'left' | 'right') {
  return {
    'hl-side': true,
    'hl-side--left': side === 'left',
    'hl-side--right': side === 'right',
    'hl-side--disabled': !canInteract.value,
    'hl-side--clickable': canInteract.value,
    'hl-side--clicked': clicked.value === side,
    'hl-side--correct': revealStage.value === 2 && lastGuessCorrect.value === true && clicked.value === side,
    'hl-side--wrong': revealStage.value === 2 && lastGuessCorrect.value === false && clicked.value === side,
    'hl-side--transitioning': transitioning.value,
  }
}

function equalClass() {
  return {
    'hl-equal': true,
    'hl-equal--disabled': !canInteract.value,
  }
}

async function loadSession() {
  busy.value = true
  try {
    const data = await startTrophiesHigherLower()
    state.value = data

    revealStage.value = 0
    clicked.value = null
    revealValues.value = { left: null, right: null }
    lastGuessCorrect.value = null
    gameOver.value = false
    transitioning.value = false
  } catch (e) {
    handleError(e)
  } finally {
    busy.value = false
  }
}

async function startGame() {
  if (busy.value) return
  if (!state.value) await loadSession()
  hasStarted.value = true
}

async function restartFromOverlay() {
  busy.value = true
  try {
    if (state.value?.session_id) {
      try {
        await endTrophiesHigherLower(state.value.session_id)
      } catch (e) {}
    }
  } finally {
    busy.value = false
  }

  await loadSession()
  hasStarted.value = true
}

async function onPick(choice: HigherLowerSide) {
  if (!state.value) return
  if (!canInteract.value) return

  busy.value = true
  revealStage.value = 0
  clicked.value = choice
  firstReveal.value = computeFirstReveal(choice)
  lastGuessCorrect.value = null
  transitioning.value = false

  let res: HigherLowerGuessResponse
  try {
    res = await guessTrophiesHigherLower(state.value.session_id, choice)
  } catch (e) {
    handleError(e)
    busy.value = false
    return
  }

  revealValues.value = { left: res.reveal.left, right: res.reveal.right }
  lastGuessCorrect.value = res.correct

  window.setTimeout(() => {
    revealStage.value = 1
  }, REVEAL_FIRST_DELAY)

  window.setTimeout(() => {
    revealStage.value = 2
  }, REVEAL_SECOND_DELAY)

  window.setTimeout(() => {
    if (!res.correct || !res.next) {
      gameOver.value = true
      busy.value = false
      return
    }

    transitioning.value = true

    window.setTimeout(() => {
      state.value = res.next
      revealStage.value = 0
      clicked.value = null
      revealValues.value = { left: null, right: null }
      lastGuessCorrect.value = null
    }, TRANSITION_OUT_DURATION)

    window.setTimeout(() => {
      transitioning.value = false
      busy.value = false
    }, TRANSITION_OUT_DURATION + TRANSITION_IN_DURATION)
  }, AFTER_REVEAL_DELAY)
}

onMounted(() => {
  void loadSession()
})
</script>

<template>
  <div class="hl-root">
    <div v-if="!state" class="hl-loading">
      <div class="hl-loading-card">Chargement…</div>
    </div>

    <div v-else class="hl-stage">
      <div class="hl-split">
        <button type="button" :class="sideClass('left')" @click="onPick('left')">
          <div class="hl-card">
            <div class="hl-photo">
              <SimpleImg :img="state.left.image_url" :alt="state.left.name" />
            </div>

            <div class="hl-name">{{ state.left.name }}</div>

            <div class="hl-count hl-count--center">
              <div class="hl-count-value">{{ trophiesText('left') }}</div>
              <div class="hl-count-label">trophées</div>
            </div>
          </div>
        </button>
        <div class="hl-center">
          <div class="hl-or">OU</div>
          <button type="button" :class="equalClass()" @click="onPick('equal')">ÉGAL</button>
        </div>
        <button type="button" :class="sideClass('right')" @click="onPick('right')">
          <div class="hl-card">
            <div class="hl-photo">
              <SimpleImg :img="state.right.image_url" :alt="state.right.name" />
            </div>

            <div class="hl-name">{{ state.right.name }}</div>

            <div class="hl-count hl-count--center">
              <div class="hl-count-value">{{ trophiesText('right') }}</div>
              <div class="hl-count-label">trophées</div>
            </div>
          </div>
        </button>
      </div>

      <div v-if="!hasStarted" class="hl-start">
        <div class="hl-start-card">
          <div class="hl-start-title">{{ modeTitle }}</div>
          <div class="hl-start-sub">{{ modeSubtitle }}</div>

          <div class="hl-rules">
            <div class="hl-rules-title">Règles</div>
            <ul class="hl-rules-list">
              <li>Deux joueurs sont affichés. Clique celui qui a le plus de trophées.</li>
              <li>Si tu penses qu’ils ont exactement le même nombre, clique <strong>ÉGAL</strong>.</li>
              <li>Au début, tu n’as pas les trophées : ils se révèlent après ton choix.</li>
              <li>Si tu as bon, le jeu continue avec un nouveau joueur. Si tu as faux, c’est terminé.</li>
            </ul>
          </div>

          <button type="button" class="hl-start-btn" @click="startGame">Jouer</button>
        </div>
      </div>

      <div v-if="gameOver" class="hl-gameover">
        <div class="hl-gameover-card">
          <div class="hl-gameover-title">Perdu</div>
          <button type="button" class="hl-gameover-btn" @click="restartFromOverlay">Rejouer</button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
:global(html),
:global(body) {
  height: 100%;
  overflow: hidden;
}

.hl-root {
  width: 100%;
  height: calc(100vh - 64px);
  overflow: hidden;
  color: #f5f7ff;
}

.hl-stage {
  width: 100%;
  height: 100%;
  position: relative;
  background: radial-gradient(circle at top, rgba(32, 38, 58, 1) 0%, rgba(5, 6, 10, 1) 65%);
  border-radius: 0;
  overflow: hidden;
}

.hl-loading {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  background: radial-gradient(circle at top, rgba(32, 38, 58, 1) 0%, rgba(5, 6, 10, 1) 65%);
  border: 1px solid rgba(255, 255, 255, 0.10);
}

.hl-loading-card {
  padding: 14px 16px;
  border-radius: 14px;
  background: rgba(0, 0, 0, 0.55);
  border: 1px solid rgba(255, 255, 255, 0.14);
  box-shadow: 0 14px 40px rgba(0, 0, 0, 0.45);
  font-weight: 900;
}

.hl-split {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 18px;
  padding: 24px;
}

.hl-side {
  width: min(520px, 42vw);
  height: min(620px, 70vh);
  border: none;
  padding: 0;
  cursor: pointer;
  color: inherit;
  background: transparent;
  transition: filter 220ms ease, transform 380ms ease, opacity 380ms ease;
}

.hl-side--clickable:hover {
  filter: brightness(1.05);
}

.hl-side--disabled {
  cursor: not-allowed;
}

.hl-side--transitioning {
  opacity: 0;
  transform: scale(0.985);
}

.hl-card {
  position: relative;
  width: 100%;
  height: 100%;
  border-radius: 18px;
  overflow: hidden;
  border: 1px solid rgba(255, 255, 255, 0.12);
  box-shadow: 0 22px 70px rgba(0, 0, 0, 0.55);
  background: rgba(0, 0, 0, 0.18);
}

.hl-photo {
  position: absolute;
  inset: 0;
  overflow: hidden;
}

.hl-photo :deep(img) {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.hl-name {
  position: absolute;
  left: 14px;
  right: 14px;
  top: 14px;
  font-weight: 900;
  font-size: 22px;
  line-height: 1.1;
  text-shadow: 0 2px 14px rgba(0, 0, 0, 0.60);
  z-index: 3;
}

.hl-count {
  z-index: 4;
  text-align: center;
  padding: 10px 12px;
  border-radius: 14px;
  background: rgba(0, 0, 0, 0.50);
  border: 1px solid rgba(255, 255, 255, 0.14);
  box-shadow: 0 16px 50px rgba(0, 0, 0, 0.45);
  backdrop-filter: blur(8px);
}

.hl-count--center {
  position: absolute;
  left: 50%;
  top: 52%;
  transform: translate(-50%, -50%);
  min-width: 140px;
}

.hl-count-value {
  font-size: 44px;
  font-weight: 900;
  line-height: 1;
}

.hl-count-label {
  font-size: 12px;
  opacity: 0.85;
  margin-top: 4px;
}

.hl-center {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 10px;
  pointer-events: none;
}

.hl-or {
  width: 84px;
  height: 84px;
  border-radius: 999px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 900;
  letter-spacing: 0.08em;
  background: rgba(0, 0, 0, 0.62);
  border: 1px solid rgba(255, 255, 255, 0.18);
  box-shadow: 0 16px 50px rgba(0, 0, 0, 0.55);
  backdrop-filter: blur(8px);
}

.hl-equal {
  pointer-events: auto;
  border: none;
  border-radius: 999px;
  padding: 10px 16px;
  font-weight: 900;
  cursor: pointer;
  background: rgba(255, 255, 255, 0.16);
  color: #f5f7ff;
  border: 1px solid rgba(255, 255, 255, 0.18);
  box-shadow: 0 16px 50px rgba(0, 0, 0, 0.45);
  backdrop-filter: blur(8px);
}

.hl-equal:hover {
  background: rgba(255, 255, 255, 0.22);
}

.hl-equal--disabled {
  cursor: not-allowed;
  opacity: 0.82;
}

.hl-side--correct .hl-card {
  outline: 2px solid rgba(34, 197, 94, 0.50);
  outline-offset: -2px;
}

.hl-side--wrong .hl-card {
  outline: 2px solid rgba(239, 68, 68, 0.50);
  outline-offset: -2px;
}

.hl-side--clicked {
  filter: brightness(1.06);
}

.hl-start {
  position: absolute;
  inset: 0;
  z-index: 40;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(0, 0, 0, 0.55);
}

.hl-start-card {
  width: min(520px, 92vw);
  padding: 18px;
  border-radius: 18px;
  background: rgba(0, 0, 0, 0.86);
  border: 1px solid rgba(255, 255, 255, 0.16);
  box-shadow: 0 22px 70px rgba(0, 0, 0, 0.7);
  text-align: left;
}

.hl-start-title {
  font-size: 22px;
  font-weight: 900;
}

.hl-start-sub {
  margin-top: 4px;
  opacity: 0.9;
}

.hl-rules {
  margin-top: 12px;
  padding: 12px 12px 10px;
  border-radius: 14px;
  background: rgba(255, 255, 255, 0.06);
  border: 1px solid rgba(255, 255, 255, 0.12);
}

.hl-rules-title {
  font-weight: 900;
  margin-bottom: 6px;
}

.hl-rules-list {
  margin: 0;
  padding-left: 18px;
  opacity: 0.92;
  line-height: 1.35;
  font-size: 13px;
}

.hl-start-btn {
  margin-top: 14px;
  width: 100%;
  border: 0;
  border-radius: 14px;
  padding: 12px 14px;
  font-weight: 900;
  cursor: pointer;
  background: rgba(0, 166, 255, 0.22);
  color: #f5f7ff;
  border: 1px solid rgba(102, 224, 255, 0.18);
}

.hl-start-btn:hover {
  background: rgba(0, 166, 255, 0.30);
}

.hl-gameover {
  position: absolute;
  inset: 0;
  z-index: 50;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(0, 0, 0, 0.55);
}

.hl-gameover-card {
  width: min(420px, 92vw);
  padding: 18px;
  border-radius: 18px;
  background: rgba(0, 0, 0, 0.86);
  border: 1px solid rgba(255, 255, 255, 0.16);
  box-shadow: 0 22px 70px rgba(0, 0, 0, 0.7);
  text-align: center;
}

.hl-gameover-title {
  font-size: 22px;
  font-weight: 900;
}

.hl-gameover-btn {
  margin-top: 14px;
  width: 100%;
  border: 0;
  border-radius: 14px;
  padding: 12px 14px;
  font-weight: 900;
  cursor: pointer;
  background: rgba(0, 166, 255, 0.22);
  color: #f5f7ff;
  border: 1px solid rgba(102, 224, 255, 0.18);
}

.hl-gameover-btn:hover {
  background: rgba(0, 166, 255, 0.30);
}

@media (max-width: 900px) {
  .hl-split {
    flex-direction: column;
    gap: 14px;
    padding: 18px;
  }

  .hl-side {
    width: min(520px, 92vw);
    height: min(320px, 34vh);
  }

  .hl-count--center {
    top: 54%;
  }

  .hl-count-value {
    font-size: 38px;
  }

  .hl-name {
    font-size: 20px;
  }

  .hl-or {
    width: 74px;
    height: 74px;
  }
}
</style>
