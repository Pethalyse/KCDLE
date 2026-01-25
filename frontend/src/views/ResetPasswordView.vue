<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import SimpleImg from '@/components/SimpleImg.vue'
import { useAuthStore } from '@/stores/auth'
import { useFlashStore } from '@/stores/flash'
import { handleError } from '@/utils/handleError'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const flash = useFlashStore()

const token = computed(() => (typeof route.query.token === 'string' ? route.query.token : ''))
const email = computed(() => (typeof route.query.email === 'string' ? route.query.email : ''))

const form = reactive({
  password: '',
  password_confirmation: '',
})

const fieldErrors = reactive<{ password?: string; password_confirmation?: string; token?: string }>({})
const submitting = ref(false)
const showPassword = ref(false)
const showPasswordConfirm = ref(false)

function resetErrors() {
  fieldErrors.password = undefined
  fieldErrors.password_confirmation = undefined
  fieldErrors.token = undefined
}

async function handleSubmit() {
  if (submitting.value) return

  resetErrors()
  submitting.value = true

  try {
    await auth.resetPassword({
      token: token.value,
      email: email.value,
      password: form.password,
      password_confirmation: form.password_confirmation,
    })

    flash.success('Mot de passe réinitialisé. Tu peux te connecter.', 'Réinitialisation')
    await router.push({ name: 'login' })
  } catch (error: any) {
    const status = error?.response?.status
    const data = error?.response?.data

    if (status === 422 && data?.errors) {
      if (Array.isArray(data.errors.password)) fieldErrors.password = data.errors.password[0]
      if (Array.isArray(data.errors.password_confirmation)) fieldErrors.password_confirmation = data.errors.password_confirmation[0]
      if (Array.isArray(data.errors.token)) fieldErrors.token = data.errors.token[0]

      const first = fieldErrors.token || fieldErrors.password || fieldErrors.password_confirmation || (typeof data?.message === 'string' ? data.message : null)
      flash.error(first || 'Impossible de réinitialiser le mot de passe.', 'Réinitialisation')
      return
    }

    handleError(error)
  } finally {
    submitting.value = false
  }
}

function goHome() {
  void router.push({ name: 'home' })
}

onMounted(() => {
  if (!token.value || !email.value) {
    flash.error('Lien de réinitialisation invalide.', 'Réinitialisation')
  }
})

watch(
  () => [form.password, form.password_confirmation],
  () => {
    fieldErrors.password = undefined
    fieldErrors.password_confirmation = undefined
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
          <h1 class="auth-title">Nouveau mot de passe</h1>
          <p class="auth-subtitle">Choisis un nouveau mot de passe pour ton compte.</p>
        </div>

        <div v-if="!token || !email" class="auth-alert">
          <div class="auth-alert-title">Lien invalide</div>
          <div class="auth-alert-text">Le lien de réinitialisation est incomplet ou a expiré.</div>
          <RouterLink class="auth-link" :to="{ name: 'forgot_password' }">Demander un nouveau lien</RouterLink>
        </div>

        <form v-else class="auth-form" @submit.prevent="handleSubmit">
          <div class="auth-field">
            <label for="password">Mot de passe</label>
            <div class="auth-password">
              <input
                id="password"
                v-model="form.password"
                :type="showPassword ? 'text' : 'password'"
                autocomplete="new-password"
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

          <div class="auth-field">
            <label for="password_confirmation">Confirmer le mot de passe</label>
            <div class="auth-password">
              <input
                id="password_confirmation"
                v-model="form.password_confirmation"
                :type="showPasswordConfirm ? 'text' : 'password'"
                autocomplete="new-password"
                required
                :disabled="submitting"
                placeholder="Confirmer le mot de passe"
              />
              <button
                type="button"
                class="auth-password-toggle"
                :disabled="submitting"
                :aria-label="showPasswordConfirm ? 'Masquer le mot de passe' : 'Afficher le mot de passe'"
                @click="showPasswordConfirm = !showPasswordConfirm"
              >
                {{ showPasswordConfirm ? 'Masquer' : 'Afficher' }}
              </button>
            </div>
            <p v-if="fieldErrors.password_confirmation" class="auth-error">{{ fieldErrors.password_confirmation }}</p>
            <p v-if="fieldErrors.token" class="auth-error">{{ fieldErrors.token }}</p>
          </div>

          <button type="submit" class="auth-submit" :disabled="submitting">
            <span v-if="!submitting">Réinitialiser</span>
            <span v-else>En cours…</span>
          </button>
        </form>

        <div class="auth-footer">
          <div class="auth-footer-text">
            <RouterLink :to="{ name: 'login' }">Retour à la connexion</RouterLink>
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

.auth-link {
  display: inline-block;
  color: #00a6ff;
  text-decoration: none;
  font-weight: 800;
}

.auth-link:hover {
  text-decoration: underline;
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
