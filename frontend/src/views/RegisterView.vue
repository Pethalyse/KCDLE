<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
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
  name: '',
  email: '',
  password: '',
  passwordConfirmation: '',
})

const fieldErrors = reactive<{
  name?: string
  email?: string
  password?: string
  passwordConfirmation?: string
}>({})

const submitting = ref(false)
const showPassword = ref(false)
const showPasswordConfirmation = ref(false)

function getSafeRedirect(v: unknown): string {
  if (typeof v !== 'string') return '/'
  if (!v.startsWith('/')) return '/'
  return v
}

const redirectTo = computed(() => getSafeRedirect(route.query.redirect))

function resetErrors() {
  fieldErrors.name = undefined
  fieldErrors.email = undefined
  fieldErrors.password = undefined
  fieldErrors.passwordConfirmation = undefined
}

async function handleSubmit() {
  if (submitting.value) return

  resetErrors()

  if (!form.name.trim()) {
    fieldErrors.name = 'Le pseudo est obligatoire.'
    flash.error('Le pseudo est obligatoire.', 'Création de compte')
    return
  }

  if (form.password !== form.passwordConfirmation) {
    fieldErrors.passwordConfirmation = 'Les mots de passe ne correspondent pas.'
    flash.error('Les mots de passe ne correspondent pas.', 'Création de compte')
    return
  }

  submitting.value = true

  try {
    const data = await auth.register({
      name: form.name.trim(),
      email: form.email.trim(),
      password: form.password,
      password_confirmation: form.passwordConfirmation,
    })

    if (data?.requires_email_verification) {
      flash.info("Un e-mail de validation vient de t'être envoyé. Pense à vérifier tes spams.", 'Validation e-mail', 0)
    }

    // flash.success('Compte créé avec succès. Tu dois valider ton e-mail pour te connecter.', 'Inscription réussie')

    const query: Record<string, string> = {}
    if (typeof route.query.redirect === 'string') query.redirect = route.query.redirect
    await router.push({ name: 'login', query })
  } catch (error: any) {
    const status = error?.response?.status
    const data = error?.response?.data

    if (status === 422) {
      if (data?.errors) {
        if (Array.isArray(data.errors.name)) fieldErrors.name = data.errors.name[0]
        if (Array.isArray(data.errors.email)) fieldErrors.email = data.errors.email[0]
        if (Array.isArray(data.errors.password)) fieldErrors.password = data.errors.password[0]

        const first =
          fieldErrors.name ||
          fieldErrors.email ||
          fieldErrors.password ||
          fieldErrors.passwordConfirmation ||
          (typeof data?.message === 'string' ? data.message : null)

        flash.error(first || 'Impossible de créer le compte.', 'Création de compte')
        return
      }

      if (typeof data?.message === 'string') {
        flash.error(data.message, 'Création de compte')
        return
      }
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
  () => [form.name, form.email, form.password, form.passwordConfirmation],
  () => resetErrors(),
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
          <h1 class="auth-title">Inscription</h1>
          <p class="auth-subtitle">Ton e-mail devra être validé pour activer ton compte.</p>
        </div>

        <form class="auth-form" @submit.prevent="handleSubmit">
          <div class="auth-field">
            <label for="name">Pseudo</label>
            <input
              id="name"
              v-model.trim="form.name"
              type="text"
              autocomplete="nickname"
              required
              :disabled="submitting"
              maxlength="20"
              placeholder="Pseudo"
            />
            <p v-if="fieldErrors.name" class="auth-error">{{ fieldErrors.name }}</p>
          </div>

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
            <p class="auth-help">10 caractères min, 1 majuscule, 1 minuscule, 1 chiffre, 1 symbole.</p>
            <p v-if="fieldErrors.password" class="auth-error">{{ fieldErrors.password }}</p>
          </div>

          <div class="auth-field">
            <label for="passwordConfirmation">Confirmation</label>
            <div class="auth-password">
              <input
                id="passwordConfirmation"
                v-model="form.passwordConfirmation"
                :type="showPasswordConfirmation ? 'text' : 'password'"
                autocomplete="new-password"
                required
                :disabled="submitting"
                placeholder="Mot de passe"
              />
              <button
                type="button"
                class="auth-password-toggle"
                :disabled="submitting"
                :aria-label="showPasswordConfirmation ? 'Masquer le mot de passe' : 'Afficher le mot de passe'"
                @click="showPasswordConfirmation = !showPasswordConfirmation"
              >
                {{ showPasswordConfirmation ? 'Masquer' : 'Afficher' }}
              </button>
            </div>
            <p v-if="fieldErrors.passwordConfirmation" class="auth-error">{{ fieldErrors.passwordConfirmation }}</p>
          </div>

          <button type="submit" class="auth-submit" :disabled="submitting">
            <span v-if="!submitting">Créer mon compte</span>
            <span v-else>Création en cours…</span>
          </button>
        </form>

        <div class="auth-footer">
          <div class="auth-footer-text">
            Déjà un compte ?
            <RouterLink :to="{ name: 'login', query: route.query.redirect ? { redirect: route.query.redirect } : {} }">
              Se connecter
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

.auth-help {
  margin: 6px 0 0;
  opacity: 0.75;
  font-size: 0.86rem;
  line-height: 1.25;
}

.auth-error {
  margin: 6px 0 0;
  color: #ffb4b4;
  font-weight: 600;
  font-size: 0.86rem;
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
