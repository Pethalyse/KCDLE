<script setup lang="ts">
import FooterKcdle from '@/components/FooterKcdle.vue'
import CookieConsent from '@/components/CookieConsent.vue'
import { RouterView, useRoute } from 'vue-router'
import FlashMessage from "@/components/FlashMessage.vue";
import HeaderKcdle from "@/components/HeaderKcdle.vue";
const route = useRoute()
</script>

<template>
  <head>
    <title>KCDLE</title>
    <link rel="icon" href="/images/HOMEDLE_Header-rbg.png" />
    <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-5212930903717514"
            crossorigin="anonymous"></script>
  </head>
  <div id="app-wrapper">
    <HeaderKcdle />
    <RouterView v-slot="{ Component }">
      <component :is="Component" :key="route.fullPath" />
    </RouterView>
    <FooterKcdle />
    <CookieConsent />
    <FlashMessage />
  </div>
</template>

<style scoped>
</style>
