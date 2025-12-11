<script setup lang="ts">
import {computed, onMounted, ref} from 'vue'
import {useRouter} from 'vue-router'
import {useAuthStore} from '@/stores/auth'
import {fetchUserProfile} from '@/api/userProfileApi'
import type {UserProfileGameStats, UserProfileResponse} from '@/types/userProfile'

const router = useRouter()
const auth = useAuthStore()

const profile = ref<UserProfileResponse | null>(null)
const loading = ref(true)
const error = ref<string | null>(null)

const hasProfile = computed(() => !!profile.value)

const gamesEntries = computed(() => {
  if (!profile.value) return []
  return Object.entries(profile.value.games) as [string, UserProfileGameStats][]
})

function formatDate(dateIso: string | null) {
  if (!dateIso) return '—'
  try {
    const d = new Date(dateIso)
    return d.toLocaleDateString('fr-FR', {
      year: 'numeric',
      month: 'short',
      day: '2-digit',
    })
  } catch {
    return dateIso
  }
}

function goHome() {
  router.push({ name: 'home' })
}

function goAchievements() {
  router.push({ name: 'achievements' })
}

onMounted(async () => {
  if (!auth.isAuthenticated) {
    goHome();
    return
  }

  try {
    loading.value = true
    profile.value = await fetchUserProfile()
  } catch (e: any) {
    console.error(e)
    error.value = "Impossible de charger ton profil."
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="profile-page">
    <header class="profile-header">
      <div>
        <h1>Mon profil</h1>
      </div>
    </header>

    <main class="profile-main">
      <div v-if="loading">
        Chargement du profil...
      </div>

      <div
        v-else-if="error"
        class="profile-error"
      >
        {{ error }}
      </div>

      <div
        v-else-if="hasProfile && profile"
        class="profile-grid"
      >
        <section class="profile-card">
          <h2>Informations du compte</h2>
          <ul class="profile-list">
            <li>
              <span class="label">Pseudo</span>
              <span class="value">{{ profile.user.name }}</span>
            </li>
            <li>
              <span class="label">Email</span>
              <span class="value">{{ profile.user.email }}</span>
            </li>
            <li>
              <span class="label">Inscription</span>
              <span class="value">{{ formatDate(profile.user.created_at) }}</span>
            </li>
          </ul>
        </section>

        <section class="profile-card">
          <h2>Stats globales</h2>
          <ul class="profile-list">
            <li>
              <span class="label">Victoires totales</span>
              <span class="value">{{ profile.global_stats.total_wins }}</span>
            </li>
            <li>
              <span class="label">Jours joués</span>
              <span class="value">{{ profile.global_stats.distinct_days_played }}</span>
            </li>
            <li>
              <span class="label">Moyenne de coups</span>
              <span class="value">
                {{
                  profile.global_stats.global_average_guesses !== null
                    ? profile.global_stats.global_average_guesses
                    : '—'
                }}
              </span>
            </li>
            <li>
              <span class="label">Première victoire</span>
              <span class="value">{{ formatDate(profile.global_stats.first_win_at) }}</span>
            </li>
            <li>
              <span class="label">Dernière victoire</span>
              <span class="value">{{ formatDate(profile.global_stats.last_win_at) }}</span>
            </li>
          </ul>
        </section>

        <section class="profile-card">
          <h2>Succès</h2>

          <p class="profile-achievements">
            <strong>{{ profile.achievements.unlocked }}</strong>
            succès débloqués sur
            <strong>{{ profile.achievements.total }}</strong>
          </p>

          <button
            type="button"
            class="profile-achievements-btn"
            @click="goAchievements"
          >
            Voir tous mes succès
          </button>
        </section>


        <section class="profile-card">
          <h2>Groupes d'amis</h2>

          <p v-if="profile.friend_groups.length === 0">
            Tu n'es encore dans aucun groupe d'amis.
          </p>

          <ul
            v-else
            class="profile-groups"
          >
            <li
              v-for="group in profile.friend_groups"
              :key="group.id"
            >
              <div class="group-header">
                <h3>{{ group.name }}</h3>
                <span class="group-slug">@{{ group.slug }}</span>
              </div>
              <p class="group-meta">
                Code d'invitation :
                <span class="code">{{ group.join_code }}</span>
                <span class="owner" v-if="group.owner?.id">
                  • Créé par {{ group.owner.name }}
                </span>
              </p>
            </li>
          </ul>
        </section>

        <section class="profile-card profile-games">
          <h2>Stats par jeu</h2>

          <div class="profile-games-grid">
            <article
              v-for="[gameCode, stats] in gamesEntries"
              :key="gameCode"
              class="profile-game-item"
            >
              <h3 class="game-title">
                {{ gameCode.toUpperCase() }}
              </h3>

              <ul class="profile-list compact">
                <li>
                  <span class="label">Victoires</span>
                  <span class="value">{{ stats.wins }}</span>
                </li>
                <li>
                  <span class="label">Moyenne de coups</span>
                  <span class="value">
                    {{ stats.average_guesses !== null ? stats.average_guesses : '—' }}
                  </span>
                </li>
                <li>
                  <span class="label">Série actuelle</span>
                  <span class="value">{{ stats.current_streak }}</span>
                </li>
                <li>
                  <span class="label">Meilleure série</span>
                  <span class="value">{{ stats.max_streak }}</span>
                </li>
              </ul>
            </article>
          </div>
        </section>
      </div>
    </main>
  </div>
</template>

<style scoped>
.profile-page {
  padding: 20px;
  color: #f0f0f0;
  background: radial-gradient(circle at top, #20263a 0, #05060a 75%);
  font-size: 0.95rem;
  min-height: 100vh;
}

.profile-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 18px;
  gap: 12px;
}

.profile-header h1 {
  margin: 0;
  font-size: 1.6rem;
}

.profile-header p {
  margin: 4px 0 0;
  font-size: 0.9rem;
  opacity: 0.9;
}

.profile-email {
  font-size: 0.85rem;
  opacity: 0.8;
}

.profile-back {
  border: none;
  background: #00a6ff;
  color: #fff;
  padding: 6px 10px;
  border-radius: 4px;
  cursor: pointer;
  font-size: 0.9rem;
}

.profile-main {
  max-width: 1000px;
  margin: 0 auto;
}

.profile-error {
  color: #ff6b6b;
}

.profile-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
  gap: 16px;
}

.profile-card {
  background: rgba(10, 12, 20, 0.9);
  border-radius: 8px;
  padding: 14px 16px;
  border: 1px solid rgba(255, 255, 255, 0.06);
  box-shadow: 0 8px 16px rgba(0, 0, 0, 0.35);
}

.profile-card h2 {
  margin: 0 0 8px;
  font-size: 1.1rem;
}

.profile-list {
  list-style: none;
  padding: 0;
  margin: 4px 0 0;
}

.profile-list li {
  display: flex;
  justify-content: space-between;
  gap: 10px;
  padding: 4px 0;
}

.profile-list.compact li {
  padding: 2px 0;
}

.label {
  opacity: 0.8;
}

.value {
  font-weight: 600;
}

.profile-achievements {
  margin: 4px 0;
}

.profile-achievements-hint {
  margin: 4px 0 0;
  font-size: 0.85rem;
  opacity: 0.8;
}

.profile-groups {
  list-style: none;
  padding: 0;
  margin: 4px 0 0;
}

.profile-groups li + li {
  margin-top: 8px;
  padding-top: 8px;
  border-top: 1px solid rgba(255, 255, 255, 0.06);
}

.group-header {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  gap: 8px;
}

.group-header h3 {
  margin: 0;
  font-size: 1rem;
}

.group-slug {
  font-size: 0.8rem;
  opacity: 0.8;
}

.group-meta {
  margin: 2px 0 0;
  font-size: 0.85rem;
  opacity: 0.9;
}

.code {
  font-family: monospace;
  background: rgba(255, 255, 255, 0.04);
  padding: 2px 4px;
  border-radius: 3px;
}

.owner {
  margin-left: 4px;
}

.profile-games {
  grid-column: 1 / -1;
}

.profile-games-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 10px;
  margin-top: 4px;
}

.profile-game-item {
  background: rgba(15, 18, 28, 0.9);
  border-radius: 6px;
  padding: 8px 10px;
  border: 1px solid rgba(255, 255, 255, 0.04);
}

.game-title {
  margin: 0 0 4px;
  font-size: 0.95rem;
}

.profile-achievements-btn {
  margin-top: 8px;
  width: 100%;
  padding: 8px 10px;
  border: 1px solid #00a6ff;
  border-radius: 6px;
  background: rgba(0, 166, 255, 0.15);
  color: #00a6ff;
  cursor: pointer;
  font-size: 0.9rem;
  transition: background 0.15s ease;
}

.profile-achievements-btn:hover {
  background: rgba(0, 166, 255, 0.25);
}


@media (max-width: 600px) {
  .profile-header {
    flex-direction: column;
    align-items: flex-start;
  }

  .profile-back {
    align-self: stretch;
    text-align: center;
  }
}
</style>
