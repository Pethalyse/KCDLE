<script setup lang="ts">
import {ref, computed, onMounted} from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import api from '@/api'
import { useFlashStore } from '@/stores/flash'

const router = useRouter()
const route = useRoute()
const auth = useAuthStore()
const flash = useFlashStore()

const isOpen = ref(false)
const navOpen = ref(false)

const currentRouteName = computed(() => route.name?.toString() ?? '')
const isAuthenticated = computed(() => auth.isAuthenticated)

function handleMouseEnter() {
  isOpen.value = true
}

function handleMouseLeave() {
  isOpen.value = false
}

function toggleBurger() {
  navOpen.value = !navOpen.value
}

function closeMenus() {
  navOpen.value = false
}

function go(name: string, query: any = null) {
  router.push({ name, ...(query ? { query } : {}) })
  closeMenus()
}

async function logout() {
  try {
    await api.post('/auth/logout')
  } catch (_) {
  }
  auth.logout()
  await router.push({ name: 'home' })
  flash.info('Tu as été déconnecté.')
  closeMenus()
}

onMounted(() => {
  console.log(currentRouteName)
})
</script>

<template>
  <div
    class="header-wrapper"
    @mouseenter="handleMouseEnter"
    @mouseleave="handleMouseLeave"
  >
    <header
      class="header"
      :class="{ 'is-open': isOpen }"
    >
      <div class="header-inner">
        <div class="logo-block" @click="go('home')">
          <div class="logo-main">
            <span class="logo-kc">KCDLE</span>
          </div>
          <div class="logo-sub">
            Daily KC Guess game
          </div>
        </div>

        <button
          type="button"
          class="burger-button"
          @click="toggleBurger"
          aria-label="Menu"
        >
          <span :class="{ 'burger-line': true, 'is-open': navOpen }"></span>
          <span :class="{ 'burger-line': true, 'is-open': navOpen }"></span>
          <span :class="{ 'burger-line': true, 'is-open': navOpen }"></span>
        </button>

        <div class="divider"></div>

        <div
          class="nav-block"
          :class="{ 'nav-block--open': navOpen }"
        >
          <div class="nav-group">
            <div class="group-label">Jeux</div>
            <div class="group-items">
              <button
                class="nav-item"
                :class="{ active: currentRouteName === 'kcdle' }"
                @click="go('kcdle')"
              >
                KCDLE
              </button>
              <button
                class="nav-item"
                :class="{ active: currentRouteName === 'lecdle' }"
                @click="go('lecdle')"
              >
                LECDLE
              </button>
              <button
                class="nav-item"
                :class="{ active: currentRouteName === 'lfldle' }"
                @click="go('lfldle')"
              >
                LFLDLE
              </button>
            </div>
          </div>

          <div class="divider"></div>

          <div class="nav-group">
            <div class="group-label">Leaderboards</div>
            <div class="group-items">
              <button
                class="nav-item"
                :class="{ active: currentRouteName === 'leaderboard_kcdle' }"
                @click="go('leaderboard_kcdle')"
              >
                KCDLE
              </button>
              <button
                class="nav-item"
                :class="{ active: currentRouteName === 'leaderboard_lecdle' }"
                @click="go('leaderboard_lecdle')"
              >
                LECDLE
              </button>
              <button
                class="nav-item"
                :class="{ active: currentRouteName === 'leaderboard_lfldle' }"
                @click="go('leaderboard_lfldle')"
              >
                LFLDLE
              </button>
              <button
                class="nav-item"
                :class="{ active: currentRouteName === 'friends' }"
                @click="go('friends')"
              >
                Groupes d’amis
              </button>
            </div>
          </div>

          <div class="divider"></div>

          <div class="nav-group">
            <div class="group-label">Compte</div>
            <div class="group-items">
              <template v-if="isAuthenticated">
                <button
                  class="nav-item"
                  :class="{ active: currentRouteName === 'profile' }"
                  @click="go('profile')"
                >
                  Profil
                </button>
                <button
                  class="nav-item danger"
                  type="button"
                  @click="logout"
                >
                  Déconnexion
                </button>
              </template>
              <template v-else>
                <button
                  class="nav-item"
                  :class="{ active: currentRouteName === 'login' }"
                  @click="go('login')"
                >
                  Connexion
                </button>
                <button
                  class="nav-item"
                  :class="{ active: currentRouteName === 'register' }"
                  @click="go('register')"
                >
                  Inscription
                </button>
              </template>
            </div>
          </div>
        </div>
      </div>
    </header>
  </div>
</template>

<style scoped>
.header-wrapper {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  pointer-events: none;
  z-index: 50;
}

.header {
  width: 100%;
  background: radial-gradient(circle at top, #20263a 0, #05060a 70%);
  border-bottom: 1px solid rgba(255, 255, 255, 0.16);
  transform: translateY(calc(-100% + 26px));
  transition: transform 0.18s ease-out;
  pointer-events: auto;
  position: relative;
  box-shadow: 0 10px 20px rgba(0, 0, 0, 0.6);
}

.header.is-open {
  transform: translateY(0);
}

.header-inner {
  max-width: 1100px;
  margin: 0 auto;
  padding: 6px 14px 10px;
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 16px;
  font-size: 0.9rem;
  color: #f5f7ff;
}

.logo-block {
  display: flex;
  flex-direction: column;
  gap: 2px;
  cursor: pointer;
}

.logo-main {
  font-weight: 800;
  letter-spacing: 0.16em;
  text-transform: uppercase;
  font-size: 1.1rem;
}

.logo-kc {
  background: linear-gradient(120deg, #00a6ff, #66e0ff);
  -webkit-background-clip: text;
  background-clip: text;
  color: transparent;
}

.logo-sub {
  font-size: 0.75rem;
  opacity: 0.8;
}

.burger-button {
  display: none;
  width: 34px;
  height: 28px;
  border-radius: 6px;
  border: 1px solid rgba(255, 255, 255, 0.22);
  background: rgba(5, 7, 15, 0.8);
  padding: 4px 6px;
  cursor: pointer;
  align-items: center;
  justify-content: center;
  flex-direction: column;
  gap: 4px;
}

.burger-line {
  width: 100%;
  height: 2px;
  background: #f5f7ff;
  border-radius: 999px;
  transition: transform 0.15s ease, opacity 0.15s ease;
}

.burger-line.is-open:nth-child(1) {
  transform: translateY(4px) rotate(45deg);
}

.burger-line.is-open:nth-child(2) {
  opacity: 0;
}

.burger-line.is-open:nth-child(3) {
  transform: translateY(-4px) rotate(-45deg);
}

.nav-block {
  display: flex;
  align-items: flex-end;
  gap: 14px;
  flex: 1;
  justify-content: flex-end;
  flex-wrap: wrap;
}

.nav-group {
  display: flex;
  flex-direction: column;
  gap: 5px;
}

.nav-group--right {
  align-items: flex-end;
}

.group-label {
  font-size: 0.74rem;
  text-transform: uppercase;
  letter-spacing: 0.12em;
  opacity: 0.75;
}

.group-items {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  justify-content: flex-start;
}

.nav-group--right .group-items {
  justify-content: flex-end;
}

.divider {
  width: 1px;
  height: 36px;
  background: rgba(255, 255, 255, 0.16);
}

.nav-item {
  border: none;
  border-radius: 6px;
  padding: 6px 10px;
  background: rgba(255, 255, 255, 0.08);
  color: #f6f6f6;
  cursor: pointer;
  font-size: 0.82rem;
  transition: background 0.15s ease, color 0.15s ease, transform 0.1s ease,
  box-shadow 0.15s ease;
  white-space: nowrap;
}

.nav-item:hover {
  background: rgba(255, 255, 255, 0.16);
  transform: translateY(-1px);
}

.nav-item.active {
  background: #00a6ff;
  color: #fff;
  box-shadow: 0 0 12px rgba(0, 166, 255, 0.7);
}

.nav-item.danger {
  background: rgba(255, 66, 66, 0.25);
}

.nav-item.danger:hover {
  background: rgba(255, 66, 66, 0.5);
}

@media (max-width: 800px) {
  .header-wrapper {
    position: relative;
  }

  .header {
    transform: translateY(0);
  }

  .header-inner {
    align-items: stretch;
  }

  .burger-button {
    display: flex;
    align-self: flex-end;
    margin-top: 4px;
  }

  .nav-block {
    flex-direction: column;
    align-items: flex-start;
    gap: 12px;
    justify-content: flex-start;
    flex-wrap: nowrap;
    display: none;
  }

  .nav-block.nav-block--open {
    display: flex;
  }

  .divider {
    display: none;
  }

  .nav-group--right {
    align-items: flex-start;
  }

  .nav-group--right .group-items {
    justify-content: flex-start;
  }
}
</style>
