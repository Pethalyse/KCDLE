<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useFlashStore } from '@/stores/flash'
import {
  fetchFriendGroups,
  createFriendGroup,
  joinFriendGroup,
  leaveFriendGroup,
  deleteFriendGroup,
} from '@/api/friendGroupApi'
import type {
  FriendGroupSummary,
  FriendGroupCreatePayload,
} from '@/types/friendGroup'

const router = useRouter()
const route = useRoute()
const auth = useAuthStore()
const flash = useFlashStore()

const loading = ref(false)
const error = ref<string | null>(null)
const groups = ref<FriendGroupSummary[]>([])

const creating = ref(false)
const joining = ref(false)

const createForm = reactive<FriendGroupCreatePayload>({
  name: '',
})

const joinForm = reactive<{
  join_code: string
}>({
  join_code: '',
})

const hasGroups = computed(() => groups.value.length > 0)

function ensureAuthenticated() {
  if (!auth.isAuthenticated) {
    const redirectTo = route.fullPath !== '/' ? route.fullPath : '/friends'
    router.push({
      name: 'login',
      query: { redirect: redirectTo },
    })
    return false
  }
  return true
}

async function loadGroups() {
  if (!ensureAuthenticated()) return
  loading.value = true
  error.value = null

  try {
    const data = await fetchFriendGroups()
    groups.value = data.groups
  } catch {
    error.value = "Impossible de charger vos groupes."
  } finally {
    loading.value = false
  }
}

function resetCreateForm() {
  createForm.name = ''
}

function resetJoinForm() {
  joinForm.join_code = ''
}

async function handleCreateGroup() {
  if (!ensureAuthenticated()) return
  if (!createForm.name.trim()) {
    flash.warning('Veuillez saisir un nom pour le groupe.')
    return
  }

  creating.value = true

  try {
    const data = await createFriendGroup({ name: createForm.name.trim() })
    const g = data.group

    groups.value.push({
      id: g.id,
      name: g.name,
      slug: g.slug,
      join_code: g.join_code,
      role: 'owner',
      owner: auth.user ? { id: auth.user.id, name: auth.user.name } : null,
    })

    flash.success('Groupe créé avec succès.', 'Groupes')
    resetCreateForm()
  } catch {
    flash.error("La création du groupe a échoué.", 'Groupes')
  } finally {
    creating.value = false
  }
}

async function handleJoinGroup() {
  if (!ensureAuthenticated()) return
  if (!joinForm.join_code.trim()) {
    flash.warning('Veuillez saisir un code de groupe.')
    return
  }

  joining.value = true

  try {
    const data = await joinFriendGroup(joinForm.join_code.trim())
    const g = data.group

    if (!groups.value.find(x => x.id === g.id)) {
      groups.value.push({
        id: g.id,
        name: g.name,
        slug: g.slug,
        join_code: g.join_code,
        role: 'member',
        owner: null,
      })
    }

    flash.success('Vous avez rejoint le groupe.', 'Groupes')
    resetJoinForm()
  } catch {
    flash.error("Impossible de rejoindre ce groupe.", 'Groupes')
  } finally {
    joining.value = false
  }
}

async function handleLeaveGroup(group: FriendGroupSummary) {
  if (!ensureAuthenticated()) return
  if (!confirm(`Quitter le groupe "${group.name}" ?`)) return

  try {
    await leaveFriendGroup(group.slug)
    groups.value = groups.value.filter(g => g.id !== group.id)
    flash.info('Vous avez quitté ce groupe.', 'Groupes')
  } catch {
    flash.error("Impossible de quitter ce groupe.", 'Groupes')
  }
}

async function handleDeleteGroup(group: FriendGroupSummary) {
  if (!ensureAuthenticated()) return
  if (!confirm(`Supprimer le groupe "${group.name}" pour tous les membres ?`)) return

  try {
    await deleteFriendGroup(group.slug)
    groups.value = groups.value.filter(g => g.id !== group.id)
    flash.success('Le groupe a été supprimé.', 'Groupes')
  } catch {
    flash.error("La suppression du groupe a échoué.", 'Groupes')
  }
}

function goToGroupLeaderboard(group: FriendGroupSummary) {
  router.push({
    name: 'leaderboard_kcdle',
    query: { group: group.slug },
  })
}

async function copyCode(code: string) {
  try {
    await navigator.clipboard.writeText(code)
    flash.info('Code du groupe copié !')
  } catch (e) {
    console.error(e)
  }
}

onMounted(loadGroups)
</script>

<template>
  <div class="friends-page">
    <header class="friends-header">
      <h1>Groupes</h1>
      <p>Crée un groupe, rejoins tes amis et comparez vos scores ensemble.</p>
    </header>

    <main class="friends-main">
      <section class="friends-panel">
        <div class="friends-forms">
          <div class="friends-card">
            <h2>Créer un groupe</h2>
            <p class="friends-card-text">Donne un nom à ton groupe.</p>

            <form class="friends-form" @submit.prevent="handleCreateGroup">
              <label class="friends-label">
                Nom du groupe
                <input
                  v-model="createForm.name"
                  type="text"
                  class="friends-input"
                  placeholder="Ex : Les Cracks du KCDLE"
                  maxlength="20"
                />
              </label>

              <button
                type="submit"
                class="friends-button friends-button-primary"
                :disabled="creating"
              >
                {{ creating ? "Création..." : "Créer le groupe" }}
              </button>
            </form>
          </div>

          <div class="friends-card">
            <h2>Rejoindre un groupe</h2>
            <p class="friends-card-text">Entre le code envoyé par ton ami.</p>

            <form class="friends-form" @submit.prevent="handleJoinGroup">
              <label class="friends-label">
                Code de groupe
                <input
                  v-model="joinForm.join_code"
                  type="text"
                  class="friends-input"
                  placeholder="Ex : ABCD1234"
                />
              </label>

              <button
                type="submit"
                class="friends-button friends-button-secondary"
                :disabled="joining"
              >
                {{ joining ? "Rejoint..." : "Rejoindre le groupe" }}
              </button>
            </form>
          </div>
        </div>

        <section class="friends-groups">
          <h2>Mes groupes</h2>

          <div v-if="loading" class="friends-state">Chargement...</div>
          <div v-else-if="error" class="friends-state friends-state--error">
            {{ error }}
          </div>

          <div v-else-if="!hasGroups" class="friends-state">
            Tu n’as encore aucun groupe d’amis.
          </div>

          <div v-else class="friends-groups-list">
            <article
              v-for="group in groups"
              :key="group.id"
              class="friends-group-card"
            >
              <header class="friends-group-header">
                <h3>{{ group.name }}</h3>
                <span
                  class="friends-group-badge"
                  :class="{
                    'friends-group-badge-owner': group.role === 'owner',
                    'friends-group-badge-member': group.role !== 'owner'
                  }"
                >
                  {{ group.role === 'owner' ? "Propriétaire" : "Membre" }}
                </span>
              </header>

              <div class="friends-group-body">
                <p class="friends-group-code">
                  <span class="friends-group-code-label">Code :</span>
                  <span class="friends-group-code-value" @click="copyCode(group.join_code)">{{ group.join_code }}</span>
                </p>
                <p v-if="group.owner" class="friends-group-owner">
                  Créé par <strong>{{ group.owner.name }}</strong>
                </p>
              </div>

              <footer class="friends-group-footer">
                <button
                  class="friends-button friends-button-link"
                  @click="goToGroupLeaderboard(group)"
                >
                  Voir le classement
                </button>

                <div class="friends-group-actions">
                  <button
                    v-if="group.role === 'owner'"
                    class="friends-button friends-button-danger"
                    @click="handleDeleteGroup(group)"
                  >
                    Supprimer
                  </button>
                  <button
                    v-else
                    class="friends-button friends-button-outline"
                    @click="handleLeaveGroup(group)"
                  >
                    Quitter
                  </button>
                </div>
              </footer>
            </article>
          </div>
        </section>
      </section>
    </main>
  </div>
</template>

<style scoped>
.friends-page {
  min-height: 100vh;
  padding: 24px 14px 32px;
  background: radial-gradient(circle at top, #20263a 0, #05060a 75%);
  color: #f3f3f3;
  display: flex;
  flex-direction: column;
  align-items: center;
}

.friends-header {
  text-align: center;
  margin-bottom: 18px;
  max-width: 900px;
}

.friends-header h1 {
  margin: 0;
  font-size: 1.9rem;
}

.friends-header p {
  margin: 4px 0 10px;
  font-size: 1rem;
  opacity: 0.85;
}

.friends-back {
  margin-top: 4px;
  border: none;
  border-radius: 999px;
  padding: 6px 14px;
  background: rgba(255, 255, 255, 0.08);
  color: #f6f6f6;
  cursor: pointer;
  font-size: 0.86rem;
}

.friends-main {
  width: 100%;
  max-width: 900px;
}

.friends-panel {
  background: rgba(6, 8, 18, 0.92);
  border-radius: 14px;
  padding: 20px 14px;
  box-shadow: 0 12px 28px rgba(0, 0, 0, 0.55);
  border: 1px solid rgba(255, 255, 255, 0.06);
}

.friends-forms {
  margin-bottom: 22px;
  display: flex;
  justify-content: center;
  align-items: stretch;
  gap: 18px;
}

.friends-card {
  max-width: 380px;
  background: rgba(10, 12, 24, 0.95);
  border-radius: 10px;
  padding: 14px 12px 18px;
  border: 1px solid rgba(255, 255, 255, 0.08);
}

.friends-card h2 {
  font-size: 1.2rem;
  margin: 0 0 4px;
  text-align: center;
}

.friends-card-text {
  text-align: center;
  margin-bottom: 10px;
  opacity: 0.9;
  font-size: 0.92rem;
}

.friends-form {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.friends-label {
  display: flex;
  flex-direction: column;
  gap: 4px;
  font-size: 0.88rem;
  color: #dbe3ff;
}

.friends-input {
  border-radius: 8px;
  border: 1px solid rgba(185, 199, 255, 0.6);
  background: #080a16;
  padding: 7px 9px;
  font-size: 0.92rem;
  color: #f3f3f3;
}

.friends-input:focus {
  border-color: #00a6ff;
}

.friends-button {
  border-radius: 999px;
  padding: 7px 12px;
  font-size: 0.9rem;
  font-weight: 600;
  cursor: pointer;
  border: none;
}

.friends-button-primary {
  background: #00a6ff;
  color: #050713;
}

.friends-button-secondary {
  background: rgba(255, 255, 255, 0.08);
  color: #f6f6f6;
}

.friends-groups {
  margin-top: 12px;
  margin-left: auto;
  margin-right: auto;
}

.friends-groups h2 {
  text-align: center;
  margin-bottom: 10px;
}

.friends-state {
  text-align: center;
  opacity: 0.9;
}

.friends-state--error {
  color: #ffb6b6;
}

.friends-groups-list {
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  gap: 10px;
  margin-top: 6px;
}

.friends-group-card {
  background: rgba(10, 12, 24, 0.95);
  border-radius: 10px;
  padding: 12px;
  border: 1px solid rgba(185, 199, 255, 0.4);
}

.friends-group-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.friends-group-header h3 {
  margin: 0;
  font-size: 1.05rem;
}

.friends-group-badge {
  padding: 3px 9px;
  border-radius: 999px;
  font-size: 0.75rem;
}

.friends-group-badge-owner {
  background: rgba(122, 92, 255, 0.22);
  border: 1px solid rgba(183, 159, 255, 0.7);
}

.friends-group-badge-member {
  background: rgba(82, 165, 255, 0.18);
  border: 1px solid rgba(124, 190, 255, 0.7);
}

.friends-group-body {
  margin-top: 6px;
  font-size: 0.92rem;
}

.friends-group-code {
  display: flex;
  gap: 4px;
  margin-bottom: 4px;
}

.friends-group-code-label {
  color: #b9c7ff;
  font-weight: 600;
}

.friends-group-code-value {
  background: #080a16;
  border: 1px dashed rgba(151, 175, 255, 0.7);
  padding: 2px 7px;
  border-radius: 8px;
  cursor: pointer;
}

.friends-group-code-value:hover{
  background: rgba(255, 255, 255, 0.16);
  transform: translateY(-1px);
}

.friends-group-footer {
  display: flex;
  justify-content: space-between;
  margin-top: 8px;
}

.friends-group-actions {
  display: flex;
  gap: 6px;
}

.friends-button-link {
  background: transparent;
  color: #00a6ff;
  padding-left: 0;
  padding-right: 0;
}

.friends-button-outline {
  background: transparent;
  border: 1px solid rgba(185, 199, 255, 0.7);
  color: #f3f3f3;
}

.friends-button-danger {
  background: rgba(255, 66, 66, 0.25);
  border: 1px solid rgba(255, 140, 140, 0.8);
  color: #ffe5e5;
}

@media (max-width: 1200px) {
  .friends-groups-list {
    grid-template-columns: repeat(4, 1fr);
  }
}

@media (max-width: 950px) {
  .friends-groups-list {
    grid-template-columns: repeat(3, 1fr);
  }
}

@media (max-width: 800px) {
  .friends-forms {
    flex-direction: column;
  }

  .friends-card {
    max-width: 100%;
  }

  .friends-groups-list {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 430px) {
  .friends-groups-list {
    grid-template-columns: 1fr;
  }
}
</style>
