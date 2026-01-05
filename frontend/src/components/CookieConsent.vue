<script setup lang="ts">
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
</script>

<template>
  <div
    v-if="visible"
    class="cookie-banner"
  >
    <div class="cookie-content">
      <h2>Gestion des cookies</h2>

      <p class="cookie-text">
        Nous utilisons des cookies et un stockage local pour faire fonctionner le jeu (essentiels) et, si vous l’acceptez,
        pour mesurer l’audience et afficher des publicités de nos partenaires.
      </p>

      <p class="cookie-text">
        Les cookies essentiels sont toujours activés, car ils sont nécessaires au fonctionnement du site (par exemple pour
        sauvegarder vos parties).
      </p>

      <button
        type="button"
        class="cookie-link"
        @click="toggleDetails"
      >
        {{ showDetails ? 'Masquer le détail' : 'Personnaliser' }}
      </button>

      <div
        v-if="showDetails"
        class="cookie-options"
      >
        <div class="cookie-option">
          <div>
            <strong>Cookies essentiels</strong>
            <p>Indispensables au fonctionnement du jeu (stockage de vos parties, préférences techniques).</p>
          </div>
          <span class="cookie-badge">Toujours activés</span>
        </div>

        <div class="cookie-option">
          <label>
            <input
              v-model="analyticsChecked"
              type="checkbox"
            />
            <strong>Mesure d’audience</strong>
          </label>
          <p>Statistiques anonymisées de fréquentation (Plausible) pour améliorer le site.</p>
        </div>

        <div class="cookie-option">
          <label>
            <input
              v-model="adsChecked"
              type="checkbox"
            />
            <strong>Publicité personnalisée</strong>
          </label>
          <p>Affichage de publicités adaptées à votre navigation (désactivé tant que vous ne l’acceptez pas).</p>
        </div>
      </div>

      <div class="cookie-actions">
        <button
          type="button"
          class="cookie-btn secondary"
          @click="refuseAll"
        >
          Tout refuser
        </button>
        <button
          type="button"
          class="cookie-btn secondary"
          @click="savePreferences"
        >
          Enregistrer mes choix
        </button>
        <button
          type="button"
          class="cookie-btn"
          @click="acceptAll"
        >
          Tout accepter
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.cookie-banner {
  position: fixed;
  z-index: 9999;
  left: 0;
  right: 0;
  bottom: 0;
  padding: 16px;
  display: flex;
  justify-content: center;
  pointer-events: none;
}

.cookie-content {
  max-width: 720px;
  width: 100%;
  background: #1f2937;
  color: #f9fafb;
  border-radius: 12px;
  padding: 16px 20px;
  box-shadow: 0 15px 40px rgba(0, 0, 0, 0.5);
  pointer-events: auto;
  font-size: 0.95rem;
}

.cookie-text {
  margin-bottom: 8px;
  line-height: 1.4;
  text-align: justify;
}

.cookie-link {
  background: none;
  border: none;
  color: #93c5fd;
  padding: 0;
  margin-top: 4px;
  cursor: pointer;
  font-size: 0.9rem;
  text-decoration: underline;
}

.cookie-options {
  margin-top: 12px;
  border-top: 1px solid rgba(148, 163, 184, 0.5);
  padding-top: 10px;
}

.cookie-option {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 8px;
  align-items: center;
}

.cookie-option p {
  margin-top: 2px;
  font-size: 0.85rem;
  opacity: 0.9;
}

.cookie-badge {
  font-size: 0.8rem;
  padding: 4px 8px;
  border-radius: 999px;
  background: rgba(16, 185, 129, 0.15);
  border: 1px solid rgba(16, 185, 129, 0.6);
}

.cookie-actions {
  margin-top: 12px;
  display: flex;
  justify-content: center;
  gap: 8px;
  flex-wrap: wrap;
}

.cookie-btn {
  border-radius: 999px;
  border: none;
  padding: 6px 16px;
  font-size: 0.9rem;
  cursor: pointer;
  background: #3b82f6;
  color: white;
  font-weight: 500;
}

.cookie-btn.secondary {
  background: #111827;
  border: 1px solid #4b5563;
  color: #e5e7eb;
}
</style>
