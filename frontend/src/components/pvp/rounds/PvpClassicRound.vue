<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import api from '@/api'
import SearchBar from '@/components/SearchBar.vue'
import PlayerTab from '@/components/PlayerTab.vue'
import SimpleImg from '@/components/SimpleImg.vue'
import { useAuthStore } from '@/stores/auth'
import { useFlashStore } from '@/stores/flash'
import {handleError} from "@/utils/handleError.ts";

type GameCode = 'kcdle' | 'lecdle' | 'lfldle'

interface GuessEntry {
  correct: boolean
  comparison: {
    correct: boolean
    fields: Record<string, number | null>
  }
  stats: any
  player: any
}

const props = defineProps<{
  matchId: number
  game: GameCode
  players: Array<{ user_id: number; name?: string | null; points: number }>
  round: any
}>()

const emit = defineEmits<{
  (e: 'guess', playerId: number): void
}>()

const auth = useAuthStore()
const flash = useFlashStore()

const dleCode = computed(() => String(props.game || 'kcdle').toUpperCase())
const myUserId = computed(() => auth.user?.id ?? 0)

const backendYouSolved = computed(() => !!props.round?.you?.solved_at)
const opponentSolved = computed(() => !!props.round?.opponent?.solved_at)

const uiYouSolved = ref(false)
const inputLocked = ref(false)

const joueurs = ref<any[]>([])
const pendingGuessedIds = ref<Set<number>>(new Set())

const guessedIdsEffective = computed<number[]>(() => {
  const base = guessedIds.value
  const pend = Array.from(pendingGuessedIds.value)
  const merged = new Set<number>([...base, ...pend])
  return Array.from(merged)
})

const playersById = computed(() => {
  const map = new Map<number, any>()
  for (const j of joueurs.value) {
    if (typeof j?.id === 'number') map.set(j.id, j)
  }
  return map
})

const opponentName = computed(() => {
  const opp = (props.players ?? []).find(p => Number(p?.user_id) !== myUserId.value)
  return opp?.name ?? 'Adversaire'
})

const yourName = computed(() => {
  const me = (props.players ?? []).find(p => Number(p?.user_id) === myUserId.value)
  return me?.name ?? 'Toi'
})

function revealDelayMs(): number {
  const cols = props.game === 'kcdle' ? 9 : 5
  return Math.round(((cols - 1) * 0.4 + 0.5 + 0.1) * 1000)
}

let winTimer: number | null = null

function scheduleUiWin() {
  if (uiYouSolved.value) return
  if (winTimer !== null) window.clearTimeout(winTimer)

  winTimer = window.setTimeout(() => {
    uiYouSolved.value = true
    winTimer = null
    if (!opponentSolved.value) {
      flash.info('En attente de l’adversaire…', 'PvP', 3500)
    }
  }, revealDelayMs())
}

async function loadPlayers() {
  const { data } = await api.get(`/games/${props.game}/players`, { params: { active: 1 } })
  joueurs.value = data.players ?? []
}

const guesses = computed<GuessEntry[]>(() => {
  const list = Array.isArray(props.round?.you?.guesses) ? props.round.you.guesses : []
  const ordered = [...list].reverse()

  return ordered
    .map((g: any) => {
      const pid = Number(g?.player_id ?? 0)
      const correct = !!g?.correct
      const cmp = g?.comparison ?? null

      if (!cmp) {
        return null
      }

      const wrapper = playersById.value.get(pid) ?? { id: pid, player: { slug: String(pid) } }

      return {
        correct,
        comparison: {
          correct: !!cmp?.correct,
          fields: (cmp?.fields ?? {}) as Record<string, number | null>,
        },
        stats: {},
        player: wrapper,
      } as GuessEntry
    })
    .filter(Boolean) as GuessEntry[]
})

const guessedIds = computed<number[]>(() => {
  const list = Array.isArray(props.round?.you?.guesses) ? props.round.you.guesses : []
  return list
    .map((g: any) => Number(g?.player_id ?? 0))
    .filter((id: number) => Number.isFinite(id) && id > 0)
})

watch(
  () => backendYouSolved.value,
  (now, prev) => {
    if (now && !prev) {
      inputLocked.value = true
      scheduleUiWin()
    }
    if (!now) {
      inputLocked.value = false
      uiYouSolved.value = false
      if (winTimer !== null) window.clearTimeout(winTimer)
      winTimer = null
    }
  },
  { immediate: true },
)

function handleClickCard(joueurWrapper: any) {
  const id = Number(joueurWrapper?.id ?? 0)
  if (!id) return
  if (inputLocked.value) return
  if (guessedIdsEffective.value.includes(id)) return
  pendingGuessedIds.value.add(id)
  emit('guess', id)
}

onMounted(async () => {
  try {
    await loadPlayers()
  } catch (e) {
    handleError(e, 'Impossible de charger la liste des joueurs.')
  }

  if (backendYouSolved.value) {
    inputLocked.value = true
    uiYouSolved.value = true
  }
})

watch(
  () => guessedIds.value.slice().sort((a, b) => a - b).join(','),
  () => {
    for (const id of Array.from(pendingGuessedIds.value)) {
      if (guessedIds.value.includes(id)) {
        pendingGuessedIds.value.delete(id)
      }
    }
  },
  { immediate: true },
)

</script>

<template>
  <div class="classic" :class="dleCode">
    <header class="classic-header">
      <div class="header-top">
        <div class="btn-home">
          <SimpleImg class="logo" :alt="dleCode" :img="dleCode + '_page_Logo.png'" />

          <div class="pvp-indicators">
            <div class="pvp-indicator" :class="uiYouSolved ? 'ok' : 'ko'">
              <span class="dot" />
              <span class="label">{{ yourName }}</span>
            </div>
            <div class="pvp-indicator" :class="opponentSolved ? 'ok' : 'ko'">
              <span class="dot" />
              <span class="label">{{ opponentName }}</span>
            </div>
          </div>
        </div>
      </div>

      <SearchBar
        v-if="!uiYouSolved"
        class="containt-name"
        :dle="dleCode"
        :joueurs="joueurs"
        :unwrittable="inputLocked"
        :guessed-ids="guessedIdsEffective"
        @click_card="handleClickCard"
      />
    </header>

    <div class="classic-body" :class="dleCode">
      <PlayerTab :game="game" :guesses="guesses"/>
    </div>
  </div>
</template>

<style scoped>
.classic {
  width: 100%;
  margin: 0 auto;
}

.header-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  flex-wrap: wrap;
}

.pvp-indicators {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
  align-items: center;
  justify-content: center;
  margin-top: 6px;
}

.pvp-indicator {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 6px 10px;
  border-radius: 999px;
  border: 1px solid rgba(255, 255, 255, 0.12);
  background: rgba(255, 255, 255, 0.06);
  font-size: 0.9rem;
}

.pvp-indicator .dot {
  width: 9px;
  height: 9px;
  border-radius: 999px;
  display: inline-block;
  background: rgba(255, 66, 66, 0.95);
}

.pvp-indicator.ok .dot {
  background: rgba(80, 220, 140, 0.95);
}

.classic-body {
  padding: 12px 0 0;
}
</style>
