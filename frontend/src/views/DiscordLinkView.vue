<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { fetchDiscordAuthUrl } from '@/api/discordAuthApi'
import { handleError } from '@/utils/handleError'
import { useFlashStore } from '@/stores/flash'

const RETURN_TO_STORAGE_KEY = 'kcdle_discord_return_to'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const flash = useFlashStore()

const loading = ref(true)
const error = ref<string | null>(null)

const returnTo = computed(() => {
  const raw = String(route.query.return_to ?? '/profile')
  if (!raw.startsWith('/')) return '/profile'
  return raw
})

onMounted(async () => {
  try {
    error.value = null
    loading.value = true

    if (!auth.isAuthenticated) {
      await router.replace({
        name: 'login',
        query: {
          redirect: route.fullPath,
        },
      })
      return
    }

    const currentDiscordId = (auth.user as any)?.discord_id
    if (currentDiscordId) {
      flash.success('Ton compte est déjà lié à Discord.', 'Discord')
      await router.replace(returnTo.value)
      return
    }

    sessionStorage.setItem(RETURN_TO_STORAGE_KEY, returnTo.value)

    const data = await fetchDiscordAuthUrl('link')
    if (!data?.url) {
      error.value = 'URL Discord invalide.'
      flash.error('Impossible de démarrer la liaison Discord.', 'Discord')
      return
    }

    window.location.href = data.url
  } catch (e: any) {
    handleError(e, 'Impossible de démarrer la liaison Discord.', 'Discord')
    error.value = 'Impossible de démarrer la liaison Discord.'
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="discord-auth-page">
    <div class="discord-auth-bg" aria-hidden="true"></div>

    <div class="discord-auth-shell">
      <div class="discord-auth-card">
        <div class="discord-auth-head">
          <div class="discord-auth-icon" aria-hidden="true">
            <span class="discord-auth-dot"></span>
            <span class="discord-auth-dot"></span>
            <span class="discord-auth-dot"></span>
          </div>

          <div class="discord-auth-titles">
            <div class="discord-auth-title">Liaison Discord</div>
            <div class="discord-auth-subtitle">Connexion sécurisée • Liaison automatique</div>
          </div>
        </div>

        <div class="discord-auth-body">
          <div v-if="loading" class="discord-auth-state">
            <div class="discord-auth-spinner" aria-hidden="true"></div>
            <div class="discord-auth-text">
              Redirection vers Discord…
              <div class="discord-auth-hint">Ne ferme pas cette page.</div>
            </div>
          </div>

          <div v-else-if="error" class="discord-auth-state discord-auth-state--error">
            <div class="discord-auth-badge discord-auth-badge--error">Erreur</div>
            <div class="discord-auth-text">
              {{ error }}
              <div class="discord-auth-hint">Tu peux réessayer depuis Discord avec /link.</div>
            </div>

            <div class="discord-auth-actions">
              <button class="discord-auth-btn" type="button" @click="router.push({ name: 'profile' })">
                Retour au profil
              </button>
            </div>
          </div>

          <div v-else class="discord-auth-state">
            <div class="discord-auth-spinner" aria-hidden="true"></div>
            <div class="discord-auth-text">
              Redirection…
              <div class="discord-auth-hint">Si rien ne se passe, relance /link.</div>
            </div>
          </div>
        </div>

        <div class="discord-auth-foot">
          <div class="discord-auth-footline"></div>
          <div class="discord-auth-foottext">
            KCDLE • OAuth Discord • Session sécurisée
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.discord-auth-page {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
  color: #f0f0f0;
  background: radial-gradient(circle at top, #20263a 0, #05060a 75%);
}

.discord-auth-bg {
  display: none;
}

.discord-auth-shell {
  width: 100%;
  max-width: 560px;
}

.discord-auth-card {
  background: rgba(10, 12, 20, 0.9);
  border-radius: 8px;
  padding: 14px 16px;
  border: 1px solid rgba(255, 255, 255, 0.06);
  box-shadow: 0 8px 16px rgba(0, 0, 0, 0.35);
}

.discord-auth-card::before,
.discord-auth-card::after {
  content: none;
}

.discord-auth-head {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 12px;
}

.discord-auth-icon {
  width: 42px;
  height: 42px;
  border-radius: 8px;
  background: rgba(255, 255, 255, 0.06);
  border: 1px solid rgba(255, 255, 255, 0.10);
  display: grid;
  place-items: center;
  flex: 0 0 auto;
}

.discord-auth-icon::before {
  content: none;
}

/* Les 3 dots restent, mais sans glow futuriste */
.discord-auth-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: rgba(240, 240, 240, 0.9);
  box-shadow: none;
}

.discord-auth-dot:nth-child(1) { justify-self: start; margin-left: 10px; }
.discord-auth-dot:nth-child(2) { justify-self: center; }
.discord-auth-dot:nth-child(3) { justify-self: end; margin-right: 10px; }

.discord-auth-titles {
  display: flex;
  flex-direction: column;
  gap: 2px;
  flex: 1;
  min-width: 0;
}

.discord-auth-title {
  font-size: 1.15rem;
  font-weight: 800;
  margin: 0;
}

.discord-auth-subtitle {
  font-size: 0.9rem;
  opacity: 0.85;
}

.discord-auth-chip {
  padding: 6px 10px;
  border-radius: 999px;
  font-size: 0.82rem;
  font-weight: 800;
  border: 1px solid rgba(255, 255, 255, 0.12);
  background: rgba(255, 255, 255, 0.08);
  flex: 0 0 auto;
}

.discord-auth-chip--ok {
  border-color: rgba(0, 166, 255, 0.45);
  background: rgba(0, 166, 255, 0.12);
  color: #d9f3ff;
}

.discord-auth-chip--error {
  border-color: rgba(255, 107, 107, 0.45);
  background: rgba(255, 107, 107, 0.10);
  color: rgba(255, 200, 200, 0.95);
}

.discord-auth-body {
  padding: 0;
}

.discord-auth-state {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 12px 12px;
  border-radius: 8px;
  border: 1px solid rgba(255, 255, 255, 0.06);
  background: rgba(15, 18, 28, 0.9);
}

.discord-auth-state--error {
  border-color: rgba(255, 107, 107, 0.25);
  background: rgba(40, 12, 16, 0.35);
}

.discord-auth-spinner {
  width: 20px;
  height: 20px;
  border-radius: 50%;
  border: 2px solid rgba(255, 255, 255, 0.16);
  border-top-color: rgba(0, 166, 255, 0.95);
  animation: spin 0.9s linear infinite;
  flex: 0 0 auto;
  margin-top: 2px;
}

.discord-auth-indicator {
  width: 20px;
  height: 20px;
  border-radius: 6px;
  border: 1px solid rgba(255, 255, 255, 0.10);
  background: rgba(255, 255, 255, 0.06);
  flex: 0 0 auto;
  margin-top: 2px;
}

.discord-auth-indicator--ok {
  border-color: rgba(0, 166, 255, 0.35);
  background: rgba(0, 166, 255, 0.12);
}

.discord-auth-indicator--error {
  border-color: rgba(255, 107, 107, 0.35);
  background: rgba(255, 107, 107, 0.10);
}

.discord-auth-text {
  font-size: 0.95rem;
  line-height: 1.25rem;
  opacity: 0.95;
}

.discord-auth-hint {
  margin-top: 6px;
  font-size: 0.86rem;
  opacity: 0.75;
}

.discord-auth-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 6px 10px;
  border-radius: 999px;
  font-size: 0.82rem;
  font-weight: 800;
  border: 1px solid rgba(255, 255, 255, 0.12);
  background: rgba(255, 255, 255, 0.08);
  margin-right: 10px;
  flex: 0 0 auto;
}

.discord-auth-badge--error {
  border-color: rgba(255, 107, 107, 0.45);
  background: rgba(255, 107, 107, 0.10);
  color: rgba(255, 200, 200, 0.95);
}

.discord-auth-actions {
  margin-top: 12px;
  display: flex;
  justify-content: flex-end;
}

.discord-auth-btn {
  padding: 10px 12px;
  border-radius: 6px;
  border: 1px solid rgba(255, 255, 255, 0.10);
  background: rgba(255, 255, 255, 0.10);
  color: #f0f0f0;
  cursor: pointer;
  font-weight: 800;
  transition: background 0.15s ease, border-color 0.15s ease;
}

.discord-auth-btn:hover {
  background: rgba(255, 255, 255, 0.14);
}

.discord-auth-btn:disabled {
  opacity: 0.65;
  cursor: default;
}

.discord-auth-foot {
  margin-top: 12px;
  opacity: 0.78;
}

.discord-auth-footline {
  height: 1px;
  width: 100%;
  background: rgba(255, 255, 255, 0.08);
  margin-bottom: 10px;
}

.discord-auth-foottext {
  font-size: 0.82rem;
  letter-spacing: 0.2px;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

@media (prefers-reduced-motion: reduce) {
  .discord-auth-spinner {
    animation: none;
  }
  .discord-auth-btn {
    transition: none;
  }
}

@media (max-width: 420px) {
  .discord-auth-page {
    padding: 16px 12px 20px;
  }

  .discord-auth-card {
    padding: 14px 14px;
  }

  .discord-auth-icon {
    width: 40px;
    height: 40px;
  }
}

</style>
