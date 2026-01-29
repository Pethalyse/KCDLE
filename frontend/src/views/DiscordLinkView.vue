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
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 22px;
  color: #f0f0f0;
  background: radial-gradient(circle at top, #20263a 0, #05060a 75%);
  overflow: hidden;
}

.discord-auth-bg {
  position: absolute;
  inset: 0;
  pointer-events: none;
  opacity: 0.75;
  background:
    radial-gradient(circle at 18% 14%, rgba(88, 101, 242, 0.18), transparent 48%),
    radial-gradient(circle at 82% 22%, rgba(255, 255, 255, 0.06), transparent 52%),
    radial-gradient(circle at 50% 92%, rgba(88, 101, 242, 0.10), transparent 55%);
}

.discord-auth-bg::before {
  content: '';
  position: absolute;
  inset: -2px;
  opacity: 0.25;
  background-image:
    linear-gradient(rgba(255, 255, 255, 0.06) 1px, transparent 1px),
    linear-gradient(90deg, rgba(255, 255, 255, 0.06) 1px, transparent 1px);
  background-size: 44px 44px;
  transform: perspective(900px) rotateX(58deg) translateY(-18%);
  transform-origin: top;
  filter: blur(0.1px);
}

.discord-auth-shell {
  width: 100%;
  max-width: 560px;
  position: relative;
  z-index: 1;
}

.discord-auth-card {
  position: relative;
  background: rgba(10, 12, 20, 0.92);
  border-radius: 14px;
  padding: 16px 16px 14px;
  border: 1px solid rgba(255, 255, 255, 0.08);
  box-shadow:
    0 18px 40px rgba(0, 0, 0, 0.42),
    0 0 0 1px rgba(88, 101, 242, 0.06) inset;
  overflow: hidden;
}

.discord-auth-card::before {
  content: '';
  position: absolute;
  inset: -2px;
  background: linear-gradient(120deg, rgba(88, 101, 242, 0.35), rgba(255, 255, 255, 0.08), rgba(88, 101, 242, 0.22));
  opacity: 0.55;
  filter: blur(16px);
  pointer-events: none;
}

.discord-auth-card::after {
  content: '';
  position: absolute;
  inset: 0;
  background: radial-gradient(circle at 14% 0%, rgba(88, 101, 242, 0.18), transparent 40%);
  pointer-events: none;
}

.discord-auth-head {
  display: flex;
  align-items: center;
  gap: 12px;
  position: relative;
  z-index: 1;
  padding: 6px 6px 10px;
}

.discord-auth-icon {
  width: 44px;
  height: 44px;
  border-radius: 12px;
  background: rgba(88, 101, 242, 0.18);
  border: 1px solid rgba(88, 101, 242, 0.45);
  box-shadow:
    0 10px 24px rgba(0, 0, 0, 0.35),
    0 0 0 1px rgba(255, 255, 255, 0.04) inset;
  display: grid;
  place-items: center;
  position: relative;
  overflow: hidden;
}

.discord-auth-icon::before {
  content: '';
  position: absolute;
  inset: -40%;
  background: linear-gradient(135deg, rgba(255, 255, 255, 0.16), transparent 55%);
  transform: rotate(20deg);
  opacity: 0.65;
}

.discord-auth-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: rgba(223, 227, 255, 0.95);
  box-shadow: 0 0 12px rgba(88, 101, 242, 0.65);
}

.discord-auth-dot:nth-child(1) { justify-self: start; margin-left: 10px; }
.discord-auth-dot:nth-child(2) { justify-self: center; }
.discord-auth-dot:nth-child(3) { justify-self: end; margin-right: 10px; }

.discord-auth-titles {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.discord-auth-title {
  font-size: 1.2rem;
  font-weight: 900;
  letter-spacing: 0.2px;
}

.discord-auth-subtitle {
  font-size: 0.9rem;
  opacity: 0.82;
}

.discord-auth-body {
  position: relative;
  z-index: 1;
  padding: 10px 6px 8px;
}

.discord-auth-state {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 12px 12px;
  border-radius: 12px;
  border: 1px solid rgba(255, 255, 255, 0.08);
  background: rgba(15, 18, 28, 0.72);
}

.discord-auth-state--error {
  border-color: rgba(255, 90, 90, 0.25);
  background: rgba(40, 12, 16, 0.45);
}

.discord-auth-spinner {
  width: 22px;
  height: 22px;
  border-radius: 50%;
  border: 2px solid rgba(223, 227, 255, 0.18);
  border-top-color: rgba(88, 101, 242, 0.95);
  box-shadow: 0 0 14px rgba(88, 101, 242, 0.35);
  animation: spin 0.9s linear infinite;
  flex: 0 0 auto;
  margin-top: 2px;
}

.discord-auth-text {
  font-size: 0.95rem;
  line-height: 1.25rem;
  opacity: 0.96;
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
  font-weight: 900;
  letter-spacing: 0.2px;
  border: 1px solid rgba(255, 255, 255, 0.12);
  background: rgba(255, 255, 255, 0.08);
  margin-right: 10px;
}

.discord-auth-badge--error {
  border-color: rgba(255, 90, 90, 0.35);
  background: rgba(255, 90, 90, 0.10);
  color: rgba(255, 200, 200, 0.95);
}

.discord-auth-actions {
  margin-top: 12px;
  display: flex;
  justify-content: flex-end;
}

.discord-auth-btn {
  padding: 10px 12px;
  border-radius: 10px;
  border: 1px solid rgba(255, 255, 255, 0.10);
  background: rgba(255, 255, 255, 0.10);
  color: #f0f0f0;
  cursor: pointer;
  font-weight: 800;
  transition: background 0.15s ease, transform 0.15s ease, border-color 0.15s ease;
}

.discord-auth-btn:hover {
  background: rgba(255, 255, 255, 0.14);
  border-color: rgba(88, 101, 242, 0.25);
  transform: translateY(-1px);
}

.discord-auth-btn:active {
  transform: translateY(0);
}

.discord-auth-foot {
  position: relative;
  z-index: 1;
  padding: 10px 6px 2px;
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
</style>
