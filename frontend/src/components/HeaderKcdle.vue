<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import api from '@/api'
import { useFlashStore } from '@/stores/flash'
import { usePvpStore } from '@/stores/pvp'
import {handleError} from "@/utils/handleError.ts";

const router = useRouter()
const route = useRoute()
const auth = useAuthStore()
const flash = useFlashStore()
const pvp = usePvpStore()

const isOpen = ref(false)
const navOpen = ref(false)

const currentRouteName = computed(() => route.name?.toString() ?? '')
const isAuthenticated = computed(() => auth.isAuthenticated)

const currentTheme = computed<'kcdle' | 'lecdle' | 'lfldle' | 'default'>(() => {
  const path = route.path ?? ''

  if (path.startsWith('/lecdle')) return 'lecdle'
  if (path.startsWith('/lfldle')) return 'lfldle'
  if (path.startsWith('/kcdle')) return 'kcdle'
  return 'default'
})

const hasQueue = computed(() => pvp.isQueued)
const hasMatch = computed(() => pvp.isInMatch && pvp.matchId !== null)

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

function go(name: string, params: any = null, query: any = null) {
  router.push({ name, ...(params ? { params } : {}), ...(query ? { query } : {}) })
  closeMenus()
}

function goPvp() {
  go('pvp')
}

function goMatch() {
  if (!pvp.matchId) return
  go('pvp_match', { matchId: pvp.matchId })
}

async function logout() {
  try {
    await api.post('/auth/logout')
  } catch (e) {
    handleError(e)
  }
  auth.logout()
  await router.push({ name: 'home' })
  flash.info('Tu as été déconnecté.')
  closeMenus()
}
</script>

<template>
  <div
    class="header-wrapper"
    @mouseenter="handleMouseEnter"
    @mouseleave="handleMouseLeave"
  >
    <header
      class="header"
      :data-theme="currentTheme"
      :class="{ 'is-open': isOpen }"
    >
      <div class="header-inner">
        <div class="logo-kc" @click="go('home')">
          <img
            src="/images/HOMEDLE_Header-rbg.png"
            alt="kcdle.fr"
            class="logo-img"
          />
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
              <button
                v-if="!hasQueue && !hasMatch"
                class="nav-item"
                :class="{ active: currentRouteName === 'pvp' }"
                @click="goPvp"
              >
                PVP
              </button>

              <button
                v-else-if="hasQueue"
                class="nav-item"
                type="button"
                :class="{ active: currentRouteName === 'pvp' }"
                @click="goPvp"
              >
                En queue
              </button>

              <button
                v-else-if="hasMatch"
                class="nav-item"
                type="button"
                :class="{ active: currentRouteName === 'pvp_match' || currentRouteName === 'pvp_match_play' }"
                @click="goMatch"
              >
                Match en cours
              </button>
              <button
                class="nav-item"
                :class="{ active: currentRouteName === 'kcdle_trophies_hl' }"
                @click="go('kcdle_trophies_hl')"
              >
                Plus/Moins
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
                v-if="isAuthenticated"
                class="nav-item"
                :class="{ active: currentRouteName === 'friends' }"
                @click="go('friends')"
              >
                Groupes
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
  //position: fixed;
  top: 0;
  left: 0;
  right: 0;
  pointer-events: none;
  z-index: 50;
}

.header {
  width: 100%;
  --header-color-main: #20263A;
  --header-color-dark: #05060A;
  --header-color-text-1: #00a6ff;
  --header-color-text-2: #66e0ff;

  background: radial-gradient(circle at top, var(--header-color-main) 0, var(--header-color-dark) 70%);
  border-bottom: 1px solid rgba(255, 255, 255, 0.16);
  //transform: translateY(calc(-100% + 26px));
  transition: transform 0.18s ease-out;
  pointer-events: auto;
  position: relative;
  box-shadow: 0 10px 20px rgba(0, 0, 0, 0.6);
}

.header[data-theme='kcdle'],
.header[data-theme='default'] {
  --header-color-main: #20263A;
  --header-color-dark: #05060A;
  --header-color-text-1: #00a6ff;
  --header-color-text-2: #66e0ff;
}

.header[data-theme='lecdle'] {
  --header-color-main: #7F66C6;
  --header-color-dark: #5A4693;
  --header-color-text-1: #A855F7;
  --header-color-text-2: #C4B5FD;
}

.header[data-theme='lfldle'] {
  --header-color-main: #F47857;
  --header-color-dark: #A85A32;
  --header-color-text-1: #E26B3D;
  --header-color-text-2: #FFB199;
}

.header.is-open {
  transform: translateY(0);
}

.header-inner {
  margin: 0 auto;
  padding: 6px 14px 10px;
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 16px;
  font-size: 0.9rem;
  color: #f5f7ff;
}

.logo-kc {
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
}

.logo-img {
  height: 38px;
  width: auto;
  object-fit: contain;
  filter: drop-shadow(0 0 4px rgba(0,0,0,0.3));
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
  font-weight: 600;
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
  background: var(--header-color-text-1);
  color: #fff;
  box-shadow: 0 0 12px var(--header-color-text-2);
}

.nav-item.danger {
  background: rgba(255, 66, 66, 0.25);
}

.nav-item.danger:hover {
  background: rgba(255, 66, 66, 0.5);
}

@media (max-width: 1080px) {
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
