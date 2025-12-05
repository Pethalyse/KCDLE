<script setup lang="ts">
import FooterKcdle from '@/components/FooterKcdle.vue'
import CookieConsent from '@/components/CookieConsent.vue'
</script>

<template>
  <div id="app-wrapper">
    <RouterView />
    <FooterKcdle />
    <CookieConsent />
  </div>
</template>

<style scoped>
</style>
