<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import api from '@/api'
import {useFlashStore} from "@/stores/flash.ts";

const router = useRouter()
const route = useRoute()
const auth = useAuthStore()
const flash = useFlashStore()

const isOpen = ref(false)
const isLocked = ref(false)
const hoverCount = ref(0)

const currentRouteName = computed(() => route.name?.toString() ?? '')

function handleMouseEnter() {
  hoverCount.value++
  isOpen.value = true
}

function handleMouseLeave() {
  hoverCount.value--
  if (hoverCount.value <= 0 && !isLocked.value) {
    setTimeout(() => {
      if (hoverCount.value <= 0 && !isLocked.value) isOpen.value = false
    }, 120)
  }
}

function closeHeader() {
  isLocked.value = false
  isOpen.value = false
}

function go(name: string, query: any = null) {
  router.push({ name, ...(query ? { query } : {}) })
  closeHeader()
}

async function logout() {
  await api.post('/auth/logout').catch(() => {})
  auth.logout()
  await router.push({name: 'home'})
  flash.info('Tu as été déconnecté.')
  closeHeader()
}
</script>

<template>
  <div
    class="header-wrapper"
    @mouseenter="handleMouseEnter"
    @mouseleave="handleMouseLeave"
  >
    <header class="header" :class="{ 'is-open': isOpen }">
      <div class="header-inner">
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
          <div class="group-label">Profil</div>
          <div class="group-items">
            <template v-if="auth.isAuthenticated">
              <button
                class="nav-item"
                :class="{ active: currentRouteName === 'profile' }"
                @click="go('profile')"
              >
                Mon profil
              </button>
              <button class="nav-item danger" @click="logout">
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

        <div class="divider"></div>

        <div class="nav-group">
          <div class="group-label">Leaderboards</div>
          <div class="group-items">
<!--            <button class="nav-item" @click="go('leaderboard')">-->
<!--              Global-->
<!--            </button>-->
            <button class="nav-item" @click="go('leaderboard', { game: 'kcdle' })">
              KCDLE
            </button>
            <button class="nav-item" @click="go('leaderboard', { game: 'lecdle' })">
              LECDLE
            </button>
            <button class="nav-item" @click="go('leaderboard', { game: 'lfldle' })">
              LFLDLE
            </button>
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
  max-width: 1200px;
  margin: 0 auto;
  padding: 16px 18px;
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 18px;
}

.divider {
  width: 1px;
  background: rgba(255, 255, 255, 0.18);
  height: 100%;
}

.nav-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.group-label {
  font-size: 0.75rem;
  text-transform: uppercase;
  opacity: 0.7;
  letter-spacing: 0.06em;
}

.group-items {
  display: flex;
  flex-direction: column;
  gap: 5px;
}

.nav-item {
  border: none;
  border-radius: 6px;
  padding: 6px 10px;
  background: rgba(255, 255, 255, 0.08);
  color: #f6f6f6;
  cursor: pointer;
  font-size: 0.85rem;
}

.nav-item:hover {
  background: rgba(0, 166, 255, 0.4);
}

.nav-item.active {
  background: #00a6ff;
  color: #fff;
}

.nav-item.danger {
  background: rgba(255, 66, 66, 0.25);
}

.nav-item.danger:hover {
  background: rgba(255, 66, 66, 0.5);
}
</style>
