<script setup lang="ts">
import { reactive, ref } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import SimpleImg from '@/components/SimpleImg.vue'
import Credit from '@/components/Credit.vue'
import { useAuthStore } from '@/stores/auth'
import { useFlashStore } from '@/stores/flash'

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

const generalError = ref<string | null>(null)
const submitting = ref(false)

function getSafeRedirect(v: unknown): string {
  if (typeof v !== 'string') return '/'
  if (!v.startsWith('/')) return '/'
  return v
}

const redirectTo = getSafeRedirect(route.query.redirect)

function resetErrors() {
  fieldErrors.email = undefined
  fieldErrors.password = undefined
  generalError.value = null
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

        flash.push(
          'success',
          achievement.name,
          'Succès débloqué',
        )
      })
    }

    flash.success('Connexion réussie.', 'Bienvenue sur KCDLE')
    await router.push(redirectTo)
  } catch (error: any) {
    const response = error?.response
    if (response?.status === 422 || response?.status === 401) {
      const data = response.data

      if (data?.errors) {
        if (Array.isArray(data.errors.email)) {
          fieldErrors.email = data.errors.email[0]
        }
        if (Array.isArray(data.errors.password)) {
          fieldErrors.password = data.errors.password[0]
        }
      } else if (data?.message) {
        generalError.value = data.message
      } else {
        generalError.value = 'Identifiants invalides.'
      }
    } else {
      generalError.value = 'Une erreur inattendue est survenue.'
    }
    flash.error('Impossible de se connecter. Vérifie tes identifiants.')
  } finally {
    submitting.value = false
  }
}

function goHome() {
  void router.push({ name: 'home' })
}
</script>

<template>
  <div class="dle-page HOME">
    <header class="header_HOME">
      <div class="auth-logo">
        <SimpleImg
          class="logo"
          alt="KCDLE"
          img="HOMEDLE_Header-rbg.png"
          @onclick="goHome"
        />
      </div>
    </header>

    <main class="auth-container">
      <section class="auth-card">
        <h1 class="auth-title">
          Connexion
        </h1>
        <p class="auth-subtitle">
          Connecte-toi pour sauvegarder tes scores et tes achievements.
        </p>

        <form class="auth-form" @submit.prevent="handleSubmit">
          <div class="auth-field">
            <label for="email">Adresse e-mail</label>
            <input
              id="email"
              v-model="form.email"
              type="email"
              autocomplete="email"
              required
              :disabled="submitting"
            />
            <p v-if="fieldErrors.email" class="auth-error">
              {{ fieldErrors.email }}
            </p>
          </div>

          <div class="auth-field">
            <label for="password">Mot de passe</label>
            <input
              id="password"
              v-model="form.password"
              type="password"
              autocomplete="current-password"
              required
              :disabled="submitting"
            />
            <p v-if="fieldErrors.password" class="auth-error">
              {{ fieldErrors.password }}
            </p>
          </div>

          <p v-if="generalError" class="auth-error auth-error--general">
            {{ generalError }}
          </p>

          <button
            type="submit"
            class="auth-submit"
            :disabled="submitting"
          >
            <span v-if="!submitting">Se connecter</span>
            <span v-else>Connexion en cours…</span>
          </button>
        </form>

        <p class="auth-footer-text">
          Pas encore de compte ?
          <RouterLink
            :to="{ name: 'register', query: route.query.redirect ? { redirect: route.query.redirect } : {} }"
          >
            Créer un compte
          </RouterLink>
        </p>
      </section>
      <Credit />
    </main>
  </div>
</template>

<style scoped>
.dle-page {
  min-height: 100vh;
  padding: 20px;
  color: #f0f0f0;
  background: radial-gradient(circle at top, #20263a 0, #05060a 60%);
  font-size: 0.95rem;
  display: flex;
  flex-direction: column;
}

.header_HOME {
  display: flex;
  justify-content: center;
  margin-bottom: 18px;
}

.btn-home .logo {
  max-width: 260px;
}

.auth-card {
  width: 100%;
  max-width: 420px;
  margin-top: 10px;

  padding: 1.8rem 1.8rem 1.6rem;
  border-radius: 18px;

  background: radial-gradient(circle at top left, rgba(30, 64, 175, 0.25), rgba(15, 23, 42, 0.96));
  border: 1px solid rgba(148, 163, 184, 0.4);
  box-shadow:
    0 18px 40px rgba(15, 23, 42, 0.55),
    0 0 0 1px rgba(15, 23, 42, 0.9);
}

.auth-title {
  font-size: 1.45rem;
  font-weight: 800;
  margin-bottom: 0.25rem;
  text-align: center;
  letter-spacing: -0.3px;
}

.auth-subtitle {
  font-size: 0.9rem;
  color: #cbd5f5;
  text-align: center;
  margin-bottom: 1.4rem;
}

.auth-form {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.auth-field label {
  display: block;
  margin-bottom: 0.3rem;
  font-size: 0.85rem;
  font-weight: 600;
  color: #e5e7eb;
}

.auth-field input {
  width: 94%;
  padding: 0.6rem 0.85rem;
  border-radius: 12px;

  background: rgba(15, 23, 42, 0.9);
  border: 1px solid #1f2937;

  font-size: 0.92rem;
  font-weight: 500;
  color: #f9fafb;

  transition:
    border-color 0.15s ease,
    box-shadow 0.15s ease,
    background-color 0.2s ease,
    transform 0.08s ease;
}

.auth-field input::placeholder {
  color: #64748b;
}

.auth-field input:hover {
  border-color: #334155;
  background: rgba(15, 23, 42, 0.96);
}

.auth-field input:focus {
  outline: none;
  border-color: #3b82f6;
  background: rgba(15, 23, 42, 1);
  box-shadow:
    0 0 0 1px rgba(59, 130, 246, 0.8),
    0 0 0 6px rgba(59, 130, 246, 0.15);
  transform: translateY(-0.5px);
}

.auth-error {
  margin-top: 0.28rem;
  font-size: 0.82rem;
  color: #fecaca;
  font-weight: 500;
}

.auth-error--general {
  margin-top: 0.5rem;
  text-align: center;
}

.auth-submit {
  margin-top: 0.4rem;
  width: 100%;
  padding: 0.7rem 1rem;

  border-radius: 999px;
  border: none;

  background: linear-gradient(135deg, #2563eb, #4f46e5);
  color: #f9fafb;

  font-weight: 700;
  font-size: 0.98rem;

  cursor: pointer;

  display: inline-flex;
  align-items: center;
  justify-content: center;

  transition:
    transform 0.12s ease,
    box-shadow 0.15s ease,
    opacity 0.12s ease;
}

.auth-submit:hover:not(:disabled) {
  transform: translateY(-1px);
  box-shadow:
    0 12px 24px rgba(15, 23, 42, 0.55),
    0 0 0 1px rgba(59, 130, 246, 0.5);
}

.auth-submit:active:not(:disabled) {
  transform: translateY(0);
  box-shadow:
    0 6px 14px rgba(15, 23, 42, 0.55),
    0 0 0 1px rgba(37, 99, 235, 0.8);
}

.auth-submit:disabled {
  opacity: 0.6;
  cursor: default;
}

.auth-footer-text {
  margin-top: 1.1rem;
  font-size: 0.86rem;
  text-align: center;
  color: #cbd5f5;
}

.auth-footer-text a {
  color: #00a6ff;
  font-weight: 700;
  text-decoration: none;
}

.auth-footer-text a:hover {
  text-decoration: underline;
}

.auth-field label{
  text-align: start;
}

.auth-logo {
  text-align: center;
  margin-bottom: 20px;
}

.auth-logo img {
  width: auto;
  filter: drop-shadow(0 0 4px rgba(0,0,0,0.25));
}


@media (max-width: 640px) {
  .dle-page {
    padding: 14px 10px 18px;
  }

  .auth-card {
    padding: 1.5rem 1.3rem 1.3rem;
    margin-top: 4px;
  }

  .auth-title {
    font-size: 1.3rem;
  }

  .auth-subtitle {
    font-size: 0.86rem;
  }

  .auth-submit {
    font-size: 0.94rem;
  }

  .auth-logo img {
    height: 50%;
  }
}
</style>

