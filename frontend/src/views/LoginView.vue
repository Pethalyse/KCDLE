<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import SimpleImg from '@/components/SimpleImg.vue'
import Credit from '@/components/Credit.vue'
import { useAuthStore } from '@/stores/auth'
import { useFlashStore } from '@/stores/flash'
import { handleError } from '@/utils/handleError'

const router = useRouter()
const route = useRoute()
const auth = useAuthStore()
const flash = useFlashStore()

const form = reactive({
  email: '',
  password: '',
})

const fieldErrors = reactive<{
  email?: string
  password?: string
}>({})

const submitting = ref(false)
const unverifiedEmail = ref<string | null>(null)
const resendLoading = ref(false)
const showPassword = ref(false)

function getSafeRedirect(v: unknown): string {
  if (typeof v !== 'string') return '/'
  if (!v.startsWith('/')) return '/'
  return v
}

const redirectTo = computed(() => getSafeRedirect(route.query.redirect))

function resetErrors() {
  fieldErrors.email = undefined
  fieldErrors.password = undefined
  unverifiedEmail.value = null
}

async function handleSubmit() {
  if (submitting.value) return

  resetErrors()
  submitting.value = true

  try {
    const data = await auth.login({
      email: form.email,
      password: form.password,
    })

    if (data && Array.isArray(data.unlocked_achievements) && data.unlocked_achievements.length > 0) {
      data.unlocked_achievements.forEach((achievement: any) => {
        if (!achievement || !achievement.name) return
        flash.push('success', achievement.name, 'Succès débloqué')
      })
    }

    flash.success('Connexion réussie.', 'Bienvenue sur KCDLE')
    await router.push(redirectTo.value)
  } catch (error: any) {
    const status = error?.response?.status
    const data = error?.response?.data

    if (status === 403 && data?.code === 'email_not_verified') {
      unverifiedEmail.value = form.email
      flash.warning(
        'Ton adresse e-mail n’est pas encore vérifiée. Vérifie ta boîte mail ou renvoie un e-mail de validation.',
        'Adresse e-mail non vérifiée',
      )
      return
    }

    if (status === 422) {
      if (data?.errors) {
        if (Array.isArray(data.errors.email)) fieldErrors.email = data.errors.email[0]
        if (Array.isArray(data.errors.password)) fieldErrors.password = data.errors.password[0]

        const first = fieldErrors.email || fieldErrors.password || (typeof data?.message === 'string' ? data.message : null)
        flash.error(first || 'Impossible de se connecter.', 'Connexion')
        return
      }

      if (typeof data?.message === 'string') {
        flash.error(data.message, 'Connexion')
        return
      }
    }

    if (status === 401) {
      flash.error('Identifiants invalides.', 'Connexion')
      return
    }

    handleError(error)
  } finally {
    submitting.value = false
  }
}

async function resendVerification() {
  if (!unverifiedEmail.value || resendLoading.value) return

  resendLoading.value = true
  try {
    const data = await auth.resendEmailVerification({ email: unverifiedEmail.value })
    if (data?.code === 'already_verified') {
      flash.success('Ton adresse e-mail est déjà vérifiée. Tu peux te connecter.', 'Adresse e-mail vérifiée')
    } else {
      flash.success('E-mail de vérification envoyé. Pense à regarder tes spams.', 'E-mail envoyé')
    }
  } catch (error) {
    handleError(error)
  } finally {
    resendLoading.value = false
  }
}

function goHome() {
  void router.push({ name: 'home' })
}

onMounted(() => {
  const token = typeof route.query.token === 'string' ? route.query.token : null

  if (token) {
    void (async () => {
      try {
        await auth.loginWithToken(token)
        flash.success('Adresse e-mail vérifiée. Connexion effectuée.', 'Validation réussie')
        await router.push(redirectTo.value)
      } catch (error) {
        handleError(error)
      }
    })()
    return
  }

  if (route.query.verified === '1') flash.success('Adresse e-mail vérifiée. Tu peux te connecter.', 'Validation réussie')
})

watch(
  () => [form.email, form.password],
  () => {
    fieldErrors.email = undefined
    fieldErrors.password = undefined
  },
)
</script>

<template>
  <div class="auth-page">
    <header class="auth-header">
      <div class="auth-logo">
        <SimpleImg class="logo" alt="KCDLE" img="HOMEDLE_Header-rbg.png" @onclick="goHome" />
      </div>
    </header>

    <main class="auth-main">
      <section class="auth-card">
        <div class="auth-head">
          <h1 class="auth-title">Connexion</h1>
          <p class="auth-subtitle">Connecte-toi pour accéder à ton profil.</p>
        </div>

        <form class="auth-form" @submit.prevent="handleSubmit">
          <div class="auth-field">
            <label for="email">Adresse e-mail</label>
            <input
              id="email"
              v-model.trim="form.email"
              type="email"
              autocomplete="email"
              required
              :disabled="submitting"
              placeholder="Adresse e-mail"
            />
            <p v-if="fieldErrors.email" class="auth-error">{{ fieldErrors.email }}</p>
          </div>

          <div class="auth-field">
            <label for="password">Mot de passe</label>
            <div class="auth-password">
              <input
                id="password"
                v-model="form.password"
                :type="showPassword ? 'text' : 'password'"
                autocomplete="current-password"
                required
                :disabled="submitting"
                placeholder="Mot de passe"
              />
              <button
                type="button"
                class="auth-password-toggle"
                :disabled="submitting"
                :aria-label="showPassword ? 'Masquer le mot de passe' : 'Afficher le mot de passe'"
                @click="showPassword = !showPassword"
              >
                {{ showPassword ? 'Masquer' : 'Afficher' }}
              </button>
            </div>
            <p v-if="fieldErrors.password" class="auth-error">{{ fieldErrors.password }}</p>
          </div>

          <div v-if="unverifiedEmail" class="auth-alert">
            <div class="auth-alert-title">Adresse e-mail non vérifiée</div>
            <div class="auth-alert-text">Tu dois valider ton e-mail pour te connecter.</div>
            <button type="button" class="auth-secondary" :disabled="resendLoading" @click="resendVerification">
              <span v-if="!resendLoading">Renvoyer l’e-mail</span>
              <span v-else>Envoi en cours…</span>
            </button>
          </div>

          <button type="submit" class="auth-submit" :disabled="submitting">
            <span v-if="!submitting">Se connecter</span>
            <span v-else>Connexion en cours…</span>
          </button>
        </form>

        <div class="auth-footer">
          <div class="auth-footer-text">
            Pas encore de compte ?
            <RouterLink :to="{ name: 'register', query: route.query.redirect ? { redirect: route.query.redirect } : {} }">
              Créer un compte
            </RouterLink>
          </div>
        </div>
      </section>
    </main>
  </div>
</template>

<style scoped>
.auth-page {
  padding: 20px;
  color: #f0f0f0;
  background: radial-gradient(circle at top, #20263a 0, #05060a 75%);
  min-height: 100vh;
  font-size: 0.95rem;
}

.auth-header {
  display: flex;
  justify-content: center;
  margin-bottom: 14px;
}

.auth-logo .logo {
  width: auto;
  max-width: 320px;
  cursor: pointer;
  filter: drop-shadow(0 0 6px rgba(0, 0, 0, 0.3));
}

.auth-main {
  max-width: 520px;
  margin: 0 auto;
}

.auth-card {
  background: rgba(10, 12, 20, 0.9);
  border-radius: 8px;
  padding: 14px 16px;
  border: 1px solid rgba(255, 255, 255, 0.06);
  box-shadow: 0 8px 16px rgba(0, 0, 0, 0.35);
}

.auth-card,
.auth-card * {
  box-sizing: border-box;
}

.auth-head {
  margin-bottom: 12px;
}

.auth-title {
  margin: 0 0 6px;
  font-size: 1.6rem;
  text-align: center;
}

.auth-subtitle {
  margin: 0;
  opacity: 0.85;
  text-align: center;
  font-size: 0.95rem;
}

.auth-form {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.auth-field label {
  display: block;
  margin: 0 0 6px;
  font-weight: 700;
  opacity: 0.9;
}

.auth-field input {
  width: 100%;
  display: block;
  padding: 10px 10px;
  border-radius: 6px;
  border: 1px solid rgba(255, 255, 255, 0.12);
  background: rgba(15, 18, 28, 0.9);
  color: #f3f3f3;
  font-size: 0.95rem;
}

.auth-password {
  position: relative;
}

.auth-password input {
  padding-right: 96px;
}

.auth-password-toggle {
  position: absolute;
  right: 8px;
  top: 50%;
  transform: translateY(-50%);
  padding: 6px 8px;
  border-radius: 6px;
  border: 1px solid rgba(255, 255, 255, 0.16);
  background: rgba(0, 0, 0, 0.25);
  color: #f3f3f3;
  cursor: pointer;
  font-size: 0.82rem;
  font-weight: 800;
}

.auth-password-toggle:hover:not(:disabled) {
  background: rgba(0, 0, 0, 0.35);
}

.auth-password-toggle:disabled {
  opacity: 0.65;
  cursor: default;
}

.auth-field input:focus {
  outline: none;
  border-color: rgba(0, 166, 255, 0.7);
  box-shadow: 0 0 0 3px rgba(0, 166, 255, 0.18);
}

.auth-error {
  margin: 6px 0 0;
  color: #ffb4b4;
  font-weight: 600;
  font-size: 0.86rem;
}

.auth-alert {
  border-radius: 8px;
  padding: 10px 10px;
  background: rgba(251, 191, 36, 0.08);
  border: 1px solid rgba(251, 191, 36, 0.25);
}

.auth-alert-title {
  font-weight: 800;
  margin-bottom: 6px;
}

.auth-alert-text {
  opacity: 0.9;
  margin-bottom: 10px;
  font-size: 0.92rem;
}

.auth-submit {
  width: 100%;
  padding: 10px 10px;
  border-radius: 6px;
  border: 1px solid #00a6ff;
  background: rgba(0, 166, 255, 0.15);
  color: #00a6ff;
  cursor: pointer;
  font-size: 0.95rem;
  font-weight: 800;
  transition: background 0.15s ease;
}

.auth-submit:hover:not(:disabled) {
  background: rgba(0, 166, 255, 0.25);
}

.auth-submit:disabled {
  opacity: 0.65;
  cursor: default;
}

.auth-secondary {
  width: 100%;
  padding: 10px 10px;
  border-radius: 6px;
  border: 1px solid rgba(251, 191, 36, 0.5);
  background: transparent;
  color: #f3f3f3;
  cursor: pointer;
  font-size: 0.92rem;
  font-weight: 700;
  transition: background 0.15s ease;
}

.auth-secondary:hover:not(:disabled) {
  background: rgba(251, 191, 36, 0.12);
}

.auth-secondary:disabled {
  opacity: 0.7;
  cursor: default;
}

.auth-footer {
  margin-top: 12px;
}

.auth-footer-text {
  text-align: center;
  opacity: 0.85;
}

.auth-footer-text a {
  color: #00a6ff;
  text-decoration: none;
  font-weight: 800;
}

.auth-footer-text a:hover {
  text-decoration: underline;
}

@media (max-width: 420px) {
  .auth-page {
    padding: 16px 12px 20px;
  }

  .auth-logo .logo {
    max-width: 260px;
  }
}
</style>
