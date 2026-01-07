<script setup lang="ts">
import GameButton from '@/components/GameButton.vue'
import AdSlot from "@/components/AdSlot.vue"


const data = [
  { dle: 'KCDLE', active: true },
  { dle: 'LECDLE', active: true },
  { dle: 'LFLDLE', active: true },
]
</script>

<template>
  <div class="home-page">
    <header class="home-hero">
      <div class="home-hero-inner">
        <div class="home-logo">
          <img
            src="/images/HOMEDLE_Header-rbg.png"
            alt="kcdle.fr"
          />
        </div>
        <p class="home-subtitle">
          Choisis ton DLE et tente de deviner le joueur du jour.
        </p>
      </div>
    </header>

    <section class="home-ad-top">
      <AdSlot id="home-top-1" kind="banner" />
    </section>

    <main class="home-body">
      <div class="btn-container">
        <GameButton
          v-for="val in data"
          :key="val.dle"
          :data="val"
        />
      </div>

      <div class="bottom-ad">
        <AdSlot id="dle-bottom-1" kind="inline" />
        <AdSlot id="dle-bottom-1" kind="inline" />
      </div>
    </main>
  </div>
</template>

<style scoped>
.home-page {
  min-height: 100vh;
  padding: 20px 12px 28px;
  display: flex;
  flex-direction: column;
  align-items: center;
  color: #f3f3f3;
  background: radial-gradient(circle at top, #20263A 0, #05060A 75%);
}

.home-hero {
  width: 100%;
  max-width: 900px;
  display: flex;
  justify-content: center;
  margin-bottom: 20px;
}

.home-hero-inner {
  text-align: center;
}

.home-title {
  font-size: clamp(2.2rem, 4vw, 2.6rem);
  font-weight: 800;
  letter-spacing: 0.08em;
}

.home-subtitle {
  margin-top: 6px;
  font-size: 0.95rem;
  opacity: 0.8;
}

.home-ad-top {
  width: 100%;
  max-width: 900px;
  display: flex;
  justify-content: center;
  margin: 0 0 14px;
}

.home-body {
  width: 100%;
  max-width: 900px;
  display: flex;
  flex-direction: column;
  align-items: center;
}

.btn-container {
  width: 100%;
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin: 12px 0 24px;
}

.home-logo {
  display: flex;
  justify-content: center;
  margin-bottom: 12px;
}

.home-logo img {
  width: auto;
  object-fit: contain;
  filter: drop-shadow(0 0 6px rgba(0,0,0,0.4));
}

.bottom-ad {
  display: flex;
}

@media (max-width: 640px) {
  .btn-container {
    gap: 14px;
  }

  .home-logo img {
    width: 50%;
  }

  .home-subtitle {
    font-size: 0.75rem;
  }
}
</style>
