<script setup lang="ts">
import {computed, onBeforeUnmount, onMounted, ref, watch} from 'vue'
import {useRoute, useRouter} from 'vue-router'
import {useAuthStore} from '@/stores/auth'
import api from '@/api'
import {useFlashStore} from '@/stores/flash'
import {usePvpStore} from '@/stores/pvp'
import {handleError} from "@/utils/handleError.ts";

const router = useRouter()
const route = useRoute()
const auth = useAuthStore()
const flash = useFlashStore()
const pvp = usePvpStore()

const isOpen = ref(false)
const navOpen = ref(false)

const mobileDrawerOpen = ref(false)
const mobileDrawerTranslateX = ref(0)
const mobileViewport = ref(typeof window !== 'undefined' ? window.innerWidth : 0)
const mobileDrawerWidth = ref(320)
const mobileAccordion = ref({
  games: true,
  leaderboards: false,
  account: false,
})

const isMobile = computed(() => mobileViewport.value <= 1080)

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
  if (isMobile.value) {
    toggleMobileDrawer()
    return
  }
  navOpen.value = !navOpen.value
}

function closeMenus() {
  navOpen.value = false
  closeMobileDrawer()
}

function updateMobileViewport() {
  mobileViewport.value = window.innerWidth
  mobileDrawerWidth.value = Math.min(360, Math.floor(window.innerWidth * 0.86))
  if (!mobileDrawerOpen.value) {
    mobileDrawerTranslateX.value = -mobileDrawerWidth.value
  }
}

function lockBodyScroll(lock: boolean) {
  if (lock) {
    document.documentElement.style.overflow = 'hidden'
    document.body.style.overflow = 'hidden'
    return
  }

  document.documentElement.style.overflow = ''
  document.body.style.overflow = ''
}

function openMobileDrawer() {
  if (!isMobile.value) return
  mobileDrawerOpen.value = true
  mobileDrawerTranslateX.value = 0
  lockBodyScroll(true)
}

function closeMobileDrawer() {
  mobileDrawerOpen.value = false
  mobileDrawerTranslateX.value = -mobileDrawerWidth.value
  lockBodyScroll(false)
}

function toggleMobileDrawer() {
  if (mobileDrawerOpen.value) {
    closeMobileDrawer()
    return
  }
  openMobileDrawer()
}

function toggleAccordion(key: 'games' | 'leaderboards' | 'account') {
  mobileAccordion.value = {
    games: false,
    leaderboards: false,
    account: false,
    [key]: !mobileAccordion.value[key],
  } as any
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

type MobileDragMode = 'none' | 'opening' | 'closing'

const dragMode = ref<MobileDragMode>('none')
const dragStartX = ref(0)
const dragStartY = ref(0)
const dragStartTranslate = ref(0)
const dragLastX = ref(0)
const dragLastY = ref(0)
const dragActive = ref(false)

function isMostlyHorizontal(dx: number, dy: number) {
  return Math.abs(dx) > 10 && Math.abs(dx) > Math.abs(dy) * 1.2
}

function onTouchStart(e: TouchEvent) {
  if (!isMobile.value) return
  if (!e.touches || e.touches.length !== 1) return

  const t = e.touches[0]
  if(!t) return;
  dragStartX.value = t.clientX
  dragStartY.value = t.clientY
  dragLastX.value = t.clientX
  dragLastY.value = t.clientY
  dragActive.value = false

  const edgeThreshold = 24

  if (!mobileDrawerOpen.value) {
    if (t.clientX <= edgeThreshold) {
      dragMode.value = 'opening'
      dragStartTranslate.value = -mobileDrawerWidth.value
      mobileDrawerTranslateX.value = -mobileDrawerWidth.value
    } else {
      dragMode.value = 'none'
    }
    return
  }

  if (t.clientX <= mobileDrawerWidth.value + 20) {
    dragMode.value = 'closing'
    dragStartTranslate.value = 0
    return
  }

  dragMode.value = 'none'
}

function onTouchMove(e: TouchEvent) {
  if (!isMobile.value) return
  if (dragMode.value === 'none') return
  if (!e.touches || e.touches.length !== 1) return

  const t = e.touches[0]
  if(!t) return;
  const dx = t.clientX - dragStartX.value
  const dy = t.clientY - dragStartY.value

  dragLastX.value = t.clientX
  dragLastY.value = t.clientY

  if (!dragActive.value) {
    if (!isMostlyHorizontal(dx, dy)) return
    dragActive.value = true
    if (dragMode.value === 'opening') {
      mobileDrawerOpen.value = true
      lockBodyScroll(true)
    }
  }

  e.preventDefault()

  if (dragMode.value === 'opening') {
    mobileDrawerTranslateX.value = Math.min(0, Math.max(-mobileDrawerWidth.value, -mobileDrawerWidth.value + dx))
    return
  }

  mobileDrawerTranslateX.value = Math.min(0, Math.max(-mobileDrawerWidth.value, dx))
}

function onTouchEnd() {
  if (!isMobile.value) return
  if (dragMode.value === 'none') return

  const dx = dragLastX.value - dragStartX.value
  const dy = dragLastY.value - dragStartY.value

  const openThreshold = mobileDrawerWidth.value * 0.35
  const closeThreshold = mobileDrawerWidth.value * 0.35

  if (!dragActive.value && !isMostlyHorizontal(dx, dy)) {
    dragMode.value = 'none'
    dragActive.value = false
    return
  }

  if (dragMode.value === 'opening') {
    if (dx >= openThreshold) {
      openMobileDrawer()
    } else {
      closeMobileDrawer()
    }
    dragMode.value = 'none'
    dragActive.value = false
    return
  }

  if (-dx >= closeThreshold) {
    closeMobileDrawer()
  } else {
    openMobileDrawer()
  }

  dragMode.value = 'none'
  dragActive.value = false
}

function onKeyDown(e: KeyboardEvent) {
  if (!isMobile.value) return
  if (!mobileDrawerOpen.value) return
  if (e.key === 'Escape') {
    closeMobileDrawer()
  }
}

onMounted(() => {
  if (typeof window === 'undefined') return
  updateMobileViewport()
  window.addEventListener('resize', updateMobileViewport)
  window.addEventListener('touchstart', onTouchStart, { passive: true })
  window.addEventListener('touchmove', onTouchMove, { passive: false })
  window.addEventListener('touchend', onTouchEnd, { passive: true })
  window.addEventListener('keydown', onKeyDown)
  mobileDrawerTranslateX.value = -mobileDrawerWidth.value
})

onBeforeUnmount(() => {
  if (typeof window === 'undefined') return
  window.removeEventListener('resize', updateMobileViewport)
  window.removeEventListener('touchstart', onTouchStart as any)
  window.removeEventListener('touchmove', onTouchMove as any)
  window.removeEventListener('touchend', onTouchEnd as any)
  window.removeEventListener('keydown', onKeyDown as any)
  lockBodyScroll(false)
})

watch(
  () => route.fullPath,
  () => {
    closeMenus()
  },
)

watch(
  () => isMobile.value,
  (v) => {
    if (!v) {
      closeMobileDrawer()
      return
    }
    mobileDrawerTranslateX.value = mobileDrawerOpen.value ? 0 : -mobileDrawerWidth.value
  },
)
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
          :aria-expanded="isMobile ? mobileDrawerOpen : navOpen"
        >
          <span :class="{ 'burger-line': true, 'is-open': isMobile ? mobileDrawerOpen : navOpen }"></span>
          <span :class="{ 'burger-line': true, 'is-open': isMobile ? mobileDrawerOpen : navOpen }"></span>
          <span :class="{ 'burger-line': true, 'is-open': isMobile ? mobileDrawerOpen : navOpen }"></span>
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

  <teleport to="body">
    <div
      v-if="isMobile"
      class="mobile-drawer-layer"
      :class="{ 'mobile-drawer-layer--open': mobileDrawerOpen }"
      @click="closeMobileDrawer"
    >
      <div
        class="mobile-drawer-overlay"
        :class="{ 'mobile-drawer-overlay--open': mobileDrawerOpen }"
      ></div>

      <aside
        class="mobile-drawer"
        :class="{ 'mobile-drawer--open': mobileDrawerOpen, 'mobile-drawer--dragging': dragActive }"
        :style="{ width: mobileDrawerWidth + 'px', transform: 'translateX(' + mobileDrawerTranslateX + 'px)' }"
        role="dialog"
        aria-modal="true"
        aria-label="Menu"
        @click.stop
      >
        <div class="mobile-drawer-header">
          <div class="mobile-drawer-title">Menu</div>
          <button type="button" class="mobile-drawer-close" @click="closeMobileDrawer" aria-label="Fermer">
            ✕
          </button>
        </div>

        <div class="mobile-drawer-content">
          <button
            type="button"
            class="mobile-section"
            :class="{ 'mobile-section--open': mobileAccordion.games }"
            @click="toggleAccordion('games')"
          >
            <div class="mobile-section-label">Jeux</div>
            <div class="mobile-section-icon">▾</div>
          </button>
          <div v-show="mobileAccordion.games" class="mobile-section-items">
            <button class="mobile-item" :class="{ active: currentRouteName === 'kcdle' }" @click="go('kcdle')">KCDLE</button>
            <button class="mobile-item" :class="{ active: currentRouteName === 'lecdle' }" @click="go('lecdle')">LECDLE</button>
            <button class="mobile-item" :class="{ active: currentRouteName === 'lfldle' }" @click="go('lfldle')">LFLDLE</button>

            <button
              v-if="!hasQueue && !hasMatch"
              class="mobile-item"
              :class="{ active: currentRouteName === 'pvp' }"
              @click="goPvp"
            >
              PVP
            </button>
            <button
              v-else-if="hasQueue"
              class="mobile-item"
              type="button"
              :class="{ active: currentRouteName === 'pvp' }"
              @click="goPvp"
            >
              En queue
            </button>
            <button
              v-else-if="hasMatch"
              class="mobile-item"
              type="button"
              :class="{ active: currentRouteName === 'pvp_match' || currentRouteName === 'pvp_match_play' }"
              @click="goMatch"
            >
              Match en cours
            </button>

            <button
              class="mobile-item"
              :class="{ active: currentRouteName === 'kcdle_trophies_hl' }"
              @click="go('kcdle_trophies_hl')"
            >
              Plus/Moins
            </button>
          </div>

          <button
            type="button"
            class="mobile-section"
            :class="{ 'mobile-section--open': mobileAccordion.leaderboards }"
            @click="toggleAccordion('leaderboards')"
          >
            <div class="mobile-section-label">Leaderboards</div>
            <div class="mobile-section-icon">▾</div>
          </button>
          <div v-show="mobileAccordion.leaderboards" class="mobile-section-items">
            <button class="mobile-item" :class="{ active: currentRouteName === 'leaderboard_kcdle' }" @click="go('leaderboard_kcdle')">KCDLE</button>
            <button class="mobile-item" :class="{ active: currentRouteName === 'leaderboard_lecdle' }" @click="go('leaderboard_lecdle')">LECDLE</button>
            <button class="mobile-item" :class="{ active: currentRouteName === 'leaderboard_lfldle' }" @click="go('leaderboard_lfldle')">LFLDLE</button>
            <button
              v-if="isAuthenticated"
              class="mobile-item"
              :class="{ active: currentRouteName === 'friends' }"
              @click="go('friends')"
            >
              Groupes
            </button>
          </div>

          <button
            type="button"
            class="mobile-section"
            :class="{ 'mobile-section--open': mobileAccordion.account }"
            @click="toggleAccordion('account')"
          >
            <div class="mobile-section-label">Compte</div>
            <div class="mobile-section-icon">▾</div>
          </button>
          <div v-show="mobileAccordion.account" class="mobile-section-items">
            <template v-if="isAuthenticated">
              <button class="mobile-item" :class="{ active: currentRouteName === 'profile' }" @click="go('profile')">Profil</button>
              <button class="mobile-item mobile-item--danger" type="button" @click="logout">Déconnexion</button>
            </template>
            <template v-else>
              <button class="mobile-item" :class="{ active: currentRouteName === 'login' }" @click="go('login')">Connexion</button>
              <button class="mobile-item" :class="{ active: currentRouteName === 'register' }" @click="go('register')">Inscription</button>
            </template>
          </div>
        </div>
      </aside>
    </div>
  </teleport>
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

.mobile-drawer-layer {
  position: fixed;
  inset: 0;
  z-index: 1000;
  pointer-events: none;
}

.mobile-drawer-layer--open {
  pointer-events: auto;
}

.mobile-drawer-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.0);
  transition: background 0.18s ease;
}

.mobile-drawer-overlay--open {
  background: rgba(0, 0, 0, 0.55);
}

.mobile-drawer {
  position: fixed;
  top: 0;
  left: 0;
  bottom: 0;
  background: radial-gradient(circle at top, rgba(32, 38, 58, 0.98) 0, rgba(5, 6, 10, 0.98) 70%);
  border-right: 1px solid rgba(255, 255, 255, 0.14);
  box-shadow: 15px 0 30px rgba(0, 0, 0, 0.65);
  transform: translateX(-100%);
  transition: transform 0.18s ease;
  display: flex;
  flex-direction: column;
  pointer-events: auto;
}

.mobile-drawer--dragging {
  transition: none;
}

.mobile-drawer-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 14px 10px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.12);
}

.mobile-drawer-title {
  font-weight: 700;
  letter-spacing: 0.04em;
}

.mobile-drawer-close {
  width: 34px;
  height: 34px;
  border-radius: 10px;
  border: 1px solid rgba(255, 255, 255, 0.18);
  background: rgba(0, 0, 0, 0.22);
  color: #fff;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
}

.mobile-drawer-content {
  padding: 10px 12px 14px;
  overflow: auto;
  -ms-overflow-style: none;
  scrollbar-width: none;
}

.mobile-drawer-content::-webkit-scrollbar {
  width: 0;
  height: 0;
}

.mobile-section {
  width: 100%;
  border: 1px solid rgba(255, 255, 255, 0.14);
  background: rgba(255, 255, 255, 0.06);
  color: #f5f7ff;
  border-radius: 12px;
  padding: 12px 12px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  cursor: pointer;
  margin-top: 10px;
}

.mobile-section-label {
  font-weight: 700;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  font-size: 0.82rem;
  opacity: 0.95;
}

.mobile-section-icon {
  opacity: 0.8;
  transform: rotate(0deg);
  transition: transform 0.14s ease;
}

.mobile-section--open .mobile-section-icon {
  transform: rotate(180deg);
}

.mobile-section-items {
  margin-top: 8px;
  padding-left: 6px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.mobile-item {
  text-align: left;
  width: 100%;
  border: 1px solid rgba(255, 255, 255, 0.12);
  background: rgba(255, 255, 255, 0.08);
  color: #f6f6f6;
  border-radius: 12px;
  padding: 10px 12px;
  cursor: pointer;
  font-size: 0.92rem;
  transition: transform 0.1s ease, background 0.15s ease, box-shadow 0.15s ease;
}

.mobile-item:active {
  transform: scale(0.99);
}

.mobile-item.active {
  background: var(--header-color-text-1);
  color: #fff;
  box-shadow: 0 0 14px var(--header-color-text-2);
  border-color: rgba(255, 255, 255, 0.2);
}

.mobile-item--danger {
  background: rgba(255, 66, 66, 0.2);
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
    display: none;
  }

  .nav-block.nav-block--open {
    display: none;
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
