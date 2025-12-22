<script setup lang="ts">
import { computed, ref } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { usePvpStore } from '@/stores/pvp'
import { useFlashStore } from '@/stores/flash'
import type { BestOf, PvpGame } from '@/types/pvp'
import { pvpLeaveQueue } from '@/api/pvpApi'
import SimpleImg from '@/components/SimpleImg.vue'

const auth = useAuthStore()
const pvp = usePvpStore()
const flash = useFlashStore()

const selectedGame = ref<PvpGame>('kcdle')
const selectedBestOf = ref<BestOf>(3)

const isAuth = computed(() => auth.isAuthenticated)
const isQueued = computed(() => pvp.isQueued)
const isInMatch = computed(() => pvp.isInMatch)

const queuedLabel = computed(() => {
  if (!pvp.queue) return ''
  return `${pvp.queue.game.toUpperCase()} (BO${pvp.queue.bestOf})`
})

function pickGame(game: PvpGame) {
  selectedGame.value = game
}

function queueUp() {
  if (!isAuth.value) {
    flash.warning('Connecte-toi pour lancer un PvP.', 'PvP')
    return
  }
  if (isInMatch.value) {
    flash.info('Tu as déjà un match en cours.', 'PvP')
    return
  }
  pvp.setQueued(selectedGame.value, selectedBestOf.value)
}

async function leaveQueue() {
  if (!pvp.queue) return
  const game = pvp.queue.game
  pvp.clearQueue()
  try {
    await pvpLeaveQueue(game)
  } catch {
  }
  flash.info('Tu as quitté la file PvP.', 'PvP')
}
</script>

<template>
  <div class="pvp-page">
    <header class="pvp-header">
      <div class="header-left">
        <h1>PvP</h1>
        <p>
          Lance une recherche sur un jeu. Tu peux continuer à naviguer pendant la file d’attente.
        </p>
      </div>
    </header>

    <main class="pvp-main">
      <section class="pvp-card">
        <div class="pvp-card-title">Choisir un jeu</div>

        <div class="game-grid">
          <button type="button" class="game-tile" :class="{ active: selectedGame === 'kcdle' }" @click="pickGame('kcdle')">
            <SimpleImg img="KCDLE_Barre.png" alt="KCDLE" />
          </button>
          <button type="button" class="game-tile" :class="{ active: selectedGame === 'lecdle' }" @click="pickGame('lecdle')">
            <SimpleImg img="LECDLE_Barre.png" alt="LECDLE" />
          </button>
          <button type="button" class="game-tile" :class="{ active: selectedGame === 'lfldle' }" @click="pickGame('lfldle')">
            <SimpleImg img="LFLDLE_Barre.png" alt="LFLDLE" />
          </button>
        </div>

        <div class="row">
          <label class="field">
            <span>Format</span>
            <select v-model="selectedBestOf" class="select">
              <option :value="1">BO1</option>
              <option :value="3">BO3</option>
              <option :value="5">BO5</option>
            </select>
          </label>

          <div class="actions">
            <button v-if="!isQueued" type="button" class="btn" :disabled="!isAuth || isInMatch" @click="queueUp">
              Rejoindre la file
            </button>
            <button v-else type="button" class="btn danger" @click="leaveQueue">
              Quitter la file
            </button>
          </div>
        </div>

        <div v-if="isQueued" class="hint">
          File en cours : <strong>{{ queuedLabel }}</strong>
        </div>

        <div v-else class="hint">
          Choisis un jeu + un format, puis “Rejoindre la file”.
        </div>

        <div v-if="!isAuth" class="hint warn">
          Tu dois être connecté pour jouer en PvP.
        </div>

        <div v-if="isInMatch" class="hint warn">
          Match en cours : tu seras redirigé automatiquement vers l’écran de match.
        </div>
      </section>
    </main>
  </div>
</template>

<style scoped>
.pvp-page {
  min-height: 100vh;
  padding: 20px 12px 28px;
  display: flex;
  flex-direction: column;
  align-items: center;
  color: #f3f3f3;
  background: radial-gradient(circle at top, #20263a 0, #05060a 75%);
}

.pvp-header {
  width: 100%;
  gap: 10px;
  max-width: 900px;
  display: flex;
  justify-content: space-between;
  margin-bottom: 18px;
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

.pvp-main {
  width: 100%;
  max-width: 900px;
}

.pvp-card {
  width: 100%;
  background: rgba(6, 8, 18, 0.92);
  border-radius: 14px;
  padding: 16px 12px 18px;
  box-shadow: 0 12px 28px rgba(0, 0, 0, 0.6);
  border: 1px solid rgba(255, 255, 255, 0.06);
  text-align: start;
}

.pvp-card-title {
  font-size: 0.9rem;
  opacity: 0.85;
  margin-bottom: 12px;
  text-transform: uppercase;
  letter-spacing: 0.12em;
}

.game-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 10px;
  margin-bottom: 14px;
}

.game-tile {
  border: 1px solid rgba(255, 255, 255, 0.10);
  background: rgba(255, 255, 255, 0.05);
  border-radius: 12px;
  padding: 10px;
  cursor: pointer;
  transition: transform 0.12s ease, background 0.12s ease, border-color 0.12s ease;
}

.game-tile img {
  width: 100%;
  max-width: 520px;
  height: auto;
  display: block;
  margin: 0 auto;
}

.game-tile:hover {
  transform: translateY(-1px);
  background: rgba(255, 255, 255, 0.08);
}

.game-tile.active {
  border-color: rgba(0, 166, 255, 0.55);
  box-shadow: 0 0 18px rgba(0, 166, 255, 0.25);
}

.row {
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
  align-items: flex-end;
  justify-content: space-between;
}

.field {
  display: flex;
  flex-direction: column;
  gap: 6px;
  min-width: 180px;
}

.field span {
  font-size: 0.85rem;
  opacity: 0.85;
}

.select {
  padding: 8px 10px;
  border-radius: 10px;
  border: 1px solid rgba(255, 255, 255, 0.14);
  background: rgba(10, 12, 20, 0.98);
  color: #f3f3f3;
  font-size: 0.95rem;
  outline: none;
}

.actions {
  display: flex;
  gap: 10px;
  align-items: center;
  margin-left: auto;
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

.btn:hover:not(:disabled) {
  transform: translateY(-1px);
  background: rgba(255, 255, 255, 0.14);
}

.btn:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

.btn.danger {
  background: rgba(255, 66, 66, 0.18);
  border-color: rgba(255, 66, 66, 0.35);
}

.btn.danger:hover {
  background: rgba(255, 66, 66, 0.28);
}

.hint {
  margin-top: 12px;
  font-size: 0.9rem;
  opacity: 0.9;
}

.hint.warn {
  color: #ffd28a;
  opacity: 0.95;
}

@media (min-width: 720px) {
  .game-grid {
    grid-template-columns: 1fr 1fr;
  }
}

@media (min-width: 920px) {
  .game-grid {
    grid-template-columns: 1fr 1fr 1fr;
  }
}
</style>
