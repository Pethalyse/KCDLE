<script setup lang="ts">
import { onMounted, ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/api'
import SimpleImg from '@/components/SimpleImg.vue'
import SearchBar from '@/components/SearchBar.vue'
import PlayerTab from '@/components/PlayerTab.vue'
import PopupGg from '@/components/PopupGg.vue'
import { trackEvent } from '@/analytics.ts'
import AdSlot from '@/components/AdSlot.vue'

import { useAuthStore } from '@/stores/auth'
import { sendGuess, fetchTodayGuessState } from '@/api/gameGuessApi'
import type { GameCode, GuessResponse, StoredGuess, TodayGuessResult } from '@/types/gameGuess'
import { useFlashStore } from '@/stores/flash.ts'

const props = defineProps<{
  game: GameCode
}>()

const router = useRouter()
const flash = useFlashStore()

const daily = ref<any | null>(null)
const joueurs = ref<any[]>([])
const guesses = ref<StoredGuess[]>([])

const loading = ref(true)
const error = ref<string | null>(null)

const dleCode = computed(
  () => props.game.toUpperCase() as 'KCDLE' | 'LECDLE' | 'LFLDLE',
)

const storageKey = computed(() => dleCode.value)
const winKey = computed(() => `${dleCode.value}_win`)
const lastClearKey = computed(() => `${dleCode.value}_lastClearTime`)

const hasWon = computed(() => guesses.value.some(g => g.correct === true))
const wonData = ref<GuessResponse>()
const wonDataAnimationFinished = ref<boolean>(false)

const ggPopupOpen = ref(false)

function clearLocalStorageDaily(): boolean {
  const now = new Date()
  const todayLocal = new Date(
    now.getFullYear(),
    now.getMonth(),
    now.getDate(),
  ).getTime()

  const lastClearLocal = parseInt(
    localStorage.getItem(lastClearKey.value) || '0',
    10,
  )

  if (lastClearLocal < todayLocal) {
    localStorage.removeItem(storageKey.value)
    localStorage.removeItem(winKey.value)
    localStorage.setItem(lastClearKey.value, todayLocal.toString())
    return true
  }

  return false
}

function saveGuessesToStorage() {
  try {
    localStorage.setItem(storageKey.value, JSON.stringify(guesses.value))
  } catch (e) {
    console.error('Erreur lors de la sauvegarde des guesses dans le localStorage :', e)
  }
}

function restoreGuessesFromStorage() {
  const stored = localStorage.getItem(storageKey.value)
  if (!stored) return

  try {
    const parsed = JSON.parse(stored)
    if (Array.isArray(parsed)) {
      guesses.value = parsed
    }
  } catch (e) {
    console.error('Erreur lors de la restauration des guesses depuis le localStorage :', e)
  }
}

async function loadDaily() {
  const { data } = await api.get(`/games/${props.game}/daily`)
  daily.value = data
}

async function loadPlayers() {
  const { data } = await api.get(`/games/${props.game}/players`, {
    params: { active: 1 },
  })
  joueurs.value = data.players ?? []
}

onMounted(async () => {
  try {
    loading.value = true

    clearLocalStorageDaily()

    await Promise.all([loadDaily(), loadPlayers()])

    const auth = useAuthStore()
    if (auth.isAuthenticated) {
      try {
        const today: TodayGuessResult = await fetchTodayGuessState(props.game)

        if (today.has_result) {
          const playersById = new Map<number, any>(
            joueurs.value.map((p: any) => [p.id, p]),
          )

          guesses.value = today.guesses.map(entry => ({
            player_id: entry.player_id,
            correct: entry.correct,
            comparison: entry.comparison,
            stats: entry.stats,
            player: playersById.get(entry.player_id) ?? { id: entry.player_id },
          }))

          saveGuessesToStorage()
          return
        }
      } catch (e) {
        console.error(
          'Erreur lors du chargement des guesses depuis l’API :',
          e,
        )
      }
    }

    restoreGuessesFromStorage()
  } catch (e: any) {
    console.error(e)
    error.value = e?.message ?? 'Erreur de chargement'
  } finally {
    loading.value = false
  }
})

function goHome() {
  router.push({ name: 'home' })
}

const nbTrouveText = computed(() => {
  if (!daily.value) return ''
  return daily.value.solvers_count === 0
    ? "Personne n'a encore trouvé"
    : daily.value.solvers_count <= 1
      ? `${daily.value.solvers_count} personne a déjà trouvé`
      : `${daily.value.solvers_count ?? 0} personnes ont déjà trouvé !`
})

function handleClickCard(joueurWrapper: any) {
  if (!joueurWrapper?.id) return
  void makeGuess(joueurWrapper)
}

async function makeGuess(joueurWrapper: any) {
  if (!daily.value) return

  if (clearLocalStorageDaily()) {
    window.location.reload()
    return
  }

  const currentGuessCount = guesses.value.length + 1

  wonDataAnimationFinished.value = false
  ggPopupOpen.value = false

  const data = await sendGuess(props.game, joueurWrapper.id, currentGuessCount)

  const guess: StoredGuess = {
    player_id: joueurWrapper.id,
    correct: data.correct,
    comparison: data.comparison,
    stats: data.stats,
    player: joueurWrapper,
  }

  guesses.value.unshift(guess)

  if (data.correct === true) {
    wonData.value = data
  }

  if (daily.value && data.stats) {
    daily.value.solvers_count = data.stats.solvers_count
    daily.value.total_guesses = data.stats.total_guesses
    daily.value.average_guesses = data.stats.average_guesses
  }

  saveGuessesToStorage()
}

function handleEndTabPlayerAnimation() {
  if (!hasWon.value) return

  const data = wonData.value
  if (data?.correct) {
    if (Array.isArray(data.unlocked_achievements) && data.unlocked_achievements.length > 0) {
      data.unlocked_achievements.forEach((achievement: any) => {
        if (!achievement || !achievement.name) return

        flash.push(
          'success',
          achievement.name,
          'Succès débloqué',
        )
      })
    }

    try {
      localStorage.setItem(winKey.value, 'true')
      trackEvent('dle_win', {
        game: dleCode.value,
        tries: data.stats.solvers_count,
        date: new Date(),
      })
    } catch (e) {
      console.error('Erreur lors de la sauvegarde du flag de victoire :', e)
    }
  }

  wonDataAnimationFinished.value = true
  ggPopupOpen.value = true
}

const guessedIds = computed<number[]>(() =>
  guesses.value
    .map(g => g.player?.id)
    .filter((id): id is number => typeof id === 'number'),
)
</script>

<template>
  <div class="dle-page" :class="dleCode">
    <header :class="'header_' + dleCode">
      <div class="btn-home">
        <SimpleImg
          class="logo"
          :alt="dleCode"
          :img="dleCode + '_page_Logo.png'"
          @onclick="goHome"
        />
        <div id="nbTrouve">
          {{ nbTrouveText }}
        </div>
      </div>

      <div v-if="hasWon && wonDataAnimationFinished" class="gg-open-wrapper">
        <button type="button" class="gg-open-btn" @click="ggPopupOpen = true">
          Résultats
        </button>
      </div>

      <SearchBar
        v-if="!hasWon || !wonDataAnimationFinished"
        class="containt-name"
        :dle="dleCode"
        :joueurs="joueurs"
        :unwrittable="hasWon"
        :guessed-ids="guessedIds"
        @click_card="handleClickCard"
      />
    </header>

    <div
      class="dle_body"
      :class="dleCode"
    >
      <div v-if="loading">
        Chargement...
      </div>
      <div v-else-if="error">
        Erreur : {{ error }}
      </div>
      <template v-else>
        <PlayerTab
          :game="game"
          :guesses="guesses"
          @endAnimaiton="handleEndTabPlayerAnimation"
        />

        <section class="dle-ad-under-grid">
          <AdSlot id="dle-under-grid-1" kind="banner" />
        </section>

        <PopupGg
          v-if="hasWon && wonDataAnimationFinished"
          :dle-code="dleCode"
          :guesses="guesses"
          :open="ggPopupOpen"
          @close="ggPopupOpen = false"
        />
      </template>
    </div>
  </div>
</template>

<style scoped>
.dle-page {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
}

.dle_body {
  padding: 12px 8px 24px;
}

.dle-ad-under-grid {
  margin: 12px 0 8px;
}

.gg-open-wrapper {
  display: flex;
  justify-content: center;
  margin-top: 6px;
}

.gg-open-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 10px 14px;
  border-radius: 999px;
  border: 1px solid var(--dle-accent-pill-border, rgba(255, 255, 255, 0.25));
  background: var(--dle-accent-pill, rgba(255, 255, 255, 0.08));
  color: rgba(255, 255, 255, 0.95);
  font-weight: 800;
  cursor: pointer;
  transition: transform 120ms ease, background 120ms ease;
}

.gg-open-btn:hover {
  transform: translateY(-1px);
  background: rgba(255, 255, 255, 0.14);
}
</style>
