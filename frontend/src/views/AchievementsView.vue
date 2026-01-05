<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import type { Achievement } from '@/types/achievement'
import { fetchAchievements } from '@/api/achievementApi'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()

const loading = ref(false)
const error = ref<string | null>(null)
const achievements = ref<Achievement[]>([])

const filterStatus = ref<'all' | 'unlocked' | 'locked'>('all')

const isAuthenticated = computed(() => !!auth.user)

const unlockedCount = computed(() => achievements.value.filter(a => a.unlocked).length)
const totalCount = computed(() => achievements.value.length)

const filteredAchievements = computed(() => {
  if (filterStatus.value === 'unlocked') return achievements.value.filter(a => a.unlocked)
  if (filterStatus.value === 'locked') return achievements.value.filter(a => !a.unlocked)
  return achievements.value
})

function formatGameLabel(game: string | null) {
  if (!game) return ""
  const g = game.toLowerCase()
  if (g === 'kcdle') return 'KCDLE'
  if (g === 'lecdle') return 'LECDLE'
  if (g === 'lfldle') return 'LFLDLE'
  return "";
}

function formatUnlockedAt(dateIso: string | null) {
  if (!dateIso) return ''
  const d = new Date(dateIso)
  if (Number.isNaN(d.getTime())) return ''
  return d.toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' })
}

function formatPercentage(value: number | null | undefined) {
  if (value == null) return '0 %'
  return `${value.toFixed(1)} %`
}

async function load() {
  loading.value = true
  error.value = null
  achievements.value = []
  try {
    const data = await fetchAchievements()
    achievements.value = data.data
  } catch (e) {
    error.value = 'Impossible de charger les succès pour le moment.'
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="achievements-page">
    <header class="achievements-header">
      <div class="header-main">
        <h1>Succès</h1>
        <p v-if="isAuthenticated">Gagne des parties, enchaîne les streaks et débloque tous les succès KCDLE.</p>
        <p v-else>Connecte-toi pour commencer à débloquer des succès sur KCDLE.</p>
      </div>

      <div class="header-stats" v-if="totalCount > 0">
        <div class="stat">
          <span class="stat-label">Succès débloqués</span>
          <span class="stat-value">{{ unlockedCount }} / {{ totalCount }}</span>
        </div>
      </div>
    </header>

    <section class="achievements-filters">
      <button type="button" class="filter-btn" :class="{ active: filterStatus === 'all' }" @click="filterStatus = 'all'">Tous</button>
      <button type="button" class="filter-btn" :class="{ active: filterStatus === 'unlocked' }" @click="filterStatus = 'unlocked'">Débloqués</button>
      <button type="button" class="filter-btn" :class="{ active: filterStatus === 'locked' }" @click="filterStatus = 'locked'">À débloquer</button>
    </section>

    <main class="achievements-main">
      <div v-if="loading" class="state-box">Chargement des succès...</div>
      <div v-else-if="error" class="state-box state-box--error">{{ error }}</div>
      <div v-else-if="!filteredAchievements.length" class="state-box">Aucun succès à afficher pour ce filtre.</div>

      <div v-else class="achievements-grid">
        <article
          v-for="ach in filteredAchievements"
          :key="ach.id"
          class="achievement-card"
          :class="{ 'achievement-card--locked': !ach.unlocked }"
        >
          <div class="achievement-inner">
            <div class="achievement-header">
              <div class="achievement-title-zone">
                <h2 class="achievement-title">{{ ach.name }}</h2>
                <div class="achievement-game">{{ formatGameLabel(ach.game) }}</div>
              </div>
            </div>

            <div class="achievement-middle">
              <p v-if="ach.unlocked" class="achievement-description">{{ ach.description }}</p>
            </div>

            <div class="achievement-footer">
              <p class="achievement-percentage">Débloqué par {{ formatPercentage(ach.unlocked_percentage) }} des joueurs</p>
              <p v-if="ach.unlocked_at" class="achievement-date">Obtenu le {{ formatUnlockedAt(ach.unlocked_at) }}</p>
            </div>
          </div>

          <div v-if="!ach.unlocked" class="achievement-lock-overlay">
            <div class="lock-circle">🔒</div>
          </div>
        </article>
      </div>
    </main>
  </div>
</template>

<style scoped>
.achievements-page {
  min-height: 100vh;
  padding: 20px 12px 28px;
  display: flex;
  flex-direction: column;
  align-items: center;
  color: #f3f3f3;
  background: radial-gradient(circle at top, #20263a 0, #05060a 75%);
}

.achievements-header {
  width: 100%;
  max-width: 1100px;
  display: flex;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 12px;
}

.header-main h1 {
  margin: 0;
  font-size: 1.6rem;
}

.header-main p {
  margin: 4px 0 0;
  font-size: 0.9rem;
  opacity: 0.9;
}

.header-stats {
  display: flex;
  align-self: start;
  gap: 10px;
}

.stat {
  padding: 6px 10px;
  border-radius: 10px;
  background: rgba(10, 12, 20, 0.95);
  border: 1px solid rgba(255, 255, 255, 0.16);
  text-align: right;
}

.stat-label {
  display: block;
  font-size: 0.7rem;
  opacity: 0.8;
}

.stat-value {
  display: block;
  font-size: 1rem;
  font-weight: 600;
}

.achievements-filters {
  width: 100%;
  max-width: 1100px;
  margin: 8px 0 16px;
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

.filter-btn {
  border: none;
  border-radius: 999px;
  padding: 6px 14px;
  background: rgba(255, 255, 255, 0.06);
  color: #f7f7f7;
  font-size: 0.85rem;
  cursor: pointer;
}

.filter-btn.active {
  background: #00a6ff;
  color: white;
}

.filter-btn:not(.active):hover {
  background: rgba(255, 255, 255, 0.12);
}

.achievements-main {
  width: 100%;
  max-width: 1100px;
}

.state-box {
  padding: 12px 10px;
  border-radius: 8px;
  background: rgba(10, 12, 20, 0.9);
  border: 1px solid rgba(255, 255, 255, 0.06);
  font-size: 0.9rem;
}

.state-box--error {
  color: #ff6b6b;
}

.achievements-grid {
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  gap: 10px;
}

.achievement-card {
  position: relative;
  border-radius: 12px;
  background: rgba(10, 12, 20, 0.98);
  border: 1px solid rgba(0, 166, 255, 0.35);
  height: 200px;
  display: flex;
  overflow: hidden;
}

.achievement-card--locked {
  border-color: rgba(255, 255, 255, 0.16);
}

.achievement-inner {
  display: grid;
  grid-template-rows: auto 1fr auto;
  padding: 10px;
  width: 100%;
}

.achievement-header {
  display: flex;
  justify-content: center;
}

.achievement-title {
  margin: 0;
  font-size: 0.9rem;
}

.achievement-game {
  margin-top: 1px;
  font-size: 0.7rem;
  opacity: 0.8;
}

.achievement-middle {
  display: flex;
  align-items: center;
  justify-content: center;
  text-align: center;
  padding: 4px;
}

.achievement-description {
  margin: 0;
  font-size: 0.8rem;
  line-height: 1.3;
}

.achievement-footer {
  display: flex;
  justify-content: space-between;
  font-size: 0.7rem;
  opacity: 0.9;
}

.achievement-lock-overlay {
  position: absolute;
  inset: 0;
  display: flex;
  justify-content: center;
  align-items: center;
  background: radial-gradient(circle, rgba(0,0,0,0.55) 0, rgba(0,0,0,0.25) 45%, transparent 80%);
  pointer-events: none;
}

.lock-circle {
  width: 40px;
  height: 40px;
  border-radius: 999px;
  border: 1px solid rgba(255,255,255,0.8);
  background: rgba(10,12,20,0.9);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.1rem;
}

@media (max-width: 1200px) {
  .achievements-grid {
    grid-template-columns: repeat(4, 1fr);
  }
}

@media (max-width: 950px) {
  .achievements-grid {
    grid-template-columns: repeat(3, 1fr);
  }
}

@media (max-width: 700px) {
  .achievements-header {
    flex-direction: column;
    align-items: flex-start;
  }
  .achievements-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 430px) {
  .achievements-grid {
    grid-template-columns: 1fr;
  }
}
</style>
