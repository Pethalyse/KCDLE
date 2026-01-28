<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { exchangeDiscordCode } from '@/api/discordAuthApi'
import { useAuthStore } from '@/stores/auth'
import { handleError } from '@/utils/handleError'
import { useFlashStore } from '@/stores/flash'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const flash = useFlashStore()

const loading = ref(true)
const message = ref('Connexion Discord en cours…')
const status = ref<'success' | 'error'>('success')

const RETURN_TO_STORAGE_KEY = 'kcdle_discord_return_to'

function getReturnTo(): string {
  const stored = sessionStorage.getItem(RETURN_TO_STORAGE_KEY)
  if (stored && stored.trim().length > 0) return stored
  return auth.isAuthenticated ? '/profile' : '/'
}

function clearReturnTo(): void {
  sessionStorage.removeItem(RETURN_TO_STORAGE_KEY)
}

onMounted(async () => {
  const code = typeof route.query.code === 'string' ? route.query.code : null
  const state = typeof route.query.state === 'string' ? route.query.state : null

  if (!code || !state) {
    loading.value = false
    status.value = 'error'
    message.value = 'Callback Discord invalide.'
    flash.error('Impossible de finaliser la connexion Discord.', 'Discord')
    return
  }

  try {
    const data: any = await exchangeDiscordCode({ code, state })

    if (data?.token && data?.user) {
      auth.setAuth(data.user, data.token)
      flash.success('Connecté avec Discord.', 'Discord')
    } else if (data?.user) {
      auth.updateUser(data.user)
      flash.success('Compte Discord lié.', 'Discord')
    } else {
      flash.error('Réponse Discord inattendue.', 'Discord')
    }

    status.value = 'success'
    message.value = 'Terminé. Redirection…'
    const returnTo = getReturnTo()
    clearReturnTo()
    await router.replace(returnTo)
  } catch (e: any) {
    handleError(e, 'Impossible de finaliser la connexion Discord.', 'Discord')
    loading.value = false
    status.value = 'error'
    message.value = 'Une erreur est survenue.'
    clearReturnTo()
  }
})
</script>

<template>
  <div class="discord-callback-page">
    <div class="callback-card">
      <div class="callback-title">Discord</div>
      <div class="callback-message">{{ message }}</div>

      <div v-if="!loading && status === 'error'" class="callback-actions">
        <button v-if="auth.isAuthenticated" type="button" class="callback-btn" @click="router.push({ name: 'profile' })">
          Retour au profil
        </button>
        <button v-else type="button" class="callback-btn" @click="router.push({ name: 'home' })">
          Retour à l'accueil
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.discord-callback-page {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
  color: #f0f0f0;
  background: radial-gradient(circle at top, #20263a 0, #05060a 75%);
}

.callback-card {
  width: 100%;
  max-width: 480px;
  background: rgba(10, 12, 20, 0.9);
  border-radius: 12px;
  padding: 18px;
  border: 1px solid rgba(255, 255, 255, 0.08);
}

.callback-title {
  font-size: 1.15rem;
  font-weight: 700;
  margin-bottom: 8px;
}

.callback-message {
  opacity: 0.92;
}

.callback-actions {
  margin-top: 14px;
  display: flex;
  justify-content: flex-end;
}

.callback-btn {
  padding: 10px 12px;
  border-radius: 10px;
  border: none;
  background: rgba(255, 255, 255, 0.12);
  color: #f0f0f0;
  cursor: pointer;
}

.callback-btn:hover {
  background: rgba(255, 255, 255, 0.18);
}
</style>
