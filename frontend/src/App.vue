<script setup lang="ts">
import FooterKcdle from '@/components/FooterKcdle.vue'
import CookieConsent from '@/components/CookieConsent.vue'
import { RouterView, useRoute } from 'vue-router'
import FlashMessage from "@/components/FlashMessage.vue";
import HeaderKcdle from "@/components/HeaderKcdle.vue";
const route = useRoute()

const googleCmpEnabled = import.meta.env.VITE_GOOGLE_CMP_ENABLED === '1'
</script>

<template>
  <div id="app-wrapper">
    <HeaderKcdle />
    <RouterView v-slot="{ Component }">
      <component :is="Component" :key="route.fullPath" />
    </RouterView>
    <FooterKcdle />
    <CookieConsent v-if="!googleCmpEnabled" />
    <FlashMessage />
  </div>
</template>

<style scoped>
</style>
