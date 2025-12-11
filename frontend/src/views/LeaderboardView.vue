<script setup lang="ts">
import {computed, onMounted, ref, watch} from 'vue'
import {useRoute, useRouter} from 'vue-router'
import type {GameCode} from '@/types/gameGuess'
import type {LeaderboardMeta, LeaderboardResponse, LeaderboardRow} from '@/types/leaderboard'
import {fetchLeaderboard} from '@/api/leaderboardApi'
import {useAuthStore} from '@/stores/auth'
import {fetchFriendGroupLeaderboard, fetchFriendGroups} from '@/api/friendGroupApi'
import type {FriendGroupLeaderboardGroup, FriendGroupSummary,} from '@/types/friendGroup'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const loading = ref(false)
const error = ref<string | null>(null)
const leaderboard = ref<LeaderboardResponse | null>(null)

const rows = ref<LeaderboardRow[]>([])
const meta = ref<LeaderboardMeta | null>(null)

const currentPage = ref(1)
const perPage = ref(50)

const availableGames: GameCode[] = ['kcdle', 'lecdle', 'lfldle']

const friendGroups = ref<FriendGroupSummary[]>([])
const loadingGroups = ref(false)
const selectedGroupSlug = ref<string | null>(null)
const groupContext = ref<FriendGroupLeaderboardGroup | null>(null)

const currentGame = computed<GameCode>(() => {
  const raw = (route.query.game as string | undefined)?.toLowerCase()
  if (availableGames.includes(raw as GameCode)) {
    return raw as GameCode
  }
  return 'kcdle'
})

const currentUserId = computed(() => auth.user?.id ?? null)

const hasRows = computed(() => rows.value.length > 0)

const hasPagination = computed(() => {
  if (!meta.value) return false
  return meta.value.last_page > 1
})

const gameLabel = computed(() => currentGame.value.toUpperCase())

const subtitleText = computed(() => {
  if (selectedGroupSlug.value && groupContext.value) {
    return `Classement des joueurs du groupe "${groupContext.value.name}" sur ${gameLabel.value}.`
  }
  return `Classement global des joueurs sur ${gameLabel.value}.`
})

const hasAuth = computed(() => auth.isAuthenticated)

const currentPageFromMeta = computed(() => meta.value?.current_page ?? currentPage.value)

async function loadFriendGroups() {
  if (!hasAuth.value) {
    friendGroups.value = []
    return
  }

  loadingGroups.value = true
  try {
    const data = await fetchFriendGroups()
    friendGroups.value = data.groups
  } catch (e: any) {
    console.error(e)
  } finally {
    loadingGroups.value = false
  }
}

async function load() {
  loading.value = true
  error.value = null
  rows.value = []
  meta.value = null
  groupContext.value = null

  try {
    const groupSlug = selectedGroupSlug.value

    if (groupSlug) {
      const data = await fetchFriendGroupLeaderboard(
        groupSlug,
        currentGame.value,
        currentPage.value,
        perPage.value,
      )

      rows.value = data.data
      meta.value = data.meta
      groupContext.value = data.group
      leaderboard.value = {
        game: currentGame.value,
        data: data.data,
        meta: data.meta,
      }
    } else {
      const data = await fetchLeaderboard(currentGame.value, currentPage.value, perPage.value)
      leaderboard.value = data
      rows.value = data.data
      meta.value = data.meta
      groupContext.value = null
    }
  } catch (e: any) {
    console.error(e)
    error.value = "Impossible de charger le classement."
  } finally {
    loading.value = false
  }
}

function changeGame(game: GameCode) {
  if (game === currentGame.value) return

  router.push({
    name: 'leaderboard',
    query: {
      game,
      page: 1,
      ...(selectedGroupSlug.value ? { group: selectedGroupSlug.value } : {}),
    },
  })
}

function goToPage(page: number) {
  if (!meta.value) return

  const p = Math.min(Math.max(page, 1), meta.value.last_page)
  if (p === currentPage.value) return

  router.push({
    name: 'leaderboard',
    query: {
      game: currentGame.value,
      page: p,
      ...(selectedGroupSlug.value ? { group: selectedGroupSlug.value } : {}),
    },
  })
}

function onGroupChange(event: Event) {
  const target = event.target as HTMLSelectElement
  const value = target.value || ''
  const slug = value.length > 0 ? value : null

  router.push({
    name: 'leaderboard',
    query: {
      game: currentGame.value,
      page: 1,
      ...(slug ? { group: slug } : {}),
    },
  })
}

function goToFriendsPage() {
  router.push({ name: 'friends' })
}

onMounted(async () => {
  const initialPage = Number(route.query.page ?? 1)
  if (Number.isFinite(initialPage) && initialPage > 0) {
    currentPage.value = initialPage
  }

  selectedGroupSlug.value = typeof route.query.group === 'string' ? route.query.group : null

  await load()

  if (hasAuth.value) {
    await loadFriendGroups()
  }
})

watch(
  () => route.query,
  async newQuery => {
    const pageParam = Number(newQuery.page ?? 1)
    currentPage.value = Number.isFinite(pageParam) && pageParam > 0 ? pageParam : 1

    selectedGroupSlug.value = typeof newQuery.group === 'string' ? newQuery.group : null

    await load()
  },
)

watch(
  () => auth.isAuthenticated,
  async isAuth => {
    if (isAuth) {
      await loadFriendGroups()
    } else {
      friendGroups.value = []
      if (selectedGroupSlug.value) {
        await router.push({
          name: 'leaderboard',
          query: {
            game: currentGame.value,
            page: 1,
          },
        })
      }
    }
  },
)
</script>

<template>
  <div class="leaderboard-page">
    <header class="leaderboard-header">
      <div class="header-left">
        <h1>Leaderboard</h1>
        <p>{{ subtitleText }}</p>
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
      <section
        v-if="hasAuth"
        class="leaderboard-groups"
      >
        <div class="leaderboard-groups-row">
          <label class="leaderboard-groups-label">
            Classement
            <select
              class="leaderboard-groups-select"
              :value="selectedGroupSlug || ''"
              @change="onGroupChange"
            >
              <option value="">
                Global (tous les joueurs)
              </option>
              <option
                v-for="group in friendGroups"
                :key="group.id"
                :value="group.slug"
              >
                Groupe : {{ group.name }}
              </option>
            </select>
          </label>

          <button
            type="button"
            class="leaderboard-groups-button"
            @click="goToFriendsPage"
          >
            Gérer mes groupes
          </button>
        </div>

        <p
          v-if="!loadingGroups && friendGroups.length === 0"
          class="leaderboard-groups-hint"
        >
          Tu n’as encore aucun groupe d’amis.
          <button
            type="button"
            class="leaderboard-groups-hint-link"
            @click="goToFriendsPage"
          >
            Crée ton premier groupe
          </button>
          pour comparer ton classement avec tes potes.
        </p>
      </section>

      <section
        v-else
        class="leaderboard-groups leaderboard-groups--guest"
      >
        <p class="leaderboard-groups-hint">
          Connecte-toi pour accéder au classement de tes groupes d’amis.
        </p>
      </section>

      <div v-if="loading" class="leaderboard-state">
        Chargement du classement...
      </div>

      <div
        v-else-if="error"
        class="leaderboard-state leaderboard-state--error"
      >
        {{ error }}
      </div>

      <template v-else>
        <table
          v-if="hasRows"
          class="leaderboard-table"
        >
          <thead>
          <tr>
            <th>#</th>
            <th>Joueur</th>
            <th>Victoires</th>
            <th>Moyenne de coups</th>
            <!-- <th>Score</th> -->
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
            <!--
            <td class="col-score">
              {{ row.final_score.toFixed(2) }}
            </td>
            -->
          </tr>
          </tbody>
        </table>

        <div
          v-else
          class="leaderboard-state"
        >
          Aucun joueur classé pour le moment sur ce jeu.
        </div>

        <div
          v-if="hasPagination && meta"
          class="leaderboard-pagination"
        >
          <button
            type="button"
            class="page-btn"
            :disabled="currentPageFromMeta === 1"
            @click="goToPage(currentPageFromMeta - 1)"
          >
            Précédent
          </button>

          <span class="page-info">
            Page {{ meta.current_page }} / {{ meta.last_page }}
          </span>

          <button
            type="button"
            class="page-btn"
            :disabled="currentPageFromMeta === meta.last_page"
            @click="goToPage(currentPageFromMeta + 1)"
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

.game-switcher {
  display: flex;
  gap: 8px;
}

.game-switcher__button {
  padding: 6px 12px;
  border-radius: 999px;
  border: 1px solid rgba(255, 255, 255, 0.12);
  background: transparent;
  color: #f3f3f3;
  font-size: 0.85rem;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  cursor: pointer;
  transition: background 0.15s ease, color 0.15s ease, transform 0.1s ease,
  box-shadow 0.15s ease;
}

.game-switcher__button.active {
  background: #00a6ff;
  color: #050711;
  box-shadow: 0 0 18px rgba(0, 166, 255, 0.55);
}

.game-switcher__button:hover {
  transform: translateY(-1px);
}

.leaderboard-main {
  width: 100%;
  max-width: 900px;
  background: rgba(6, 8, 18, 0.92);
  border-radius: 14px;
  padding: 16px 12px 18px;
  box-shadow: 0 12px 28px rgba(0, 0, 0, 0.6);
  border: 1px solid rgba(255, 255, 255, 0.06);
  align-items: start;
}

.leaderboard-groups {
  margin-bottom: 12px;
  font-size: 0.86rem;
  color: #d2dcff;
  text-align: start;
}

.leaderboard-groups-row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 10px;
  margin-bottom: 4px;
}

.leaderboard-groups-label {
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

.leaderboard-groups-select {
  padding: 3px 8px;
  border-radius: 999px;
  border: 1px solid rgba(185, 199, 255, 0.6);
  background: #080a16;
  color: #f3f3f3;
  font-size: 0.86rem;
}

.leaderboard-groups-button {
  padding: 4px 10px;
  border-radius: 999px;
  border: 1px solid rgba(185, 199, 255, 0.4);
  background: transparent;
  color: #d2dcff;
  font-size: 0.8rem;
  cursor: pointer;
}

.leaderboard-groups-button:hover {
  background: rgba(185, 199, 255, 0.16);
}

.leaderboard-groups-hint {
  margin: 0;
  opacity: 0.85;
}

.leaderboard-groups-hint-link {
  border: none;
  background: none;
  color: #00a6ff;
  font: inherit;
  cursor: pointer;
  padding: 0 2px;
}

.leaderboard-groups-hint-link:hover {
  text-decoration: underline;
}

/* États */

.leaderboard-state {
  text-align: center;
  padding: 16px 10px;
  font-size: 0.95rem;
  color: #d7e1ff;
}

.leaderboard-state--error {
  color: #ffb6b6;
}

.leaderboard-table {
  width: 100%;
  border-collapse: collapse;
  margin-bottom: 10px;
}

.leaderboard-table th,
.leaderboard-table td {
  padding: 8px 6px;
  font-size: 0.9rem;
}

.leaderboard-table thead {
  background: rgba(17, 21, 36, 0.96);
}

.leaderboard-table th {
  font-weight: 500;
  color: #b9c7ff;
  border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}

.leaderboard-table tbody tr:nth-child(odd) {
  background: rgba(10, 12, 22, 0.9);
}

.leaderboard-table tbody tr:nth-child(even) {
  background: rgba(14, 16, 28, 0.9);
}

.leaderboard-table tbody tr.is-current-user {
  background: rgba(0, 166, 255, 0.15);
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
  padding: 5px 10px;
  border-radius: 999px;
  border: 1px solid rgba(255, 255, 255, 0.16);
  background: rgba(11, 13, 25, 0.95);
  color: #f3f3f3;
  cursor: pointer;
  font-size: 0.85rem;
}

.page-btn:disabled {
  opacity: 0.5;
  cursor: default;
}

.page-info {
  opacity: 0.9;
}

@media (max-width: 700px) {
  .leaderboard-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 8px;
  }

  .leaderboard-main {
    padding-inline: 10px;
  }

  .leaderboard-table th:nth-child(4),
  .leaderboard-table td:nth-child(4) {
    display: none;
  }
}
</style>
z
