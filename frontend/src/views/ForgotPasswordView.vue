<script setup lang="ts">
import { reactive, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import SimpleImg from '@/components/SimpleImg.vue'
import { useAuthStore } from '@/stores/auth'
import { useFlashStore } from '@/stores/flash'
import { handleError } from '@/utils/handleError'

const router = useRouter()
const auth = useAuthStore()
const flash = useFlashStore()

const form = reactive({
  email: '',
})

const fieldErrors = reactive<{ email?: string }>({})
const submitting = ref(false)
const sent = ref(false)

function resetErrors() {
  fieldErrors.email = undefined
}

async function handleSubmit() {
  if (submitting.value) return

  resetErrors()
  submitting.value = true

  try {
    await auth.forgotPassword({ email: form.email })
    sent.value = true
    flash.success('Si un compte existe avec cette adresse, un lien a été envoyé.', 'E-mail envoyé')
  } catch (error: any) {
    const status = error?.response?.status
    const data = error?.response?.data

    if (status === 422 && data?.errors && Array.isArray(data.errors.email)) {
      fieldErrors.email = data.errors.email[0]
      flash.error(fieldErrors.email || 'Impossible d’envoyer l’e-mail.', 'Mot de passe oublié')
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

watch(
  () => form.email,
  () => {
    fieldErrors.email = undefined
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
          <h1 class="auth-title">Mot de passe oublié</h1>
          <p class="auth-subtitle">Entre ton e-mail pour recevoir un lien de réinitialisation.</p>
        </div>

        <div v-if="sent" class="auth-alert success">
          <div class="auth-alert-title">E-mail envoyé</div>
          <div class="auth-alert-text">Si un compte existe, tu vas recevoir un lien pour réinitialiser ton mot de passe.</div>
        </div>

        <form v-else class="auth-form" @submit.prevent="handleSubmit">
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

          <button type="submit" class="auth-submit" :disabled="submitting">
            <span v-if="!submitting">Envoyer le lien</span>
            <span v-else>Envoi en cours…</span>
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
  background: rgba(0, 166, 255, 0.08);
  border: 1px solid rgba(0, 166, 255, 0.25);
}

.auth-alert.success {
  background: rgba(34, 197, 94, 0.08);
  border-color: rgba(34, 197, 94, 0.25);
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
