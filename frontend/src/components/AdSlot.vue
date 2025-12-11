<script setup lang="ts">
import { onMounted, watch, computed } from 'vue'
import { renderSlot } from '@/ads'
import {useCookieConsent} from "@/composables/useCookieConsent.ts";

const props = defineProps<{
  id: string
  kind?: 'inline' | 'sidebar' | 'banner'
}>()

const slotId = computed(() => props.id)
const publisherId = import.meta.env.PUBLISHER_ID;
const adSenseId = import.meta.env.AD_SENSE_ID;

const REAL_ADS_ENABLED = import.meta.env.VITE_ENV === "production";

const {
  adsChecked,
} = useCookieConsent()

function adsExists() : boolean {
  if(REAL_ADS_ENABLED)
    return publisherId || adSenseId;
  return true;
}

onMounted(() => {
  if (REAL_ADS_ENABLED) {
    renderSlot(slotId.value)
  }
})

watch(slotId, (newVal) => {
  if (REAL_ADS_ENABLED) {
    renderSlot(newVal)
  }
})
</script>

<template>
  <div v-if="adsChecked && adsExists()"
    class="ad-slot"
    :data-kind="kind || 'inline'"
  >
    <div class="ad-card">
      <div class="ad-header">
        <span class="ad-label">Annonce</span>
      </div>

      <div
        v-if="REAL_ADS_ENABLED"
        class="ad-content ethical-ad"
        :data-ea-publisher="publisherId"
        :data-ea-type="kind === 'sidebar' ? 'image' : 'text'"
        :data-ea-manual="true"
      ></div>

      <div v-else class="ad-content ad-placeholder">
        <div class="fake-title">[PUB DE TEST]</div>
        <div class="fake-text">
          Ici, il y aura une publicité quand KCDLE sera en ligne 🚀
        </div>
      </div>

    <div class="ad-footer">
      <span>Publicité non intrusive pour soutenir KCDLE 💙</span>
    </div>
  </div>
  </div>
</template>

<style scoped>
.ad-slot {
  display: flex;
  justify-content: center;
  margin: 12px 0;
}

.ad-slot[data-kind='inline'] {
  margin: 16px 0;
}

.ad-slot[data-kind='banner'] {
  margin: 20px 0;
}

.ad-slot[data-kind='sidebar'] {
  margin: 8px 0;
}

.ad-card {
  width: 100%;
  max-width: 420px;
  padding: 10px 12px;
  border-radius: 10px;

  background: radial-gradient(
    circle at top left,
    var(--dle-color-main, #111827),
    var(--dle-color-accent, #020617)
  );
  border: 1px solid var(--dle-accent-soft, rgba(148, 163, 184, 0.45));
  box-shadow: 0 10px 20px rgba(0, 0, 0, 0.5);

  display: flex;
  flex-direction: column;
  gap: 6px;
  font-size: 0.85rem;
  color: #e5e7eb;
}


.ad-slot[data-kind='banner'] .ad-card {
  max-width: 720px;
}

.ad-slot[data-kind='sidebar'] .ad-card {
  max-width: 260px;
  font-size: 0.8rem;
}

.ad-header {
  display: flex;
  justify-content: flex-start;
  align-items: center;
}

.ad-label {
  font-size: 0.7rem;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  padding: 2px 8px;
  border-radius: 999px;
  background: var(--dle-accent-pill, rgba(59, 130, 246, 0.15));
  border: 1px solid var(--dle-accent-pill-border, rgba(59, 130, 246, 0.7));
  color: #e5e7eb;
}


.ad-content {
  margin-top: 4px;
  min-height: 60px;
  display: flex;
  align-items: center;
}

.ad-content::before {
  content: 'Chargement de la publicité…';
  font-size: 0.75rem;
  opacity: 0.5;
}

.ethical-ad > * {

}

.ad-footer {
  margin-top: 2px;
  display: flex;
  justify-content: flex-start;
  font-size: 0.7rem;
  opacity: 0.6;
}

.ad-card:hover {
  border-color: var(--dle-accent-strong, rgba(59, 130, 246, 0.8));
  box-shadow: 0 14px 28px rgba(0, 0, 0, 0.7);
  transform: translateY(-1px);
  transition: all 120ms ease-out;
}


.ad-placeholder .fake-title {
  font-weight: 600;
  margin-bottom: 2px;
}
.ad-placeholder .fake-text {
  opacity: 0.7;
  font-size: 0.75rem;
}
</style>
