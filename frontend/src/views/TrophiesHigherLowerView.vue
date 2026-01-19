<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import SimpleImg from '@/components/SimpleImg.vue'
import { startTrophiesHigherLower, guessTrophiesHigherLower, endTrophiesHigherLower } from '@/api/trophiesHigherLowerApi'
import type { HigherLowerGuessResponse, HigherLowerSide, HigherLowerState } from '@/types/trophiesHigherLower'
import { handleError } from '@/utils/handleError'

const state = ref<HigherLowerState | null>(null)
const busy = ref(false)

const revealStep = ref<0 | 1 | 2>(0)
const clickedSide = ref<HigherLowerSide | null>(null)
const revealValues = ref<{ left: number | null; right: number | null }>({ left: null, right: null })
const lastGuessCorrect = ref<boolean | null>(null)
const gameOver = ref(false)

const score = computed(() => state.value?.score ?? 0)
const round = computed(() => state.value?.round ?? 1)

const leftKnown = computed(() => state.value?.left.trophies_count !== null)

function sideIsRevealed(side: HigherLowerSide): boolean {
  if (revealStep.value === 2) return true
  if (revealStep.value === 1 && clickedSide.value === side) return true
  return false
}

function trophiesText(side: HigherLowerSide): string {
  const s = state.value
  if (!s) return '—'

  if (sideIsRevealed(side)) {
    const v = side === 'left' ? revealValues.value.left : revealValues.value.right
    return v === null ? '—' : `${v}`
  }

  const known = side === 'left' ? s.left.trophies_count : s.right.trophies_count
  if (known !== null) return `${known}`

  return '??'
}

function trophiesLabel(): string {
  return 'trophées'
}

function panelClass(side: HigherLowerSide): Record<string, boolean> {
  return {
    'hl-panel': true,
    'hl-panel--clickable': !busy.value && !gameOver.value,
    'hl-panel--disabled': busy.value || gameOver.value,
    'hl-panel--correct': revealStep.value === 2 && lastGuessCorrect.value === true && clickedSide.value === side,
    'hl-panel--wrong': revealStep.value === 2 && lastGuessCorrect.value === false && clickedSide.value === side,
  }
}

async function startGame() {
  busy.value = true
  try {
    if (state.value?.session_id) {
      try {
        await endTrophiesHigherLower(state.value.session_id)
      } catch (e) {
      }
    }

    const data = await startTrophiesHigherLower()
    state.value = data
    revealStep.value = 0
    clickedSide.value = null
    revealValues.value = { left: null, right: null }
    lastGuessCorrect.value = null
    gameOver.value = false
  } catch (e) {
    handleError(e)
  } finally {
    busy.value = false
  }
}

async function onPick(side: HigherLowerSide) {
  if (!state.value) return
  if (busy.value) return
  if (gameOver.value) return

  busy.value = true
  revealStep.value = 0
  clickedSide.value = side
  lastGuessCorrect.value = null

  let res: HigherLowerGuessResponse
  try {
    res = await guessTrophiesHigherLower(state.value.session_id, side)
  } catch (e) {
    handleError(e)
    busy.value = false
    return
  }

  revealValues.value = {
    left: res.reveal.left,
    right: res.reveal.right,
  }

  lastGuessCorrect.value = res.correct

  window.setTimeout(() => {
    revealStep.value = 1
  }, 250)

  window.setTimeout(() => {
    revealStep.value = 2
  }, 950)

  window.setTimeout(() => {
    if (res.correct && res.next) {
      state.value = res.next
      revealStep.value = 0
      clickedSide.value = null
      revealValues.value = { left: null, right: null }
      lastGuessCorrect.value = null
      gameOver.value = false
      busy.value = false
      return
    }

    gameOver.value = true
    busy.value = false
  }, 1750)
}

onMounted(() => {
  void startGame()
})
</script>

<template>
  <div class="hl-wrapper" v-if="state">
    <div class="hl-head">
      <div class="hl-title">Trophées : plus ou moins</div>
      <div class="hl-sub">
        Clique sur le joueur qui a le plus de trophées.
        <span v-if="leftKnown" class="hl-hint">Le joueur de gauche garde son score visible.</span>
      </div>

      <div class="hl-stats">
        <div class="hl-stat">
          <div class="hl-stat-label">Score</div>
          <div class="hl-stat-value">{{ score }}</div>
        </div>
        <div class="hl-stat">
          <div class="hl-stat-label">Round</div>
          <div class="hl-stat-value">{{ round }}</div>
        </div>
      </div>
    </div>

    <div class="hl-grid" :key="state.session_id + '-' + state.round">
      <button type="button" :class="panelClass('left')" @click="onPick('left')">
        <div class="hl-img">
          <SimpleImg :img="state.left.image_url" :alt="state.left.name" />
        </div>
        <div class="hl-name">{{ state.left.name }}</div>
        <div class="hl-trophies">
          <div class="hl-trophies-value">{{ trophiesText('left') }}</div>
          <div class="hl-trophies-label">{{ trophiesLabel() }}</div>
        </div>
      </button>

      <div class="hl-vs">VS</div>

      <button type="button" :class="panelClass('right')" @click="onPick('right')">
        <div class="hl-img">
          <SimpleImg :img="state.right.image_url" :alt="state.right.name" />
        </div>
        <div class="hl-name">{{ state.right.name }}</div>
        <div class="hl-trophies">
          <div class="hl-trophies-value">{{ trophiesText('right') }}</div>
          <div class="hl-trophies-label">{{ trophiesLabel() }}</div>
        </div>
      </button>
    </div>

    <div v-if="gameOver" class="hl-gameover">
      <div class="hl-gameover-card">
        <div class="hl-gameover-title">Perdu</div>
        <div class="hl-gameover-sub">Score final : <strong>{{ score }}</strong></div>
        <button type="button" class="hl-retry" @click="startGame">Rejouer</button>
      </div>
    </div>
  </div>

  <div class="hl-wrapper" v-else>
    <div class="hl-loading">Chargement…</div>
  </div>
</template>

<style scoped>
.hl-wrapper {
  width: min(1100px, 92vw);
  margin: 0 auto;
  padding: 18px 0 40px;
}

.hl-head {
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin-bottom: 18px;
}

.hl-title {
  font-size: 28px;
  font-weight: 800;
}

.hl-sub {
  opacity: 0.9;
  line-height: 1.4;
}

.hl-hint {
  display: inline-block;
  margin-left: 8px;
  opacity: 0.85;
}

.hl-stats {
  display: flex;
  gap: 10px;
}

.hl-stat {
  background: rgba(255, 255, 255, 0.08);
  border: 1px solid rgba(255, 255, 255, 0.12);
  border-radius: 14px;
  padding: 10px 12px;
  min-width: 120px;
}

.hl-stat-label {
  font-size: 12px;
  opacity: 0.85;
}

.hl-stat-value {
  font-size: 20px;
  font-weight: 800;
  margin-top: 2px;
}

.hl-grid {
  display: grid;
  grid-template-columns: 1fr auto 1fr;
  gap: 14px;
  align-items: stretch;
}

.hl-vs {
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 900;
  letter-spacing: 0.08em;
  opacity: 0.8;
}

.hl-panel {
  appearance: none;
  border: 1px solid rgba(255, 255, 255, 0.14);
  border-radius: 18px;
  background: rgba(0, 0, 0, 0.25);
  padding: 14px;
  color: inherit;
  text-align: center;
  display: flex;
  flex-direction: column;
  gap: 10px;
  cursor: pointer;
  transition: transform 140ms ease, border-color 140ms ease, background 140ms ease;
}

.hl-panel--clickable:hover {
  transform: translateY(-2px);
  border-color: rgba(255, 255, 255, 0.25);
  background: rgba(0, 0, 0, 0.32);
}

.hl-panel--disabled {
  cursor: not-allowed;
  opacity: 0.85;
}

.hl-panel--correct {
  border-color: rgba(34, 197, 94, 0.55);
  background: rgba(34, 197, 94, 0.12);
}

.hl-panel--wrong {
  border-color: rgba(239, 68, 68, 0.55);
  background: rgba(239, 68, 68, 0.12);
}

.hl-img {
  width: 100%;
  display: flex;
  justify-content: center;
}

.hl-img img {
  width: 100%;
  max-width: 240px;
  aspect-ratio: 1 / 1;
  object-fit: cover;
  border-radius: 16px;
  border: 1px solid rgba(255, 255, 255, 0.14);
}

.hl-name {
  font-size: 20px;
  font-weight: 800;
}

.hl-trophies {
  margin-top: auto;
  padding-top: 6px;
}

.hl-trophies-value {
  font-size: 34px;
  font-weight: 900;
  line-height: 1;
}

.hl-trophies-label {
  font-size: 12px;
  opacity: 0.85;
  margin-top: 4px;
}

.hl-gameover {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.55);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 60;
}

.hl-gameover-card {
  width: min(420px, 90vw);
  padding: 18px;
  border-radius: 18px;
  background: rgba(0, 0, 0, 0.85);
  border: 1px solid rgba(255, 255, 255, 0.16);
}

.hl-gameover-title {
  font-size: 22px;
  font-weight: 900;
}

.hl-gameover-sub {
  margin-top: 6px;
  opacity: 0.9;
}

.hl-retry {
  margin-top: 14px;
  width: 100%;
  border: 0;
  border-radius: 14px;
  padding: 12px 14px;
  font-weight: 900;
  cursor: pointer;
}

.hl-loading {
  padding: 30px 0;
  opacity: 0.85;
}

@media (max-width: 780px) {
  .hl-grid {
    grid-template-columns: 1fr;
  }

  .hl-vs {
    display: none;
  }
}
</style>
