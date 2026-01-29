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

function goDiscord() {
  window.open(
    'https://discord.com/oauth2/authorize?client_id=1465309604195078155',
    '_blank'
  );
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

      <button
        class="footer-link bot-discord"
        @click="goDiscord"
      >
        Bot discord
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
  padding: 6px 8px;
  border-radius: 8px;
  transition: background 0.15s ease, transform 0.15s ease, opacity 0.15s ease;
}

.footer-link:hover {
  text-decoration: underline;
}

.footer-link:active {
  transform: translateY(1px);
}

.footer-separator {
  opacity: 0.7;
}

.bot-discord {
  text-decoration: none;
  font-weight: 900;
  letter-spacing: 0.2px;
  padding: 7px 12px;
  border-radius: 999px;
  border: 1px solid rgba(88, 101, 242, 0.85);
  background: rgba(88, 101, 242, 0.18);
  color: #dfe3ff;
  box-shadow: 0 6px 14px rgba(0, 0, 0, 0.25);
}

.bot-discord:hover {
  text-decoration: none;
  background: rgba(88, 101, 242, 0.28);
  transform: translateY(-1px);
}

.bot-discord:active {
  transform: translateY(0);
}

.bot-discord:focus-visible {
  outline: none;
  box-shadow:
    0 0 0 3px rgba(88, 101, 242, 0.25),
    0 6px 14px rgba(0, 0, 0, 0.25);
}
</style>
