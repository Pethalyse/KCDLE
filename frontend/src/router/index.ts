import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router'
import HomeView from '@/views/HomeView.vue'
import DleView from '@/views/DleView.vue'
import CreditsView from '@/views/CreditsView.vue'
import PrivacyPolicyView from '@/views/PrivacyPolicyView.vue'
import LegalView from '@/views/LegalView.vue'
import NotFoundView from '@/views/NotFoundView.vue'
import LoginView from '@/views/LoginView.vue'
import RegisterView from '@/views/RegisterView.vue'
import ProfileView from '@/views/ProfileView.vue'
import LeaderboardView from '@/views/LeaderboardView.vue'
import AchievementsView from '@/views/AchievementsView.vue'
import FriendGroupsView from '@/views/FriendGroupsView.vue'
import PvpView from '@/views/PvpView.vue'
import PvpMatchView from '@/views/PvpMatchView.vue'
import PvpMatchPlayView from '@/views/PvpMatchPlayView.vue'
import PvpMatchEndView from '@/views/PvpMatchEndView.vue'
import {useAuthStore} from "@/stores/auth.ts";

const routes: RouteRecordRaw[] = [
  { path: '/', name: 'home', component: HomeView },

  { path: '/kcdle', name: 'kcdle', component: DleView, props: { game: 'kcdle' } },
  { path: '/lecdle', name: 'lecdle', component: DleView, props: { game: 'lecdle' } },
  { path: '/lfldle', name: 'lfldle', component: DleView, props: { game: 'lfldle' } },

  { path: '/pvp', name: 'pvp', component: PvpView, meta: { requiresAuth: true } },

  { path: '/pvp/matches/:matchId', name: 'pvp_match', component: PvpMatchView, props: true, meta: { requiresAuth: true } },
  { path: '/pvp/matches/:matchId/play', name: 'pvp_match_play', component: PvpMatchPlayView, props: true, meta: { requiresAuth: true } },
  { path: '/pvp/matches/:matchId/end', name: 'pvp_match_end', component: PvpMatchEndView, props: true, meta: { requiresAuth: true } },

  { path: '/credits', name: 'credits', component: CreditsView },
  { path: '/privacy', name: 'privacy', component: PrivacyPolicyView },
  { path: '/legal', name: 'legal', component: LegalView },

  { path: '/login', name: 'login', component: LoginView, meta: { requiresNotAuth: true } },
  { path: '/register', name: 'register', component: RegisterView, meta: { requiresNotAuth: true } },

  { path: '/profile', name: 'profile', component: ProfileView, meta: { requiresAuth: true } },

  { path: '/leaderboard/kcdle', name: 'leaderboard_kcdle', component: LeaderboardView, props: { game: 'kcdle' } },
  { path: '/leaderboard/lecdle', name: 'leaderboard_lecdle', component: LeaderboardView, props: { game: 'lecdle' } },
  { path: '/leaderboard/lfldle', name: 'leaderboard_lfldle', component: LeaderboardView, props: { game: 'lfldle' } },

  { path: '/achievements', name: 'achievements', component: AchievementsView, meta: { requiresAuth: true } },
  { path: '/groups', name: 'friends', component: FriendGroupsView, meta: { requiresAuth: true } },

  { path: '/:pathMatch(.*)*', name: 'not-found', component: NotFoundView },
]

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes,
  scrollBehavior(to, from, savedPosition) {
    if (savedPosition) return savedPosition
    return { top: 0 }
  },
})

router.beforeEach((to) => {
  const auth = useAuthStore()

  const requiresAuth = Boolean(to.meta?.requiresAuth)
  if (!requiresAuth) return true
  if (auth.isAuthenticated) return true

  return {
    name: 'login',
    query: {
      redirect: to.fullPath,
    },
  }
})

router.beforeEach((to) => {
  const auth = useAuthStore()

  const requiresNotAuth = Boolean(to.meta?.requiresNotAuth)
  if (!requiresNotAuth) return true
  if (!auth.isAuthenticated) return true

  return {
    name: 'home',
  }
})

export default router
