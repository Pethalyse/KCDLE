import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router'
import HomeView from '@/views/HomeView.vue'
import DleView from '@/views/DleView.vue'
import CreditsView from "@/views/CreditsView.vue";
import PrivacyPolicyView from "@/views/PrivacyPolicyView.vue";
import LegalView from "@/views/LegalView.vue";
import NotFoundView from "@/views/NotFoundView.vue";

const routes: RouteRecordRaw[] = [
  {
    path: '/',
    name: 'home',
    component: HomeView,
  },
  {
    path: '/kcdle',
    name: 'kcdle',
    component: DleView,
    props: { game: 'kcdle' },
  },
  {
    path: '/lecdle',
    name: 'lecdle',
    component: DleView,
    props: { game: 'lecdle' },
  },
  {
    path: '/lfldle',
    name: 'lfldle',
    component: DleView,
    props: { game: 'lfldle' },
  },

  { path: '/credits', name: 'credits', component: CreditsView },
  { path: '/confidentialite', name: 'privacy', component: PrivacyPolicyView },
  { path: '/mentions-legales', name: 'legal', component: LegalView },
  {
    path: '/:pathMatch(.*)*',
    name: 'not-found',
    component: NotFoundView,
  },
]

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes,
})

export default router
