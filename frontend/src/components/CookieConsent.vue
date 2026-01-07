<script setup lang="ts">
import { computed } from 'vue'
import { useCookieConsent } from '@/composables/useCookieConsent'

const {
  visible,
  showDetails,
  analyticsChecked,
  adsChecked,
  acceptAll,
  refuseAll,
  savePreferences,
  toggleDetails,
} = useCookieConsent()

const summary = computed(() => {
  const items: string[] = ['Essentiels']
  if (analyticsChecked.value) items.push('Audience')
  if (adsChecked.value) items.push('Pubs personnalisées')
  return items.join(' · ')
})
</script>

<template>
  <Teleport to="body">
    <div
      v-if="visible"
      class="cc-overlay"
      role="dialog"
      aria-modal="true"
      aria-label="Gestion des cookies"
    >
      <div class="cc-card">
        <div class="cc-head">
          <div class="cc-title">
            <div>
              <h2>Gestion des cookies</h2>
              <p class="cc-subtitle">
                Choisis ce que tu autorises.
              </p>
            </div>
          </div>

          <button
            type="button"
            class="cc-link"
            @click="toggleDetails"
          >
            {{ showDetails ? 'Masquer' : 'Personnaliser' }}
          </button>
        </div>

        <div class="cc-body">
          <p class="cc-text">
            KCDLE utilise des technologies de stockage (cookies et/ou stockage local) pour faire fonctionner le site
            (connexion, sauvegarde de parties, préférences) et, si tu l’acceptes, pour mesurer l’audience.
            La publicité peut être affichée sans personnalisation. La personnalisation publicitaire (cookies/identifiants)
            nécessite ton accord.
          </p>

          <div class="cc-list">
            <div class="cc-item">
              <div class="cc-item-left">
                <strong>Essentiels</strong>
                <p>
                  Indispensables au fonctionnement : session, sauvegarde de parties, préférences techniques.
                </p>
              </div>
              <span class="cc-pill ok">Toujours actifs</span>
            </div>

            <div
              v-if="showDetails"
              class="cc-item"
            >
              <div class="cc-item-left">
                <strong>Mesure d’audience</strong>
                <p>
                  Statistiques de fréquentation via Plausible pour améliorer le site (chargé uniquement si accepté).
                </p>
              </div>

              <label class="cc-switch" aria-label="Activer la mesure d’audience">
                <input
                  v-model="analyticsChecked"
                  type="checkbox"
                />
                <span class="cc-slider" />
              </label>
            </div>

            <div
              v-if="showDetails"
              class="cc-item"
            >
              <div class="cc-item-left">
                <strong>Publicités personnalisées</strong>
                <p>
                  Autoriser la personnalisation des pubs (AdSense). Sans accord, on demande des publicités non personnalisées.
                </p>
              </div>

              <label class="cc-switch" aria-label="Activer les publicités personnalisées">
                <input
                  v-model="adsChecked"
                  type="checkbox"
                />
                <span class="cc-slider" />
              </label>
            </div>
          </div>

          <div class="cc-footer">
            <div class="cc-note">
              Tu peux changer d’avis à tout moment via <strong>« Paramètres des cookies »</strong> dans le footer.
            </div>

            <div class="cc-actions">
              <button
                type="button"
                class="cc-btn ghost"
                @click="refuseAll"
              >
                Tout refuser
              </button>

              <button
                type="button"
                class="cc-btn soft"
                @click="savePreferences"
              >
                Enregistrer
              </button>

              <button
                type="button"
                class="cc-btn primary"
                @click="acceptAll"
              >
                Tout accepter
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<style scoped>
.cc-overlay {
  position: fixed;
  inset: 0;
  z-index: 9999;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px;
  background: rgba(0, 0, 0, 0.55);
  backdrop-filter: blur(6px);
}

.cc-card {
  width: min(860px, 100%);
  border-radius: 16px;
  background: rgba(17, 24, 39, 0.92);
  border: 1px solid rgba(255, 255, 255, 0.10);
  box-shadow: 0 20px 70px rgba(0, 0, 0, 0.55);
  overflow: hidden;
}

.cc-head {
  display: grid;
  grid-template-columns: 2fr auto;
  align-items: flex-start;
  gap: 12px;
  padding: 16px 18px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}

.cc-title {
  display: flex;
  gap: 12px;
  align-items: center;
  justify-self: center;
}

.cc-badge {
  width: 40px;
  height: 40px;
  border-radius: 12px;
  display: grid;
  place-items: center;
  background: rgba(59, 130, 246, 0.15);
  border: 1px solid rgba(59, 130, 246, 0.35);
}

h2 {
  margin: 0;
  color: #f9fafb;
  font-size: 1.05rem;
  font-weight: 700;
}

.cc-subtitle {
  margin: 2px 0 0 0;
  color: rgba(229, 231, 235, 0.85);
  font-size: 0.9rem;
}

.cc-summary {
  opacity: 0.95;
}

.cc-link {
  border: none;
  background: transparent;
  color: #93c5fd;
  cursor: pointer;
  font-size: 0.9rem;
  text-decoration: underline;
  padding: 6px 8px;
  border-radius: 10px;
  justify-self: end;
}

.cc-link:hover {
  background: rgba(147, 197, 253, 0.10);
  text-decoration: none;
}

.cc-body {
  padding: 14px 18px 16px 18px;
}

.cc-text {
  margin: 0 0 12px 0;
  color: rgba(243, 244, 246, 0.92);
  line-height: 1.45;
  font-size: 0.92rem;
}

.cc-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.cc-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 14px;
  padding: 12px 12px;
  border-radius: 14px;
  background: rgba(255, 255, 255, 0.04);
  border: 1px solid rgba(255, 255, 255, 0.08);
}

.cc-item-left strong {
  color: #f9fafb;
  font-size: 0.95rem;
}

.cc-item-left p {
  margin: 3px 0 0 0;
  color: rgba(229, 231, 235, 0.85);
  font-size: 0.85rem;
  line-height: 1.35;
}

.cc-pill {
  font-size: 0.78rem;
  padding: 6px 10px;
  border-radius: 999px;
  border: 1px solid transparent;
  white-space: nowrap;
}

.cc-pill.ok {
  background: rgba(16, 185, 129, 0.12);
  border-color: rgba(16, 185, 129, 0.45);
  color: rgba(209, 250, 229, 0.95);
}

.cc-footer {
  margin-top: 12px;
  display: flex;
  gap: 12px;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
}

.cc-note {
  color: rgba(229, 231, 235, 0.75);
  font-size: 0.85rem;
}

.cc-actions {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

.cc-btn {
  border-radius: 999px;
  padding: 8px 14px;
  font-size: 0.9rem;
  cursor: pointer;
  border: 1px solid transparent;
  font-weight: 600;
}

.cc-btn.primary {
  background: #3b82f6;
  color: white;
}

.cc-btn.primary:hover {
  filter: brightness(1.06);
}

.cc-btn.ghost {
  background: transparent;
  color: rgba(229, 231, 235, 0.92);
  border-color: rgba(255, 255, 255, 0.14);
}

.cc-btn.ghost:hover {
  background: rgba(255, 255, 255, 0.06);
}

.cc-btn.soft {
  background: rgba(255, 255, 255, 0.06);
  color: rgba(229, 231, 235, 0.92);
  border-color: rgba(255, 255, 255, 0.10);
}

.cc-btn.soft:hover {
  background: rgba(255, 255, 255, 0.09);
}

.cc-switch {
  position: relative;
  width: 46px;
  height: 28px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex: 0 0 auto;
}

.cc-switch input {
  position: absolute;
  opacity: 0;
  width: 0;
  height: 0;
}

.cc-slider {
  width: 46px;
  height: 28px;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.10);
  border: 1px solid rgba(255, 255, 255, 0.14);
  position: relative;
  transition: 180ms ease;
}

.cc-slider::after {
  content: '';
  position: absolute;
  top: 3px;
  left: 3px;
  width: 22px;
  height: 22px;
  border-radius: 999px;
  background: rgba(229, 231, 235, 0.95);
  transition: 180ms ease;
}

.cc-switch input:checked + .cc-slider {
  background: rgba(59, 130, 246, 0.25);
  border-color: rgba(59, 130, 246, 0.45);
}

.cc-switch input:checked + .cc-slider::after {
  transform: translateX(18px);
  background: rgba(255, 255, 255, 0.98);
}

@media (max-width: 540px) {
  .cc-head {
    align-items: center;
  }

  .cc-link {
    white-space: nowrap;
  }

  .cc-footer {
    flex-direction: column;
    align-items: stretch;
  }

  .cc-actions {
    width: 100%;
    justify-content: center;
  }
}
</style>
