import { createApp } from 'vue'
import { createPinia } from 'pinia'

import App from './App.vue'
import router from './router'

import './assets/style.css'
import { initRouterAnalytics } from '@/analytics.ts'
import { autoInitAds, loadAds } from '@/ads.ts'
import { initAuthStore } from '@/stores/auth.ts'
import { initApi } from '@/api/initApi.ts'
import { initPvpRuntime } from '@/pvpRuntime.ts'

const app = createApp(App)

const pinia = createPinia()
app.use(pinia)
app.use(router)

initAuthStore()
initApi(pinia, router)
initPvpRuntime(router)

// initRouterAnalytics(router)
autoInitAds()
loadAds()

app.mount('#app')
