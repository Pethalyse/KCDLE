<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import type { GameCode } from '@/types/gameGuess'
import type { LeaderboardRow, LeaderboardResponse } from '@/types/leaderboard'
import { fetchLeaderboard } from '@/api/leaderboardApi'
import { useAuthStore } from '@/stores/auth'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const loading = ref(false)
const error = ref<string | null>(null)
const leaderboard = ref<LeaderboardResponse | null>(null)
const currentPage = ref(1)
const perPage = ref(50)

const availableGames: GameCode[] = ['kcdle', 'lecdle', 'lfldle']

const currentGame = computed<GameCode>(() => {
  const raw = (route.query.game as string | undefined)?.toLowerCase()
  if (availableGames.includes(raw as GameCode)) {
    return raw as GameCode
  }
  return 'kcdle'
})

const rows = computed<LeaderboardRow[]>(() => leaderboard.value?.data ?? [])
const meta = computed(() => leaderboard.value?.meta)
const hasPagination = computed(() => (meta.value?.last_page ?? 1) > 1)

const currentUserId = computed<number | null>(() => {
  return auth.user?.id ?? null
})

async function load() {
  loading.value = true
  error.value = null
  leaderboard.value = null

  try {
    const data = await fetchLeaderboard(currentGame.value, currentPage.value, perPage.value)
    leaderboard.value = data
  } catch (e) {
    console.error(e)
    error.value = 'Impossible de charger le leaderboard.'
  } finally {
    loading.value = false
  }
}

function changeGame(game: GameCode) {
  router.push({
    name: 'leaderboard',
    query: { game },
  })
}

function goToPage(page: number) {
  if (!meta.value) return
  const p = Math.min(Math.max(page, 1), meta.value.last_page)
  if (p === currentPage.value) return
  currentPage.value = p
  load()
}

onMounted(() => {
  const initialPage = Number(route.query.page ?? 1)
  if (Number.isFinite(initialPage) && initialPage > 0) {
    currentPage.value = initialPage
  }
  load()
})

watch(
  () => route.query.game,
  () => {
    currentPage.value = 1
    load()
  },
)
</script>

<template>
  <div class="leaderboard-page">
    <header class="leaderboard-header">
      <div class="header-left">
        <h1>Leaderboard</h1>
        <p>Classement global des joueurs sur {{ currentGame.toUpperCase() }}.</p>
      </div>

      <div class="game-switcher">
        <button
          v-for="game in availableGames"
          :key="game"
          type="button"
          class="game-switcher__button"
          :class="{ active: currentGame === game }"
          @click="changeGame(game)"
        >
          {{ game.toUpperCase() }}
        </button>
      </div>
    </header>

    <main class="leaderboard-main">
      <div v-if="loading" class="leaderboard-state">
        Chargement du classement...
      </div>

      <div v-else-if="error" class="leaderboard-state leaderboard-state--error">
        {{ error }}
      </div>

      <template v-else>
        <table v-if="rows.length" class="leaderboard-table">
          <thead>
          <tr>
            <th>#</th>
            <th>Joueur</th>
            <th>Victoires</th>
            <th>Moyenne de coups</th>
<!--            <th>Score</th>-->
          </tr>
          </thead>
          <tbody>
          <tr
            v-for="row in rows"
            :key="row.rank + '-' + (row.user?.id ?? 'anon')"
            :class="{
                'is-current-user': currentUserId !== null && row.user?.id === currentUserId,
              }"
          >
            <td class="col-rank">
              {{ row.rank }}
            </td>
            <td class="col-user">
                <span class="user-name">
                  {{ row.user?.name ?? 'Joueur inconnu' }}
                </span>
            </td>
            <td class="col-wins">
              {{ row.wins }}
            </td>
            <td class="col-avg">
              {{ row.average_guesses !== null ? row.average_guesses.toFixed(2) : '—' }}
            </td>
<!--            <td class="col-score">-->
<!--              {{ row.final_score.toFixed(2) }}-->
<!--            </td>-->
          </tr>
          </tbody>
        </table>

        <div v-else class="leaderboard-state">
          Aucun joueur classé pour le moment sur ce jeu.
        </div>

        <div v-if="hasPagination" class="leaderboard-pagination">
          <button
            type="button"
            class="page-btn"
            :disabled="currentPage === 1"
            @click="goToPage(currentPage - 1)"
          >
            Précédent
          </button>

          <span class="page-info">
            Page {{ meta?.current_page }} / {{ meta?.last_page }}
          </span>

          <button
            type="button"
            class="page-btn"
            :disabled="meta && currentPage === meta.last_page"
            @click="goToPage(currentPage + 1)"
          >
            Suivant
          </button>
        </div>
      </template>
    </main>
  </div>
</template>

<style scoped>
.leaderboard-page {
  min-height: 100vh;
  padding: 20px 12px 28px;
  display: flex;
  flex-direction: column;
  align-items: center;
  color: #f3f3f3;
  background: radial-gradient(circle at top, #20263a 0, #05060a 75%);
}

.leaderboard-header {
  width: 100%;
  max-width: 900px;
  display: flex;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 16px;
}

.header-left h1 {
  margin: 0;
  font-size: 1.6rem;
}

.header-left p {
  margin: 4px 0 0;
  font-size: 0.9rem;
  opacity: 0.9;
}

.game-switcher {
  display: flex;
  gap: 6px;
}

.game-switcher__button {
  border: none;
  border-radius: 999px;
  padding: 6px 12px;
  background: rgba(255, 255, 255, 0.08);
  color: #f7f7f7;
  font-size: 0.85rem;
  cursor: pointer;
}

.game-switcher__button.active {
  background: #00a6ff;
  color: #ffffff;
}

.game-switcher__button:hover {
  background: rgba(0, 166, 255, 0.5);
}

.leaderboard-main {
  width: 100%;
  max-width: 900px;
}

.leaderboard-state {
  padding: 12px 10px;
  border-radius: 8px;
  background: rgba(10, 12, 20, 0.9);
  border: 1px solid rgba(255, 255, 255, 0.06);
  font-size: 0.9rem;
}

.leaderboard-state--error {
  color: #ff6b6b;
}

.leaderboard-table {
  width: 100%;
  border-collapse: collapse;
  background: rgba(10, 12, 20, 0.92);
  border-radius: 10px;
  overflow: hidden;
  border: 1px solid rgba(255, 255, 255, 0.1);
  font-size: 0.9rem;
}

.leaderboard-table th,
.leaderboard-table td {
  padding: 8px 10px;
}

.leaderboard-table thead {
  background: rgba(255, 255, 255, 0.05);
}

.leaderboard-table tbody tr:nth-child(odd) {
  background: rgba(255, 255, 255, 0.02);
}

.leaderboard-table tbody tr:hover {
  background: rgba(0, 166, 255, 0.18);
}

.leaderboard-table tbody tr.is-current-user {
  background: rgba(0, 166, 255, 0.3);
  box-shadow: 0 0 0 1px rgba(0, 166, 255, 0.8);
}

.col-rank {
  width: 40px;
  text-align: center;
}

.col-user .user-name {
  font-weight: 500;
}

.col-wins,
.col-avg {
  text-align: center;
}

.col-score {
  text-align: right;
}

.leaderboard-pagination {
  margin-top: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 12px;
  font-size: 0.9rem;
}

.page-btn {
  border: none;
  border-radius: 999px;
  padding: 4px 10px;
  background: rgba(255, 255, 255, 0.08);
  color: #f7f7f7;
  cursor: pointer;
}

.page-btn:disabled {
  opacity: 0.4;
  cursor: default;
}

.page-btn:not(:disabled):hover {
  background: rgba(0, 166, 255, 0.5);
}

.page-info {
  opacity: 0.9;
}

@media (max-width: 700px) {
  .leaderboard-header {
    flex-direction: column;
  }

  .leaderboard-table th:nth-child(4),
  .leaderboard-table td:nth-child(4) {
    display: none;
  }
}
</style>
