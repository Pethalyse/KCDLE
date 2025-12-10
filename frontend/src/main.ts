import { createApp } from 'vue'
import { createPinia } from 'pinia'

import App from './App.vue'
import router from './router'

import './assets/style.css'
import {initRouterAnalytics} from "@/analytics.ts";
import {initAds} from "@/ads.ts";
import {initAuthStore, useAuthStore} from "@/stores/auth.ts";

const app = createApp(App)
app.use(createPinia())
app.use(router)

initAuthStore();
initRouterAnalytics(router)
initAds('ethical')

app.mount('#app')
