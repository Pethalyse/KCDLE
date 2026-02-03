<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { fetchUserProfile, updateUserProfile } from '@/api/userProfileApi'
import { fetchDiscordAuthUrl, unlinkDiscord } from '@/api/discordAuthApi'
import type { UserProfileGameStats, UserProfileResponse } from '@/types/userProfile'
import { handleError } from '@/utils/handleError'
import AdSlot from '@/components/AdSlot.vue'
import { useFlashStore } from '@/stores/flash'
import UserBadge from '@/components/UserBadge.vue'

type TabKey = 'games' | 'pvp' | 'groups'

const router = useRouter()
const auth = useAuthStore()
const flash = useFlashStore()

const profile = ref<UserProfileResponse | null>(null)
const loading = ref(true)
const error = ref<string | null>(null)

const saving = ref(false)
const avatarFile = ref<File | null>(null)
const avatarPreview = ref<string | null>(null)
const frameColor = ref<string>('#00a6ff')

const presetColors = [
  '#00a6ff',
  '#7c3aed',
  '#ff4d4d',
  '#22c55e',
  '#f59e0b',
  '#14b8a6',
  '#e11d48',
  '#a3a3a3',
]

const tab = ref<TabKey>('games')
const showAllPrivateOpponents = ref(false)

const discordLinkLoading = ref(false)
const discordUnlinkLoading = ref(false)

const hasProfile = computed(() => !!profile.value)

const isDiscordLinked = computed(() => {
  const id = profile.value?.user?.discord_id ?? (auth.user as any)?.discord_id
  return Boolean(id && String(id).trim().length > 0)
})

const gamesEntries = computed(() => {
  if (!profile.value) return []
  return Object.entries(profile.value.games) as [string, UserProfileGameStats][]
})

const achievementsPct = computed(() => {
  const total = Number(profile.value?.achievements?.total ?? 0)
  const unlocked = Number(profile.value?.achievements?.unlocked ?? 0)
  if (total <= 0) return 0
  return Math.round((unlocked / total) * 100)
})

const privateOpponentsVisible = computed(() => {
  if (!profile.value) return []
  const all = profile.value.pvp?.private_opponents ?? []
  return showAllPrivateOpponents.value ? all : all.slice(0, 5)
})

function formatDate(dateIso: string | null) {
  if (!dateIso) return '—'
  try {
    const d = new Date(dateIso)
    return d.toLocaleDateString('fr-FR', { year: 'numeric', month: 'short', day: '2-digit' })
  } catch {
    return dateIso
  }
}

function goAchievements() {
  router.push({ name: 'achievements' })
}

function syncEditorFromProfile() {
  const c = (profile.value?.user?.avatar_frame_color ?? '').toString().trim()
  frameColor.value = c.length > 0 ? c : '#00a6ff'
}

function onSelectAvatar(e: Event) {
  const input = e.target as HTMLInputElement
  const file = input.files && input.files.length > 0 ? input.files[0] : null
  if (!file) {
    avatarFile.value = null
    avatarPreview.value = null
    return
  }

  const isGif = file.type === 'image/gif' || file.name.toLowerCase().endsWith('.gif')
  if (isGif && !auth.user?.is_admin) {
    avatarFile.value = null
    avatarPreview.value = null
    input.value = ''
    flash.error('Les GIF sont réservés aux admins pour le moment.', 'Photo de profil')
    return
  }

  avatarFile.value = file
  try {
    avatarPreview.value = URL.createObjectURL(file)
  } catch {
    avatarPreview.value = null
  }
}

async function saveProfileCustomization() {
  if (!profile.value) return
  saving.value = true
  try {
    const data = await updateUserProfile({
      avatar: avatarFile.value,
      avatar_frame_color: frameColor.value,
    })

    profile.value = data
    auth.updateUser({
      ...(auth.user as any),
      ...(data.user as any),
    })

    if (avatarPreview.value) {
      try {
        URL.revokeObjectURL(avatarPreview.value)
      } catch {}
    }
    avatarFile.value = null
    avatarPreview.value = null
    flash.success('Profil mis à jour.', 'Profil')
  } catch (e: any) {
    handleError(e)
    flash.error('Impossible de mettre à jour ton profil.', 'Profil')
  } finally {
    saving.value = false
  }
}

async function startDiscordLink() {
  discordLinkLoading.value = true
  try {
    const { url } = await fetchDiscordAuthUrl('link')
    sessionStorage.setItem('kcdle_discord_return_to', router.currentRoute.value.fullPath)
    window.location.href = url
  } catch (e: any) {
    handleError(e)
    flash.error('Impossible de lancer la liaison Discord.', 'Discord')
  } finally {
    discordLinkLoading.value = false
  }
}

async function unlinkDiscordFromProfile() {
  discordUnlinkLoading.value = true
  try {
    const data = await unlinkDiscord()
    auth.updateUser({
      ...(auth.user as any),
      ...(data.user as any),
    })

    profile.value = await fetchUserProfile()
    syncEditorFromProfile()
    flash.success('Compte Discord dissocié.', 'Discord')
  } catch (e: any) {
    handleError(e)
    flash.error('Impossible de dissocier ton compte Discord.', 'Discord')
  } finally {
    discordUnlinkLoading.value = false
  }
}

onMounted(async () => {
  if (!auth.isAuthenticated) {
    await router.push({ name: 'home' })
    return
  }

  try {
    loading.value = true
    profile.value = await fetchUserProfile()
    syncEditorFromProfile()
  } catch (e: any) {
    handleError(e)
    error.value = 'Impossible de charger ton profil.'
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="profile-page">
    <main class="profile-main">
      <div v-if="loading">Chargement du profil...</div>

      <div v-else-if="error" class="profile-error">
        {{ error }}
      </div>

      <div v-else-if="hasProfile && profile" class="profile-layout">
        <header class="profile-overview">
          <section class="profile-card profile-identity">
            <div class="identity-left">
              <div class="identity-head">
                <div class="identity-info">
                  <h1 class="profile-title">Mon profil</h1>

                  <div class="identity-meta">
                    <div class="meta-line">
                      <span class="meta-label">Pseudo</span>
                      <span class="meta-value">{{ profile.user.name }}</span>
                    </div>
                    <div class="meta-line">
                      <span class="meta-label">Email</span>
                      <span class="meta-value">{{ profile.user.email }}</span>
                    </div>
                    <div class="meta-line">
                      <span class="meta-label">Inscription</span>
                      <span class="meta-value">{{ formatDate(profile.user.created_at) }}</span>
                    </div>
                  </div>
                </div>

                <UserBadge
                  :name="profile.user.name"
                  :avatar-url="avatarPreview ?? profile.user.avatar_url ?? null"
                  :frame-color="frameColor"
                  :size="88"
                  :show-name="false"
                  :admin="Boolean(profile.user.is_admin)"
                />
              </div>

              <div class="identity-custom">
                <div class="custom-row">
                  <label class="avatar-upload">
                    <span class="avatar-upload-text">Changer la photo</span>
                    <input
                      type="file"
                      accept="image/*"
                      class="avatar-upload-input"
                      @change="onSelectAvatar"
                    />
                  </label>

                  <button
                    type="button"
                    class="avatar-save"
                    :disabled="saving"
                    @click="saveProfileCustomization"
                  >
                    {{ saving ? 'Enregistrement…' : 'Enregistrer' }}
                  </button>
                </div>

                <div class="frame-picker">
                  <div class="frame-label">Cadre</div>
                  <div class="frame-colors">
                    <button
                      v-for="c in presetColors"
                      :key="c"
                      type="button"
                      class="frame-color"
                      :class="{ active: frameColor.toLowerCase() === c.toLowerCase() }"
                      :style="{ background: c }"
                      @click="frameColor = c"
                      :aria-label="'Couleur ' + c"
                    />
                    <input
                      v-model="frameColor"
                      type="color"
                      class="frame-custom"
                      aria-label="Couleur personnalisée"
                    />
                  </div>
                </div>
              </div>

              <div class="identity-links">
                <button
                  v-if="!isDiscordLinked"
                  type="button"
                  class="link-icon-btn"
                  :disabled="discordLinkLoading"
                  aria-label="Discord"
                  title="Discord"
                  @click="startDiscordLink"
                >
                  <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-discord" viewBox="0 0 16 16">
                    <path d="M13.545 2.907a13.2 13.2 0 0 0-3.257-1.011.05.05 0 0 0-.052.025c-.141.25-.297.577-.406.833a12.2 12.2 0 0 0-3.658 0 8 8 0 0 0-.412-.833.05.05 0 0 0-.052-.025c-1.125.194-2.22.534-3.257 1.011a.04.04 0 0 0-.021.018C.356 6.024-.213 9.047.066 12.032q.003.022.021.037a13.3 13.3 0 0 0 3.995 2.02.05.05 0 0 0 .056-.019q.463-.63.818-1.329a.05.05 0 0 0-.01-.059l-.018-.011a9 9 0 0 1-1.248-.595.05.05 0 0 1-.02-.066l.015-.019q.127-.095.248-.195a.05.05 0 0 1 .051-.007c2.619 1.196 5.454 1.196 8.041 0a.05.05 0 0 1 .053.007q.121.1.248.195a.05.05 0 0 1-.004.085 8 8 0 0 1-1.249.594.05.05 0 0 0-.03.03.05.05 0 0 0 .003.041c.24.465.515.909.817 1.329a.05.05 0 0 0 .056.019 13.2 13.2 0 0 0 4.001-2.02.05.05 0 0 0 .021-.037c.334-3.451-.559-6.449-2.366-9.106a.03.03 0 0 0-.02-.019m-8.198 7.307c-.789 0-1.438-.724-1.438-1.612s.637-1.613 1.438-1.613c.807 0 1.45.73 1.438 1.613 0 .888-.637 1.612-1.438 1.612m5.316 0c-.788 0-1.438-.724-1.438-1.612s.637-1.613 1.438-1.613c.807 0 1.451.73 1.438 1.613 0 .888-.631 1.612-1.438 1.612"/>
                  </svg>
                </button>

                <button
                  v-else
                  type="button"
                  class="link-icon-btn danger"
                  :disabled="discordUnlinkLoading"
                  aria-label="Discord"
                  title="Discord"
                  @click="unlinkDiscordFromProfile"
                >
                  <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-discord" viewBox="0 0 16 16">
                    <path d="M13.545 2.907a13.2 13.2 0 0 0-3.257-1.011.05.05 0 0 0-.052.025c-.141.25-.297.577-.406.833a12.2 12.2 0 0 0-3.658 0 8 8 0 0 0-.412-.833.05.05 0 0 0-.052-.025c-1.125.194-2.22.534-3.257 1.011a.04.04 0 0 0-.021.018C.356 6.024-.213 9.047.066 12.032q.003.022.021.037a13.3 13.3 0 0 0 3.995 2.02.05.05 0 0 0 .056-.019q.463-.63.818-1.329a.05.05 0 0 0-.01-.059l-.018-.011a9 9 0 0 1-1.248-.595.05.05 0 0 1-.02-.066l.015-.019q.127-.095.248-.195a.05.05 0 0 1 .051-.007c2.619 1.196 5.454 1.196 8.041 0a.05.05 0 0 1 .053.007q.121.1.248.195a.05.05 0 0 1-.004.085 8 8 0 0 1-1.249.594.05.05 0 0 0-.03.03.05.05 0 0 0 .003.041c.24.465.515.909.817 1.329a.05.05 0 0 0 .056.019 13.2 13.2 0 0 0 4.001-2.02.05.05 0 0 0 .021-.037c.334-3.451-.559-6.449-2.366-9.106a.03.03 0 0 0-.02-.019m-8.198 7.307c-.789 0-1.438-.724-1.438-1.612s.637-1.613 1.438-1.613c.807 0 1.45.73 1.438 1.613 0 .888-.637 1.612-1.438 1.612m5.316 0c-.788 0-1.438-.724-1.438-1.612s.637-1.613 1.438-1.613c.807 0 1.451.73 1.438 1.613 0 .888-.631 1.612-1.438 1.612"/>
                  </svg>
                </button>
              </div>
            </div>
          </section>

          <section class="profile-card profile-kpis">
            <h2 class="section-title">Aperçu</h2>

            <div class="kpi-grid">
              <div class="kpi-tile">
                <div class="kpi-label">Victoires (DLE)</div>
                <div class="kpi-value">{{ profile.global_stats.total_wins }}</div>
              </div>

              <div class="kpi-tile">
                <div class="kpi-label">Jours joués</div>
                <div class="kpi-value">{{ profile.global_stats.distinct_days_played }}</div>
              </div>

              <div class="kpi-tile">
                <div class="kpi-label">Moyenne de coups</div>
                <div class="kpi-value">
                  {{ profile.global_stats.global_average_guesses !== null ? profile.global_stats.global_average_guesses : '—' }}
                </div>
              </div>

              <div class="kpi-tile">
                <div class="kpi-label">PvP (matchs)</div>
                <div class="kpi-value">{{ profile.pvp.total.matches }}</div>
              </div>

              <div class="kpi-tile">
                <div class="kpi-label">PvP (winrate)</div>
                <div class="kpi-value">{{ profile.pvp.total.winrate }}%</div>
              </div>

              <div class="kpi-tile">
                <div class="kpi-label">PvP (W/L)</div>
                <div class="kpi-value">
                  {{ profile.pvp.total.wins }} - {{ profile.pvp.total.losses }}
                </div>
              </div>
            </div>

            <AdSlot id="profile-inline-1" kind="inline" />
          </section>

          <section class="profile-card">
            <div class="profile-achievements-card">
              <div class="ach-title">Succès</div>
              <div class="ach-kpi">
                <span class="ach-big">{{ profile.achievements.unlocked }}</span>
                <span class="ach-sep">/</span>
                <span class="ach-small">{{ profile.achievements.total }}</span>
              </div>
              <div class="ach-bar">
                <div class="ach-bar-fill" :style="{ width: achievementsPct + '%' }" />
              </div>
              <div class="ach-sub">{{ achievementsPct }}%</div>

              <button type="button" class="profile-achievements-btn" @click="goAchievements">
                Voir tous mes succès
              </button>
            </div>
          </section>
        </header>

        <nav class="profile-tabs">
          <button
            type="button"
            class="tab-btn"
            :class="{ active: tab === 'games' }"
            @click="tab = 'games'"
          >
            Jeux
          </button>
          <button
            type="button"
            class="tab-btn"
            :class="{ active: tab === 'pvp' }"
            @click="tab = 'pvp'"
          >
            PvP
          </button>
          <button
            type="button"
            class="tab-btn"
            :class="{ active: tab === 'groups' }"
            @click="tab = 'groups'"
          >
            Groupes
          </button>
        </nav>

        <section v-if="tab === 'games'" class="profile-section">
          <div class="profile-card">
            <h2 class="section-title">Stats par jeu</h2>

            <div class="profile-games-grid">
              <article
                v-for="[gameCode, stats] in gamesEntries"
                :key="gameCode"
                class="profile-game-item"
              >
                <div class="game-head">
                  <h3 class="game-title">{{ gameCode.toUpperCase() }}</h3>
                  <div class="game-wins">{{ stats.wins }} victoires</div>
                </div>

                <ul class="profile-list compact">
                  <li>
                    <span class="label">Moyenne de coups</span>
                    <span class="value">{{ stats.average_guesses !== null ? stats.average_guesses : '—' }}</span>
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
          </div>
        </section>

        <section v-else-if="tab === 'pvp'" class="profile-section">
          <div class="profile-card">
            <h2 class="section-title">PvP</h2>

            <div class="pvp-grid">
              <div class="pvp-box">
                <div class="pvp-box-head">
                  <h3 class="pvp-title">Queue</h3>
                  <div class="pvp-sub">{{ profile.pvp.queue.matches }} matchs</div>
                </div>

                <div class="pvp-kpis">
                  <div class="pvp-kpi">
                    <div class="kpi-label">Winrate</div>
                    <div class="kpi-value">{{ profile.pvp.queue.winrate }}%</div>
                  </div>
                  <div class="pvp-kpi">
                    <div class="kpi-label">Victoires</div>
                    <div class="kpi-value">{{ profile.pvp.queue.wins }}</div>
                  </div>
                  <div class="pvp-kpi">
                    <div class="kpi-label">Défaites</div>
                    <div class="kpi-value">{{ profile.pvp.queue.losses }}</div>
                  </div>
                </div>
              </div>

              <div class="pvp-box">
                <div class="pvp-box-head">
                  <h3 class="pvp-title">Privé</h3>
                  <div class="pvp-sub">{{ profile.pvp.private.matches }} matchs</div>
                </div>

                <div class="pvp-kpis">
                  <div class="pvp-kpi">
                    <div class="kpi-label">Winrate</div>
                    <div class="kpi-value">{{ profile.pvp.private.winrate }}%</div>
                  </div>
                  <div class="pvp-kpi">
                    <div class="kpi-label">Victoires</div>
                    <div class="kpi-value">{{ profile.pvp.private.wins }}</div>
                  </div>
                  <div class="pvp-kpi">
                    <div class="kpi-label">Défaites</div>
                    <div class="kpi-value">{{ profile.pvp.private.losses }}</div>
                  </div>
                </div>

                <div class="pvp-opponents">
                  <div class="pvp-opponents-head">
                    <div class="pvp-opponents-title">Top adversaires</div>

                    <button
                      v-if="(profile.pvp.private_opponents?.length ?? 0) > 5"
                      type="button"
                      class="link-btn"
                      @click="showAllPrivateOpponents = !showAllPrivateOpponents"
                    >
                      {{ showAllPrivateOpponents ? 'Réduire' : 'Afficher plus' }}
                    </button>
                  </div>

                  <div v-if="(profile.pvp.private_opponents?.length ?? 0) === 0" class="muted">
                    Aucun match privé terminé pour le moment.
                  </div>

                  <ul v-else class="opponents-list">
                    <li v-for="opp in privateOpponentsVisible" :key="opp.user_id" class="opponent-row">
                      <div class="opp-line">
                        <span class="opp-name">{{ opp.name }}</span>
                        <span class="opp-sep">•</span>
                        <span class="opp-meta">{{ opp.matches }} matchs</span>
                        <span class="opp-sep">•</span>
                        <span class="opp-meta">{{ opp.wins }}W-{{ opp.losses }}L</span>
                      </div>
                      <div class="opp-right">
                        <div class="opp-wr">{{ opp.winrate }}%</div>
                      </div>
                    </li>
                  </ul>
                </div>
              </div>
            </div>

            <div class="pvp-total">
              <div class="muted">
                Total : {{ profile.pvp.total.matches }} matchs • {{ profile.pvp.total.wins }}W-{{ profile.pvp.total.losses }}L • {{ profile.pvp.total.winrate }}%
              </div>
            </div>
          </div>
        </section>

        <section v-else class="profile-section">
          <div class="profile-card">
            <h2 class="section-title">Groupes</h2>

            <p v-if="profile.friend_groups.length === 0">
              Tu n'es encore dans aucun groupe d'amis.
            </p>

            <ul v-else class="profile-groups">
              <li v-for="group in profile.friend_groups" :key="group.id">
                <div class="group-header">
                  <h3>{{ group.name }}</h3>
                  <span class="group-slug">@{{ group.slug }}</span>
                </div>
                <p class="group-meta">
                  {{ group.users_count }} utilisateur<span v-if="group.users_count > 1">s</span>
                  • Code d'invitation : <span class="code">{{ group.join_code }}</span>
                  <span class="owner" v-if="group.owner?.id"> • Créé par {{ group.owner.name }}</span>
                </p>
              </li>
            </ul>
          </div>
        </section>

        <section class="profile-ad">
          <AdSlot id="profile-banner-1" kind="banner" />
        </section>
      </div>
    </main>
  </div>
</template>

<style scoped>
.profile-page {
  padding: 20px 20px 100px;
  color: #f0f0f0;
  background: radial-gradient(circle at top, #20263a 0, #05060a 75%);
  font-size: 0.95rem;
  min-height: 100vh;
}

.profile-main {
  max-width: 1100px;
  margin: 0 auto;
}

.profile-error {
  color: #ff6b6b;
}

.profile-layout {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.profile-card {
  background: rgba(10, 12, 20, 0.9);
  border-radius: 10px;
  padding: 14px 16px;
  border: 1px solid rgba(255, 255, 255, 0.06);
  box-shadow: 0 8px 16px rgba(0, 0, 0, 0.35);
}

.profile-overview {
  display: grid;
  grid-template-columns: 1.3fr 1fr;
  gap: 14px;
  align-items: start;
}

.profile-title {
  margin: 0;
  font-size: 1.6rem;
  line-height: 1.15;
}

.section-title {
  margin: 0 0 10px;
  font-size: 1.1rem;
}

.identity-left {
  display: flex;
  flex-direction: column;
  gap: 14px;
  min-width: 0;
}

.identity-head {
  justify-content: center;
  display: flex;
  gap: 30px;
  align-items: center;
  min-width: 0;
}

.identity-info {
  display: flex;
  flex-direction: column;
  gap: 10px;
  min-width: 0;
}

.identity-meta {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.meta-line {
  display: flex;
  justify-content: space-between;
  gap: 12px;
}

.meta-label {
  opacity: 0.78;
}

.meta-value {
  font-weight: 650;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  max-width: 65%;
  text-align: right;
}

.identity-custom {
  background: rgba(15, 18, 28, 0.7);
  border: 1px solid rgba(255, 255, 255, 0.05);
  border-radius: 10px;
  padding: 12px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.custom-row {
  display: flex;
  gap: 10px;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
}

.avatar-upload {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  width: fit-content;
  padding: 8px 12px;
  border-radius: 999px;
  border: 1px solid rgba(255, 255, 255, 0.14);
  background: rgba(255, 255, 255, 0.06);
  cursor: pointer;
}

.avatar-upload:hover {
  background: rgba(255, 255, 255, 0.1);
}

.avatar-upload-input {
  display: none;
}

.avatar-upload-text {
  font-size: 0.9rem;
  opacity: 0.95;
}

.avatar-save {
  padding: 9px 12px;
  border-radius: 10px;
  border: 1px solid rgba(0, 166, 255, 0.55);
  background: rgba(0, 166, 255, 0.18);
  color: #d9f3ff;
  font-weight: 750;
  cursor: pointer;
}

.avatar-save:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.avatar-save:hover {
  background: rgba(0, 166, 255, 0.25);
}

.frame-picker {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.frame-label {
  font-size: 0.9rem;
  opacity: 0.92;
  font-weight: 650;
}

.frame-colors {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 10px;
}

.frame-color {
  width: 20px;
  height: 20px;
  border-radius: 999px;
  border: 2px solid rgba(255, 255, 255, 0.2);
  cursor: pointer;
}

.frame-color.active {
  border-color: rgba(255, 255, 255, 0.85);
}

.frame-custom {
  width: 30px;
  height: 30px;
  padding: 0;
  border: none;
  background: transparent;
  cursor: pointer;
}

.identity-links {
  display: flex;
  justify-content: flex-start;
}

.link-icon-btn {
  width: 44px;
  height: 44px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 12px;
  border: 1px solid rgba(255, 255, 255, 0.14);
  background: rgba(255, 255, 255, 0.06);
  color: #f0f0f0;
  cursor: pointer;
  transition: background 0.15s ease, border-color 0.15s ease, transform 0.05s ease;
}

.link-icon-btn:hover:not(:disabled) {
  background: rgba(255, 255, 255, 0.1);
}

.link-icon-btn:active:not(:disabled) {
  transform: translateY(1px);
}

.link-icon-btn:disabled {
  opacity: 0.65;
  cursor: not-allowed;
}

.link-icon-btn.danger {
  border-color: rgba(225, 29, 72, 0.45);
  background: rgba(225, 29, 72, 0.14);
}

.link-icon-btn.danger:hover:not(:disabled) {
  background: rgba(225, 29, 72, 0.2);
}

.profile-achievements-card {
  background: rgba(15, 18, 28, 0.7);
  border: 1px solid rgba(255, 255, 255, 0.05);
  border-radius: 10px;
  padding: 12px;
  display: flex;
  flex-direction: column;
}

.ach-title {
  font-size: 0.95rem;
  opacity: 0.9;
  font-weight: 650;
}

.ach-kpi {
  display: flex;
  align-items: baseline;
  gap: 6px;
  margin-top: 8px;
}

.ach-big {
  font-size: 1.8rem;
  font-weight: 800;
}

.ach-sep {
  opacity: 0.6;
}

.ach-small {
  opacity: 0.9;
  font-weight: 750;
}

.ach-bar {
  margin-top: 10px;
  height: 9px;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.06);
  overflow: hidden;
}

.ach-bar-fill {
  height: 100%;
  background: rgba(0, 166, 255, 0.55);
}

.ach-sub {
  margin-top: 8px;
  font-size: 0.85rem;
  opacity: 0.85;
}

.profile-achievements-btn {
  margin-top: 12px;
  width: 100%;
  padding: 9px 10px;
  border: 1px solid #00a6ff;
  border-radius: 10px;
  background: rgba(0, 166, 255, 0.15);
  color: #00a6ff;
  cursor: pointer;
  font-size: 0.92rem;
  transition: background 0.15s ease;
}

.profile-achievements-btn:hover {
  background: rgba(0, 166, 255, 0.25);
}

.kpi-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 10px;
}

.kpi-tile {
  background: rgba(15, 18, 28, 0.9);
  border-radius: 8px;
  padding: 10px 10px;
  border: 1px solid rgba(255, 255, 255, 0.04);
}

.kpi-label {
  opacity: 0.8;
  font-size: 0.85rem;
}

.kpi-value {
  margin-top: 4px;
  font-weight: 800;
  font-size: 1.05rem;
}

.profile-tabs {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

.tab-btn {
  border: 1px solid rgba(255, 255, 255, 0.1);
  background: rgba(15, 18, 28, 0.7);
  color: #f0f0f0;
  padding: 8px 12px;
  border-radius: 10px;
  cursor: pointer;
  transition: background 0.15s ease, border-color 0.15s ease;
}

.tab-btn.active {
  border-color: rgba(0, 166, 255, 0.6);
  background: rgba(0, 166, 255, 0.12);
}

.profile-section {
  display: flex;
  flex-direction: column;
}

.profile-games-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
  gap: 10px;
  margin-top: 4px;
}

.profile-game-item {
  background: rgba(15, 18, 28, 0.9);
  border-radius: 8px;
  padding: 10px 10px;
  border: 1px solid rgba(255, 255, 255, 0.04);
}

.game-head {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  gap: 10px;
  margin-bottom: 6px;
}

.game-title {
  margin: 0;
  font-size: 0.95rem;
}

.game-wins {
  font-size: 0.85rem;
  opacity: 0.85;
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

.pvp-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}

.pvp-box {
  background: rgba(15, 18, 28, 0.9);
  border-radius: 8px;
  padding: 12px 12px;
  border: 1px solid rgba(255, 255, 255, 0.04);
}

.pvp-box-head {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  gap: 10px;
}

.pvp-title {
  margin: 0;
  font-size: 1rem;
}

.pvp-sub {
  opacity: 0.85;
  font-size: 0.85rem;
}

.pvp-kpis {
  margin-top: 10px;
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 10px;
}

.pvp-kpi {
  background: rgba(10, 12, 20, 0.7);
  border: 1px solid rgba(255, 255, 255, 0.04);
  border-radius: 8px;
  padding: 10px 10px;
}

.pvp-opponents {
  margin-top: 12px;
}

.pvp-opponents-head {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  gap: 10px;
  margin-bottom: 6px;
}

.pvp-opponents-title {
  font-weight: 700;
  opacity: 0.95;
}

.link-btn {
  border: none;
  background: transparent;
  color: #00a6ff;
  cursor: pointer;
  padding: 0;
  font-size: 0.9rem;
}

.opponents-list {
  list-style: none;
  padding: 0;
  margin: 0;
}

.opponent-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 10px;
  padding: 8px 0;
  border-top: 1px solid rgba(255, 255, 255, 0.06);
}

.opp-line {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.opp-name {
  font-weight: 700;
}

.opp-meta {
  font-size: 0.9rem;
  opacity: 0.9;
}

.opp-sep {
  opacity: 0.5;
}

.opponent-row:first-child {
  border-top: none;
}

.opp-wr {
  font-weight: 800;
}

.pvp-total {
  margin-top: 10px;
}

.muted {
  opacity: 0.85;
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

.profile-ad {
  display: flex;
  justify-content: center;
}

@media (max-width: 900px) {
  .profile-overview {
    grid-template-columns: 1fr;
  }

  .meta-value {
    max-width: 72%;
  }

  .kpi-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .pvp-grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 600px) {
  .kpi-grid {
    grid-template-columns: 1fr;
  }

  .identity-head {
    align-items: flex-start;
  }

  .profile-title {
    font-size: 1.45rem;
  }

  .meta-line {
    flex-direction: column;
    align-items: flex-start;
    gap: 2px;
  }

  .meta-value {
    max-width: 100%;
    text-align: left;
  }

  .identity-links {
    justify-content: flex-start;
  }
}
</style>
