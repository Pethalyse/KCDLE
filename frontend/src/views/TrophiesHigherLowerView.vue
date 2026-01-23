<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
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
const showResult = ref(false)
const gameOver = ref(false)

const hasStarted = ref(false)
const transitioning = ref(false)

const currentStreak = ref(0)
const bestStreak = ref(0)
const BEST_STREAK_STORAGE_KEY = 'kcdle_hl_trophies_best_streak'

const animateLeft = ref(false)
const animateRight = ref(false)
const knownBeforePick = ref<{ left: boolean; right: boolean }>({ left: false, right: false })
const animatedThisRound = ref<{ left: boolean; right: boolean }>({ left: false, right: false })

const REVEAL_FIRST_DELAY = 750
const REVEAL_SECOND_DELAY = 2400
const RESULT_AFTER_REVEAL_DELAY = 450
const NEXT_AFTER_RESULT_DELAY = 900

const TRANSITION_OUT_DURATION = 380
const TRANSITION_IN_DURATION = 420

const canInteract = computed(() => hasStarted.value && !busy.value && !gameOver.value && !transitioning.value)

const centerText = computed(() => {
  if (!showResult.value) return 'OU'
  if (lastGuessCorrect.value === true) return 'V'
  if (lastGuessCorrect.value === false) return 'X'
  return 'OU'
})

const centerOrClass = computed(() => {
  return {
    'hl-or': true,
    'hl-or--win': showResult.value && lastGuessCorrect.value === true,
    'hl-or--lose': showResult.value && lastGuessCorrect.value === false,
    'hl-or--result': showResult.value,
    'hl-or--transitioning': transitioning.value,
  }
})

const centerContainerClass = computed(() => {
  return {
    'hl-center': true,
    'hl-center--transitioning': transitioning.value,
  }
})

function safeLoadBestStreak(): number {
  try {
    const v = window.localStorage.getItem(BEST_STREAK_STORAGE_KEY)
    if (!v) return 0
    const n = Number.parseInt(v, 10)
    return Number.isFinite(n) && n > 0 ? n : 0
  } catch {
    return 0
  }
}

function safeSaveBestStreak(value: number): void {
  try {
    window.localStorage.setItem(BEST_STREAK_STORAGE_KEY, String(value))
  } catch {}
}

function updateBestIfNeeded(): void {
  if (currentStreak.value > bestStreak.value) {
    bestStreak.value = currentStreak.value
    safeSaveBestStreak(bestStreak.value)
  }
}

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
  const isClicked = clicked.value === side
  return {
    'hl-side': true,
    'hl-side--disabled': !canInteract.value,
    'hl-side--clickable': canInteract.value,
    'hl-side--clicked': isClicked,
    'hl-side--transitioning': transitioning.value,
  }
}

function cardClass(side: 'left' | 'right') {
  const isClicked = clicked.value === side
  const isCorrectPick = showResult.value && lastGuessCorrect.value === true && isClicked
  const isWrongPick = showResult.value && lastGuessCorrect.value === false && isClicked

  return {
    'hl-card': true,
    'hl-card--correct': isCorrectPick,
    'hl-card--wrong': isWrongPick,
  }
}

function equalClass() {
  return {
    'hl-equal': true,
    'hl-equal--disabled': !canInteract.value,
  }
}

function triggerNumberPop(side: 'left' | 'right'): void {
  if (side === 'left') {
    animateLeft.value = false
    requestAnimationFrame(() => {
      animateLeft.value = true
      window.setTimeout(() => {
        animateLeft.value = false
      }, 600)
    })
    return
  }

  animateRight.value = false
  requestAnimationFrame(() => {
    animateRight.value = true
    window.setTimeout(() => {
      animateRight.value = false
    }, 600)
  })
}

function resetRoundAnimations(): void {
  animatedThisRound.value = { left: false, right: false }
  animateLeft.value = false
  animateRight.value = false
}

function maybeAnimate(side: 'left' | 'right'): void {
  if (side === 'left') {
    if (animatedThisRound.value.left) return
    if (knownBeforePick.value.left) return
    animatedThisRound.value.left = true
    triggerNumberPop('left')
    return
  }

  if (animatedThisRound.value.right) return
  if (knownBeforePick.value.right) return
  animatedThisRound.value.right = true
  triggerNumberPop('right')
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
    showResult.value = false
    gameOver.value = false
    transitioning.value = false
    knownBeforePick.value = { left: false, right: false }
    resetRoundAnimations()
  } catch (e) {
    handleError(e)
  } finally {
    busy.value = false
  }
}

async function startGame() {
  if (busy.value) return
  if (!state.value) await loadSession()
  currentStreak.value = 0
  hasStarted.value = true
}

async function restartFromOverlay() {
  busy.value = true
  try {
    if (state.value?.session_id) {
      try {
        await endTrophiesHigherLower(state.value.session_id)
      } catch {}
    }
  } finally {
    busy.value = false
  }

  await loadSession()
  currentStreak.value = 0
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
  showResult.value = false
  transitioning.value = false
  resetRoundAnimations()

  knownBeforePick.value = {
    left: state.value.left.trophies_count !== null,
    right: state.value.right.trophies_count !== null,
  }

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
    showResult.value = true
  }, REVEAL_SECOND_DELAY + RESULT_AFTER_REVEAL_DELAY)

  window.setTimeout(() => {
    if (!res.correct || !res.next) {
      gameOver.value = true
      updateBestIfNeeded()
      busy.value = false
      return
    }

    currentStreak.value += 1
    updateBestIfNeeded()

    transitioning.value = true

    window.setTimeout(() => {
      state.value = res.next
      revealStage.value = 0
      clicked.value = null
      revealValues.value = { left: null, right: null }
      lastGuessCorrect.value = null
      showResult.value = false
      knownBeforePick.value = { left: false, right: false }
      resetRoundAnimations()
    }, TRANSITION_OUT_DURATION)

    window.setTimeout(() => {
      transitioning.value = false
      busy.value = false
    }, TRANSITION_OUT_DURATION + TRANSITION_IN_DURATION)
  }, REVEAL_SECOND_DELAY + RESULT_AFTER_REVEAL_DELAY + NEXT_AFTER_RESULT_DELAY)
}

watch(revealStage, (stage) => {
  if (!hasStarted.value) return
  if (stage === 1) {
    maybeAnimate(firstReveal.value)
    return
  }
  if (stage === 2) {
    maybeAnimate('left')
    maybeAnimate('right')
  }
})

onMounted(() => {
  bestStreak.value = safeLoadBestStreak()
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
          <div :class="cardClass('left')">
            <div class="hl-photo">
              <SimpleImg :img="state.left.image_url" :alt="state.left.name" />
            </div>

            <div class="hl-name">{{ state.left.name }}</div>

            <div class="hl-count hl-count--center">
              <div class="hl-count-value" :class="{ 'hl-count-value--reveal': animateLeft }">
                {{ trophiesText('left') }}
              </div>
              <div class="hl-count-label">trophées</div>
            </div>
          </div>
        </button>

        <div :class="centerContainerClass">
          <div :class="centerOrClass">{{ centerText }}</div>
          <button v-if="!showResult" type="button" :class="equalClass()" @click="onPick('equal')">ÉGAL</button>
        </div>

        <button type="button" :class="sideClass('right')" @click="onPick('right')">
          <div :class="cardClass('right')">
            <div class="hl-photo">
              <SimpleImg :img="state.right.image_url" :alt="state.right.name" />
            </div>

            <div class="hl-name">{{ state.right.name }}</div>

            <div class="hl-count hl-count--center">
              <div class="hl-count-value" :class="{ 'hl-count-value--reveal': animateRight }">
                {{ trophiesText('right') }}
              </div>
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

          <div class="hl-streaks">
            <div class="hl-streak">
              <div class="hl-streak-label">Streak</div>
              <div class="hl-streak-value">{{ currentStreak }}</div>
            </div>
            <div class="hl-streak">
              <div class="hl-streak-label">Meilleure streak</div>
              <div class="hl-streak-value">{{ bestStreak }}</div>
            </div>
          </div>

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
  border-radius: 16px;
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
  display: grid;
  grid-template-columns: 1fr auto 1fr;
  align-items: center;
  justify-items: center;
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
  transition: filter 220ms ease, transform 420ms ease, opacity 420ms ease;
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
  transition: box-shadow 260ms ease, transform 260ms ease, border-color 260ms ease, opacity 420ms ease;
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

.hl-card::after {
  content: '';
  position: absolute;
  inset: 0;
  background: radial-gradient(circle at 50% 55%, rgba(0, 0, 0, 0.05) 0%, rgba(0, 0, 0, 0.45) 68%);
  z-index: 1;
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
  background: rgba(0, 0, 0, 0.52);
  border: 1px solid rgba(255, 255, 255, 0.16);
  box-shadow: 0 18px 70px rgba(0, 0, 0, 0.60);
  backdrop-filter: blur(10px);
}

.hl-count--center {
  position: absolute;
  left: 50%;
  top: 52%;
  transform: translate(-50%, -50%);
  min-width: 140px;
  z-index: 4;
}

.hl-count-value {
  font-size: 44px;
  font-weight: 900;
  line-height: 1;
}

.hl-count-value--reveal {
  animation: hl-pop 560ms cubic-bezier(0.2, 0.9, 0.22, 1) both;
}

@keyframes hl-pop {
  0% {
    transform: translateY(10px) scale(0.86);
    opacity: 0;
    filter: blur(2px);
  }
  55% {
    transform: translateY(0px) scale(1.08);
    opacity: 1;
    filter: blur(0px);
  }
  100% {
    transform: translateY(0px) scale(1);
    opacity: 1;
  }
}

.hl-count-label {
  font-size: 12px;
  opacity: 0.88;
  margin-top: 4px;
  z-index: 4;
}

.hl-center {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 10px;
  pointer-events: none;
  transition: opacity 420ms ease, transform 420ms ease;
}

.hl-center--transitioning {
  opacity: 0;
  transform: scale(0.96);
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
  background: rgba(0, 0, 0, 0.70);
  border: 1px solid rgba(255, 255, 255, 0.22);
  box-shadow: 0 20px 80px rgba(0, 0, 0, 0.65);
  backdrop-filter: blur(10px);
  transition: transform 220ms ease, background 220ms ease, border-color 220ms ease, box-shadow 220ms ease;
}

.hl-or--result {
  transform: scale(1.03);
}

.hl-or--win {
  background: rgba(34, 197, 94, 0.22);
  border-color: rgba(34, 197, 94, 0.75);
  box-shadow: 0 0 0 10px rgba(34, 197, 94, 0.18), 0 22px 80px rgba(0, 0, 0, 0.6);
  animation: hl-bump 420ms cubic-bezier(0.2, 0.9, 0.22, 1) both;
}

.hl-or--lose {
  background: rgba(239, 68, 68, 0.22);
  border-color: rgba(239, 68, 68, 0.75);
  box-shadow: 0 0 0 10px rgba(239, 68, 68, 0.18), 0 22px 80px rgba(0, 0, 0, 0.6);
  animation: hl-bump 420ms cubic-bezier(0.2, 0.9, 0.22, 1) both;
}

@keyframes hl-bump {
  0% {
    transform: scale(0.92);
    opacity: 0.92;
  }
  55% {
    transform: scale(1.08);
    opacity: 1;
  }
  100% {
    transform: scale(1.03);
    opacity: 1;
  }
}

.hl-equal {
  pointer-events: auto;
  border-radius: 999px;
  padding: 10px 16px;
  font-weight: 900;
  cursor: pointer;
  background: rgba(255, 255, 255, 0.18);
  color: #f5f7ff;
  border: 1px solid rgba(255, 255, 255, 0.22);
  box-shadow: 0 20px 80px rgba(0, 0, 0, 0.55);
  backdrop-filter: blur(10px);
}

.hl-equal:hover {
  background: rgba(255, 255, 255, 0.26);
}

.hl-equal--disabled {
  cursor: not-allowed;
  opacity: 0.82;
}

.hl-card--correct {
  border: 3px solid rgba(34, 197, 94, 1);
  box-shadow: 0 0 0 8px rgba(34, 197, 94, 0.28), 0 26px 90px rgba(0, 0, 0, 0.60);
}

.hl-card--wrong {
  border: 3px solid rgba(239, 68, 68, 1);
  box-shadow: 0 0 0 8px rgba(239, 68, 68, 0.28), 0 26px 90px rgba(0, 0, 0, 0.60);
}

.hl-side--clicked .hl-card {
  transform: scale(1.01);
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

.hl-streaks {
  margin-top: 12px;
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
}

.hl-streak {
  padding: 10px 10px 9px;
  border-radius: 14px;
  background: rgba(255, 255, 255, 0.06);
  border: 1px solid rgba(255, 255, 255, 0.12);
}

.hl-streak-label {
  font-size: 12px;
  opacity: 0.85;
}

.hl-streak-value {
  margin-top: 4px;
  font-size: 22px;
  font-weight: 900;
}

.hl-gameover-btn {
  margin-top: 14px;
  width: 100%;
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
    grid-template-columns: 1fr;
    grid-template-rows: auto auto auto;
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
