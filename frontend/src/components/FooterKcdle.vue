<script setup lang="ts">
import { useRouter } from 'vue-router'
import {useCookieConsent} from "@/composables/useCookieConsent.ts";

const router = useRouter()
const { openCookieManager } = useCookieConsent()

const googleCmpEnabled = import.meta.env.VITE_GOOGLE_CMP_ENABLED === '1'

function goCredits() {
  router.push({ name: 'credits' })
}

function goPrivacy() {
  router.push({ name: 'privacy' })
}

function goLegal() {
  router.push({ name: 'legal' })
}

</script>

<template>
  <footer class="footer-kcdle">
    <div class="footer-content">
      <span class="footer-left">
        © 2024 - {{ new Date().getFullYear() }} KCDLE
      </span>

      <button
        class="footer-link"
        @click="goCredits"
      >
        Crédits & Remerciements
      </button>

      <button
        class="footer-link"
        @click="goPrivacy"
      >
        Politique de confidentialité
      </button>

      <button
        class="footer-link"
        @click="goLegal"
      >
        Mentions légales
      </button>

      <button
        class="footer-link"
        @click="openCookieManager"
        v-if="!googleCmpEnabled"
      >
        Paramètres des cookies
      </button>
    </div>
  </footer>
</template>

<style scoped>
.footer-kcdle {
  width: 100%;
  padding: 12px 0;
  background: rgba(0, 0, 0, 0.35);
  backdrop-filter: blur(6px);
  color: #ddd;
  font-size: 0.85rem;
  position: fixed;
  bottom: 0;
  left: 0;
  z-index: 5000;
  display: flex;
  justify-content: center;
  border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.footer-content {
  width: 95%;
  max-width: 1200px;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.footer-links {
  display: flex;
  align-items: center;
  gap: 8px;
}

.footer-link {
  border: none;
  background: none;
  color: white;
  font-size: 0.85rem;
  cursor: pointer;
}

.footer-link:hover {
  text-decoration: underline;
}

.footer-separator {
  opacity: 0.7;
}
</style>
