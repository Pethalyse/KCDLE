<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { usePvpStore } from '@/stores/pvp'
import { useFlashStore } from '@/stores/flash'
import type { BestOf, PvpGame } from '@/types/pvp'
import {
  pvpCloseLobby,
  pvpCreateLobby,
  pvpJoinLobbyByCode,
  pvpLeaveLobby,
  pvpLeaveQueue,
  pvpPeekLobbyByCode,
  pvpStartLobby,
} from '@/api/pvpApi'
import SimpleImg from '@/components/SimpleImg.vue'
import PvpRoundsHelp from '@/components/pvp/PvpRoundsHelp.vue'
import { handleError } from '@/utils/handleError'
import { useRoute, useRouter } from 'vue-router'
import AdSlot from "@/components/AdSlot.vue";

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const pvp = usePvpStore()
const flash = useFlashStore()

const selectedGame = ref<PvpGame>((route.query.game as PvpGame) ?? 'kcdle')
const selectedBestOf = ref<BestOf>(route.query.bo ? (Number(route.query.bo) as BestOf) : 3)

const isAuth = computed(() => auth.isAuthenticated)
const isQueued = computed(() => pvp.isQueued)
const isInMatch = computed(() => pvp.isInMatch)
const isInLobby = computed(() => pvp.isInLobby)
const lobby = computed(() => pvp.lobby)

const lobbyCodeInput = ref('')
const lobbyPeek = ref<any | null>(null)
const lobbyLoading = ref(false)
const lobbyActionLoading = ref(false)

const queueDisabledBecauseLobby = computed(() => isInLobby.value)

const queuedLabel = computed(() => {
  if (!pvp.queue) return ''
  return `${pvp.queue.game.toUpperCase()} (BO${pvp.queue.bestOf})`
})

const inviteCode = computed(() => {
  const c = route.query.code
  if (typeof c !== 'string') return null
  const code = c.replace(/\s+/g, '').toUpperCase().slice(0, 8)
  return code.length === 8 ? code : null
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
  if (isInLobby.value) {
    flash.info('Tu es déjà dans un lobby privé.', 'PvP')
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

async function createLobby() {
  if (!isAuth.value) {
    flash.warning('Connecte-toi pour créer un lobby.', 'PvP')
    return
  }
  if (isInMatch.value) {
    flash.info('Tu as déjà un match en cours.', 'PvP')
    return
  }
  lobbyLoading.value = true
  try {
    if (pvp.queue) {
      const g = pvp.queue.game
      pvp.clearQueue()
      try { await pvpLeaveQueue(g) } catch {}
    }
    const res = await pvpCreateLobby(selectedGame.value, selectedBestOf.value)
    pvp.setLobby(res)
    flash.success(`Lobby créé : ${res.code}`, 'PvP')
  } catch (e) {
    handleError(e, 'Impossible de créer le lobby')
  } finally {
    lobbyLoading.value = false
  }
}

async function joinLobby() {
  if (!isAuth.value) {
    flash.warning('Connecte-toi pour rejoindre un lobby.', 'PvP')
    return
  }
  if (isInMatch.value) {
    flash.info('Tu as déjà un match en cours.', 'PvP')
    return
  }
  const code = lobbyCodeInput.value.trim().toUpperCase()
  if (code.length !== 8) {
    flash.warning('Code invalide.', 'PvP')
    return
  }
  lobbyLoading.value = true
  try {
    if (pvp.queue) {
      const g = pvp.queue.game
      pvp.clearQueue()
      try { await pvpLeaveQueue(g) } catch {}
    }
    const res = await pvpJoinLobbyByCode(code)
    pvp.setLobby(res)
    flash.success('Lobby rejoint.', 'PvP')
  } catch (e) {
    handleError(e, 'Impossible de rejoindre le lobby')
  } finally {
    lobbyLoading.value = false
  }
}

async function leaveOrCloseLobby() {
  if (!lobby.value) return
  lobbyActionLoading.value = true
  try {
    if (lobby.value.is_host) {
      await pvpCloseLobby(lobby.value.id)
      pvp.clearLobby()
      flash.info('Lobby fermé.', 'PvP')
    } else {
      await pvpLeaveLobby(lobby.value.id)
      pvp.clearLobby()
      flash.info('Lobby quitté.', 'PvP')
    }
  } catch (e) {
    handleError(e, 'Impossible de quitter le lobby')
  } finally {
    lobbyActionLoading.value = false
  }
}

async function startLobbyMatch() {
  if (!lobby.value) return
  if (!lobby.value.is_host) return
  if (!lobby.value.guest) {
    flash.warning('Attends qu’un joueur rejoigne le lobby.', 'PvP')
    return
  }
  lobbyActionLoading.value = true
  try {
    const res = await pvpStartLobby(lobby.value.id)
    pvp.clearLobby()
    pvp.setMatch(res.match_id)
    flash.success('Match lancé.', 'PvP')
    router.push({ name: 'pvp_match', params: { matchId: res.match_id } }).then()
  } catch (e) {
    handleError(e, 'Impossible de lancer le match')
  } finally {
    lobbyActionLoading.value = false
  }
}

async function copyLobbyCode() {
  if (!lobby.value) return
  try {
    await navigator.clipboard.writeText(lobby.value.code)
    flash.success('Code copié.', 'PvP')
  } catch {
    flash.info(`Code : ${lobby.value.code}`, 'PvP')
  }
}

async function refreshPeek(code: string) {
  if (code.length !== 8) {
    lobbyPeek.value = null
    return
  }
  try {
    lobbyPeek.value = await pvpPeekLobbyByCode(code)
  } catch {
    lobbyPeek.value = null
  }
}

watch(
  () => lobbyCodeInput.value,
  (v) => {
    const code = v.replace(/\s+/g, '').toUpperCase().slice(0, 8)
    if (code !== v) lobbyCodeInput.value = code
    refreshPeek(code).then()
  },
)

watch(
  () => inviteCode.value,
  async (code) => {
    if (!code) return
    if (!isInLobby.value) {
      lobbyCodeInput.value = code
    }
  },
  { immediate: true },
)


onMounted(() => {
  if (pvp.isInLobby) {
    lobbyCodeInput.value = ''
    lobbyPeek.value = null
  }
})
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

      <div class="header-right">
        <PvpRoundsHelp button-variant="ghost" />
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
            <button v-if="!isQueued" type="button" class="btn" :disabled="!isAuth || isInMatch || queueDisabledBecauseLobby" @click="queueUp">
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

        <div v-if="queueDisabledBecauseLobby" class="hint warn">
          Tu es dans un lobby privé : la file est désactivée.
        </div>

        <div v-if="isInMatch" class="hint warn">
          Match en cours : tu seras redirigé automatiquement vers l’écran de match.
        </div>
      </section>

      <div class="lobby-ad">
        <AdSlot id="pvp-banner-1" kind="banner" />
      </div>

      <section class="pvp-card lobby-card">
        <div class="pvp-card-title">Lobby privé</div>

        <div v-if="!isAuth" class="hint warn">
          Connecte-toi pour créer ou rejoindre un lobby.
        </div>

        <template v-else>
          <div v-if="!isInLobby" class="lobby-grid">
            <div class="lobby-div">
              <div class="lobby-panel">
                <div class="lobby-title">Créer</div>
                <div class="lobby-sub">Même jeu et format que ta sélection ci-dessus.</div>
                <button type="button" class="btn" :disabled="lobbyLoading || isInMatch" @click="createLobby">
                  Créer un lobby
                </button>
              </div>
              <div class="lobby-panel">
                <div class="lobby-title">Rejoindre</div>
                <div class="lobby-sub">Entre un code de 8 caractères.</div>

                <div class="code-row">
                  <input v-model="lobbyCodeInput" class="code-input" placeholder="ABCDEFGH" maxlength="8" @keyup.enter="joinLobby"/>
                  <button type="button" class="btn" :disabled="lobbyLoading || lobbyCodeInput.length !== 8 || isInMatch" @click="joinLobby">
                    Rejoindre
                  </button>
                </div>

                <div v-if="lobbyPeek" class="peek">
                  <div class="peek-title">Lobby de {{ lobbyPeek.host.name }}</div>
                  <div class="peek-meta">{{ lobbyPeek.game.toUpperCase() }} · BO{{ lobbyPeek.best_of }}</div>
                </div>
                <div v-else-if="lobbyCodeInput.length === 8" class="peek peek--empty">Aperçu indisponible.</div>
              </div>
            </div>
            <div class="lobby-ad">
              <AdSlot id="pvp-inline-1" kind="inline" />
            </div>
          </div>

          <div v-else class="lobby-wrap">
            <div class="lobby-hero">
              <div class="hero-left">
                <div class="lobby-badge">Lobby actif</div>
                <div class="lobby-code">
                  <span class="code">{{ lobby?.code }}</span>
                  <button type="button" class="btn tiny" @click="copyLobbyCode">Copier</button>
                </div>
                <div class="lobby-meta">{{ lobby?.game.toUpperCase() }} · BO{{ lobby?.best_of }}</div>
              </div>
              <div class="hero-right">
                <div class="slot" :class="{ ready: true }">
                  <div class="slot-label">Host</div>
                  <div class="slot-name">{{ lobby?.host.name }}</div>
                </div>
                <div class="slot" :class="{ ready: !!lobby?.guest }">
                  <div class="slot-label">Invité</div>
                  <div v-if="lobby?.guest" class="slot-name">{{ lobby.guest.name }}</div>
                  <div v-else class="slot-wait">En attente…</div>
                </div>
              </div>

            </div>

            <div class="lobby-actions">
              <button v-if="lobby?.is_host" type="button" class="btn" :disabled="lobbyActionLoading || !lobby?.guest" @click="startLobbyMatch">
                Lancer le match
              </button>
              <button type="button" class="btn danger" :disabled="lobbyActionLoading" @click="leaveOrCloseLobby">
                {{ lobby?.is_host ? 'Fermer le lobby' : 'Quitter le lobby' }}
              </button>
            </div>

            <div class="hint" v-if="lobby?.is_host && !lobby?.guest">Partage le code pour inviter quelqu’un.</div>
            <div class="hint" v-else-if="!lobby?.is_host">Attends que l’host lance le match.</div>
            <div class="lobby-ad">
              <AdSlot id="pvp-banner-2" kind="banner" />
            </div>
          </div>
        </template>
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

.header-right {
  display: flex;
  align-items: flex-start;
}

.pvp-main {
  width: 100%;
  max-width: 900px;
}

.pvp-card {
  background: rgba(6, 8, 18, 0.92);
  border-radius: 14px;
  padding: 16px 12px 18px;
  box-shadow: 0 12px 28px rgba(0, 0, 0, 0.6);
  border: 1px solid rgba(255, 255, 255, 0.06);
  text-align: start;
}

.lobby-card {
  margin-top: 14px;
}

.lobby-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 12px;
}

.lobby-panel {
  padding: 12px;
  border-radius: 12px;
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.08);
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.lobby-title {
  font-size: 1.05rem;
  font-weight: 700;
}

.lobby-sub {
  font-size: 0.9rem;
  opacity: 0.8;
}

.code-row {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
  align-items: center;
}

.code-input {
  flex: 1 1 160px;
  padding: 10px 12px;
  border-radius: 10px;
  border: 1px solid rgba(255, 255, 255, 0.14);
  background: rgba(10, 12, 20, 0.98);
  color: #f3f3f3;
  font-size: 1rem;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  outline: none;
}

.peek {
  padding: 10px 12px;
  border-radius: 12px;
  background: rgba(0, 166, 255, 0.08);
  border: 1px solid rgba(0, 166, 255, 0.20);
}

.peek--empty {
  background: rgba(255, 255, 255, 0.04);
  border-color: rgba(255, 255, 255, 0.08);
  opacity: 0.75;
}

.peek-title {
  font-weight: 700;
  margin-bottom: 2px;
}

.peek-meta {
  font-size: 0.9rem;
  opacity: 0.85;
}

.lobby-div {
  gap: 14px;
  display: flex;
  flex-direction: column;
}

.lobby-wrap {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.lobby-hero {
  border-radius: 14px;
  padding: 14px;
  border: 1px solid rgba(255, 255, 255, 0.10);
  background: linear-gradient(135deg, rgba(0, 166, 255, 0.10), rgba(255, 255, 255, 0.04));
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.lobby-badge {
  display: inline-flex;
  width: fit-content;
  padding: 4px 8px;
  border-radius: 999px;
  font-size: 0.75rem;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  background: rgba(0, 166, 255, 0.18);
  border: 1px solid rgba(0, 166, 255, 0.35);
}

.lobby-code {
  display: flex;
  gap: 10px;
  align-items: center;
  margin-top: 6px;
}

.lobby-code .code {
  font-size: 1.35rem;
  font-weight: 800;
  letter-spacing: 0.12em;
}

.lobby-meta {
  margin-top: 6px;
  font-size: 0.92rem;
  opacity: 0.85;
}

.hero-right {
  display: grid;
  grid-template-columns: 1fr;
  gap: 10px;
}

.slot {
  padding: 10px 12px;
  border-radius: 12px;
  border: 1px solid rgba(255, 255, 255, 0.10);
  background: rgba(0, 0, 0, 0.18);
}

.slot.ready {
  border-color: rgba(120, 255, 160, 0.30);
  background: rgba(120, 255, 160, 0.05);
}

.slot-label {
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.12em;
  opacity: 0.75;
}

.slot-name {
  margin-top: 4px;
  font-weight: 700;
}

.slot-wait {
  margin-top: 4px;
  opacity: 0.8;
}

.lobby-actions {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}

.btn.tiny {
  padding: 7px 10px;
  border-radius: 10px;
  font-size: 0.85rem;
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

.lobby-ad {
  margin-top: 2px;
  display: flex;
  justify-content: center;
}

@media (min-width: 720px) {
  .game-grid {
    grid-template-columns: 1fr 1fr;
  }

  .lobby-grid {
    grid-template-columns: 1fr 1fr;
  }

  .lobby-hero {
    flex-direction: row;
    justify-content: space-between;
    align-items: stretch;
  }

  .hero-right {
    grid-template-columns: 1fr 1fr;
    min-width: 340px;
  }
}

@media (min-width: 920px) {
  .game-grid {
    grid-template-columns: 1fr 1fr 1fr;
  }
}
</style>
