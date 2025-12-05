<script setup lang="ts">
import FooterKcdle from '@/components/FooterKcdle.vue'
import CookieConsent from '@/components/CookieConsent.vue'
import { RouterView, useRoute } from 'vue-router'
const route = useRoute()
</script>

<template>
  <div id="app-wrapper">
    <RouterView v-slot="{ Component }">
      <component :is="Component" :key="route.fullPath" />
    </RouterView>
    <FooterKcdle />
    <CookieConsent />
  </div>
</template>

<style scoped>
</style>
