<script setup lang="ts">
import { onMounted, ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/api'
import SimpleImg from '@/components/SimpleImg.vue'
import SearchBar from '@/components/SearchBar.vue'
import PlayerTab from '@/components/PlayerTab.vue'
import Credit from '@/components/Credit.vue'
import PopupGg from '@/components/PopupGg.vue'

type GameCode = 'kcdle' | 'lecdle' | 'lfldle'

const props = defineProps<{
  game: GameCode
}>()

const router = useRouter()

const daily = ref<any | null>(null)
const joueurs = ref<any[]>([])
const guesses = ref<any[]>([])

const loading = ref(true)
const error = ref<string | null>(null)

const dleCode = computed(
  () => props.game.toUpperCase() as 'KCDLE' | 'LECDLE' | 'LFLDLE',
)

const storageKey = computed(() => dleCode.value)
const winKey = computed(() => `${dleCode.value}_win`)
const lastClearKey = 'lastClearTime'

const hasWon = computed(() => guesses.value.some(g => g.correct === true))

function clearLocalStorageDaily(): boolean {
  const now = new Date()
  const todayLocal = new Date(
    now.getFullYear(),
    now.getMonth(),
    now.getDate(),
  ).getTime()

  const lastClearLocal = parseInt(
    localStorage.getItem(lastClearKey) || '0',
    10,
  )

  if (lastClearLocal < todayLocal) {
    localStorage.clear()
    localStorage.setItem(lastClearKey, todayLocal.toString())
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
  const { data } = await api.get(`/daily/${props.game}`)
  daily.value = data
}

async function loadPlayers() {
  const { data } = await api.get(`/players/${props.game}`, {
    params: { active: 1 },
  })
  joueurs.value = data.players ?? []
}

onMounted(async () => {
  try {
    loading.value = true

    clearLocalStorageDaily()

    await Promise.all([loadDaily(), loadPlayers()])

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
      : `${daily.value.solvers_count ?? 0} personnes ont déjà trouvés !`
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

  const { data } = await api.post(`/games/${props.game}/guess`, {
    player_id: joueurWrapper.id,
    guesses: currentGuessCount,
  })

  const guess = {
    correct: data.correct,
    comparison: data.comparison,
    stats: data.stats,
    player: joueurWrapper,
  }

  guesses.value.unshift(guess)
  if (data.correct === true) {
    try {
      localStorage.setItem(winKey.value, 'true')
    } catch (e) {
      console.error('Erreur lors de la sauvegarde du flag de victoire :', e)
    }
  }

  if (daily.value && data.stats) {
    daily.value.solvers_count = data.stats.solvers_count
    daily.value.total_guesses = data.stats.total_guesses
    daily.value.average_guesses = data.stats.average_guesses
  }

  saveGuessesToStorage()
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

      <SearchBar
        v-if="!hasWon"
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
        />

        <PopupGg
          v-if="hasWon"
          :dle-code="dleCode"
          :guesses="guesses"
        />
      </template>
    </div>

    <Credit />
  </div>
</template>

