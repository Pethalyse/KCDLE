<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, watch } from 'vue'
import SimpleImg from '@/components/SimpleImg.vue'

type Theme = 'kcdle' | 'lecdle' | 'lfldle' | 'default'

const props = defineProps<{
  open: boolean
  theme: Theme
}>()

const emit = defineEmits<{
  (e: 'close'): void
}>()

const dleCode = computed<'KCDLE' | 'LECDLE' | 'LFLDLE'>(() => {
  if (props.theme === 'lecdle') return 'LECDLE'
  if (props.theme === 'lfldle') return 'LFLDLE'
  return 'KCDLE'
})

const trueClass = computed(() => `${dleCode.value}_guess_true`)
const falseClass = computed(() => `${dleCode.value}_guess_false`)

const title = computed(() => `Règles ${dleCode.value}`)

function close() {
  emit('close')
}

function onKeydown(e: KeyboardEvent) {
  if (!props.open) return
  if (e.key === 'Escape') close()
}

watch(
  () => props.open,
  (v) => {
    if (typeof document === 'undefined') return
    document.body.style.overflow = v ? 'hidden' : ''
  },
  { immediate: true },
)

onMounted(() => window.addEventListener('keydown', onKeydown))
onBeforeUnmount(() => {
  window.removeEventListener('keydown', onKeydown)
  if (typeof document !== 'undefined') document.body.style.overflow = ''
})

function rotateStyle(deg: number) {
  return { rotate: `${deg}deg` }
}
</script>

<template>
  <Teleport to="body">
    <div
      v-if="open"
      class="rules-overlay"
      role="dialog"
      aria-modal="true"
      :aria-label="title"
      @click.self="close"
    >
      <div class="rules-panel">
        <div class="rules-head">
          <div class="rules-title">{{ title }}</div>
          <button type="button" class="rules-close" aria-label="Fermer" @click="close">×</button>
        </div>

        <div class="rules-body">
          <div class="rules-section">
            <div class="rules-section-title">Objectif</div>
            <p class="rules-text">
              Devine le joueur du jour en proposant des noms. <br> Après chaque essai, la grille te donne des indices pour
              te rapprocher de la bonne réponse.
            </p>
          </div>

          <div class="rules-section">
            <div class="rules-section-title">Couleurs</div>

            <div class="legend-grid">
              <div class="legend-row">
                <div class="legend-box" :class="trueClass">OK</div>
                <div class="legend-label">
                  <div class="legend-label-title">Case claire</div>
                  <div class="legend-label-text">La valeur est correcte.</div>
                </div>
              </div>

              <div class="legend-row">
                <div class="legend-box" :class="falseClass">KO</div>
                <div class="legend-label">
                  <div class="legend-label-title">Case foncée</div>
                  <div class="legend-label-text">La valeur ne correspond pas.</div>
                </div>
              </div>
            </div>
          </div>

          <div class="rules-section">
            <div class="rules-section-title">Flèches</div>
            <p class="rules-text">
              Quand une case concerne un nombre, une flèche indique si la valeur du joueur recherché est
              plus grande ou plus petite que ton essai.
            </p>

            <div class="arrows-grid">
              <div class="arrow-card">
                <div class="arrow-icon">
                  <SimpleImg img="arrow-age.png" alt="Plus" :style="rotateStyle(-90)" />
                </div>
                <div class="arrow-text">
                  <div class="arrow-title">↑ Plus</div>
                  <div class="arrow-sub">Le joueur recherché a une valeur plus grande (plus âgé…).</div>
                </div>
              </div>

              <div class="arrow-card">
                <div class="arrow-icon">
                  <SimpleImg img="arrow-age.png" alt="Moins" :style="rotateStyle(90)" />
                </div>
                <div class="arrow-text">
                  <div class="arrow-title">↓ Moins</div>
                  <div class="arrow-sub">Le joueur recherché a une valeur plus petite (plus jeune…).</div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<style scoped>
.rules-overlay,
.rules-overlay * {
  box-sizing: border-box;
}

.rules-overlay {
  position: fixed;
  inset: 0;
  z-index: 1200;
  background: rgba(0, 0, 0, 0.6);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 18px;
}

.rules-panel {
  width: min(720px, 100%);
  max-height: min(82vh, 860px);
  background: rgba(10, 12, 18, 0.96);
  border: 1px solid rgba(255, 255, 255, 0.12);
  border-radius: 16px;
  box-shadow: 0 18px 50px rgba(0, 0, 0, 0.6);
  overflow: hidden;
  display: flex;
  flex-direction: column;
  animation: rules-in 180ms ease-out;
}

.rules-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 16px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.12);
}

.rules-title {
  font-weight: 800;
  letter-spacing: 0.02em;
  color: #f5f7ff;
}

.rules-close {
  width: 34px;
  height: 34px;
  border-radius: 10px;
  border: 1px solid rgba(255, 255, 255, 0.18);
  background: rgba(255, 255, 255, 0.06);
  color: #fff;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 20px;
  line-height: 1;
}

.rules-close:hover {
  background: rgba(255, 255, 255, 0.12);
}

.rules-body {
  padding: 14px 16px 18px;
  overflow: auto;
  color: rgba(245, 247, 255, 0.92);
}

.rules-section {
  margin-top: 14px;
}

.rules-section:first-child {
  margin-top: 0;
}

.rules-section-title {
  font-weight: 700;
  color: #f5f7ff;
  margin-bottom: 8px;
}

.rules-text {
  margin: 0;
  line-height: 1.45;
  opacity: 0.95;
}

.legend-grid {
  display: flex;
  flex-direction: row;
  justify-content: center;
  gap: 10px;
}

.legend-row {
  display: flex;
  gap: 12px;
  align-items: center;
}

.legend-box {
  width: 68px;
  height: 44px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 800;
  border: 1px solid rgba(255, 255, 255, 0.14);
}

.legend-label-title {
  font-weight: 700;
  color: #f5f7ff;
}

.legend-label-text {
  font-size: 0.92rem;
  opacity: 0.9;
}

.arrows-grid {
  margin-top: 10px;
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
}

.arrow-card {
  border: 1px solid rgba(255, 255, 255, 0.12);
  background: rgba(255, 255, 255, 0.06);
  border-radius: 14px;
  padding: 10px;
  display: flex;
  gap: 10px;
  align-items: center;
}

.arrow-icon {
  width: 40px;
  height: 40px;
  border-radius: 12px;
  background: rgba(255, 255, 255, 0.08);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.arrow-icon img {
  width: 22px;
  height: 22px;
}

.arrow-title {
  font-weight: 800;
  color: #f5f7ff;
}

.arrow-sub {
  font-size: 0.9rem;
  opacity: 0.9;
}

:deep(.KCDLE_guess_true),
:deep(.LECDLE_guess_true),
:deep(.LFLDLE_guess_true) {
  color: #0a0c12;
}

:deep(.KCDLE_guess_false),
:deep(.LECDLE_guess_false),
:deep(.LFLDLE_guess_false) {
  color: #f5f7ff;
}

@media (max-width: 560px) {
  .arrows-grid {
    grid-template-columns: 1fr;
  }

  .legend-row {
    align-items: flex-start;
  }

  .legend-box {
    width: 62px;
  }
}

@keyframes rules-in {
  0% {
    opacity: 0;
    transform: translateY(8px) scale(0.985);
  }
  100% {
    opacity: 1;
    transform: translateY(0) scale(1);
  }
}
</style>
