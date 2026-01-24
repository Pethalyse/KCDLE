<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, watch } from 'vue'
import SimpleImg from '@/components/SimpleImg.vue'
import GameButton from '@/components/GameButton.vue'
import { trackEvent } from '@/analytics.ts'
import AdSlot from '@/components/AdSlot.vue'
import { handleError } from '@/utils/handleError.ts'
import { useAuthStore } from '@/stores/auth.ts'

const auth = useAuthStore()

type DleLink = {
  dle: string
  active: boolean
}

const props = defineProps<{
  dleCode: string
  guesses: any[]
  open: boolean
}>()

const emit = defineEmits<{
  (e: 'close'): void
}>()

const headerText = computed(
  () => `J'ai joué au ${props.dleCode} sur https://kcdle.com/ et voici mes résultats :\n`,
)

const hashtagText = '#KCORP'

const resultLines = computed(() => {
  return props.guesses.map((g) => {
    const comparison = g?.comparison?.fields || {}
    const keys = Object.keys(comparison)
    if (keys.length === 0) {
      return g?.correct ? '🟩' : '🟥'
    }
    return keys
      .map((k) => {
        const v = comparison[k]
        if (v === 'correct' || v === true || v === 1) {
          return '🟩'
        }
        return '🟥'
      })
      .join('')
  })
})

const fullText = computed(() => {
  const reversed = [...resultLines.value].reverse()
  return headerText.value + reversed.join('\n') + '\n' + hashtagText
})

function getOthersDle(): DleLink[] {
  if (props.dleCode === 'KCDLE') {
    return [
      { dle: 'LECDLE', active: true },
      { dle: 'LFLDLE', active: true },
    ]
  }
  if (props.dleCode === 'LECDLE') {
    return [
      { dle: 'KCDLE', active: true },
      { dle: 'LFLDLE', active: true },
    ]
  }
  if (props.dleCode === 'LFLDLE') {
    return [
      { dle: 'KCDLE', active: true },
      { dle: 'LECDLE', active: true },
    ]
  }
  return []
}

function shareOnX() {
  const url = 'https://twitter.com/intent/tweet?text=' + encodeURIComponent(fullText.value)
  window.open(url, '_blank')
}

async function copyText() {
  try {
    await navigator.clipboard.writeText(fullText.value)
  } catch (e) {
    handleError(e)
  }
}

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

function handleGameButton(targetGame: string) {
  trackEvent('dle_switch_from_gg_popup', {
    from: props.dleCode,
    to: targetGame,
  })
}

onMounted(() => window.addEventListener('keydown', onKeydown))
onBeforeUnmount(() => {
  window.removeEventListener('keydown', onKeydown)
  if (typeof document !== 'undefined') document.body.style.overflow = ''
})
</script>

<template>
  <Teleport to="body">
    <div
      v-if="open"
      class="gg-overlay"
      role="dialog"
      aria-modal="true"
      :aria-label="`Résultats ${dleCode}`"
      @click.self="close"
    >
      <div class="gg-panel">
        <div class="gg-head">
          <div class="gg-title">Bravo !</div>
          <button type="button" class="gg-close" aria-label="Fermer" @click="close">×</button>
        </div>

        <div class="gg-body">
          <div class="gg-kicker">Tu as trouvé le joueur</div>

          <div class="historique-visuel">
            <div class="historique-header">{{ headerText }}</div>
            <div class="historique-grid">
              <p v-for="(line, index) in resultLines" :key="index">{{ line }}</p>
            </div>
            <div class="historique-hashtag">{{ hashtagText }}</div>
          </div>

          <div class="gg-share">
            <div class="gg-share-title">Partage ta réussite :</div>

            <div class="gg-share-buttons">
              <button type="button" class="share-btn" @click="shareOnX">
                <SimpleImg class="x_logo" alt="Partager sur X" img="x_logo.png" />
              </button>

              <button type="button" class="share-btn" @click="copyText">
                <SimpleImg class="x_logo" alt="Copier" img="copy.png" />
              </button>
            </div>
          </div>

          <div v-if="!auth.isAuthenticated" class="gg-auth">
            <router-link to="login" class="white">
              <p class="white">Connecte toi pour enregistrer tes résultats !</p>
            </router-link>
          </div>

          <div class="gg-ad">
            <AdSlot id="gg-popup-bottom" kind="banner" />
          </div>
        </div>
      </div>

      <div class="gg-panel">
          <div class="gg-other-dles">
            <GameButton
              v-for="val in getOthersDle()"
              :key="val.dle"
              :data="val"
              @click="handleGameButton(val.dle)"
            />
          </div>
        </div>
      </div>
  </Teleport>
</template>

<style scoped>
.gg-overlay,
.gg-overlay * {
  box-sizing: border-box;
}

.gg-overlay {
  position: fixed;
  inset: 0;
  z-index: 9999;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 14px 10px;
  background: rgba(0, 0, 0, 0.58);
  backdrop-filter: blur(6px);
  gap: 10px;
}

.gg-panel {
  width: min(520px, 94vw);
  max-height: 92vh;
  border-radius: 18px;
  border: 1px solid rgba(255, 255, 255, 0.10);
  background: radial-gradient(circle at top, #20263a, rgba(10, 12, 22, 0.92) 70%);
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.65);
  overflow: hidden;
  display: flex;
  flex-direction: column;
  animation: gg-panel-in 180ms ease-out;
}

.gg-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 12px 10px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.08);
  flex: 0 0 auto;
}

.gg-title {
  font-weight: 800;
  letter-spacing: 0.4px;
  font-size: 1.05rem;
  color: rgba(255, 255, 255, 0.95);
}

.gg-close {
  width: 34px;
  height: 34px;
  border-radius: 12px;
  border: 1px solid rgba(255, 255, 255, 0.16);
  background: rgba(255, 255, 255, 0.06);
  color: rgba(255, 255, 255, 0.9);
  cursor: pointer;
  font-size: 1.35rem;
  line-height: 1;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  transition: transform 120ms ease, background 120ms ease;
}

.gg-close:hover {
  transform: translateY(-1px);
  background: rgba(255, 255, 255, 0.12);
}

.gg-body {
  padding: 12px;
  display: flex;
  flex-direction: column;
  gap: 12px;
  overflow: hidden;
  flex: 1 1 auto;
  min-height: 0;
}

.gg-kicker {
  font-size: 1rem;
  font-weight: 800;
  color: rgba(255, 255, 255, 0.95);
  text-align: center;
}

.historique-visuel {
  white-space: pre-wrap;
  padding: 12px;
  border-radius: 14px;
  border: 1px solid rgba(255, 255, 255, 0.10);
  background: rgba(255, 255, 255, 0.06);
}

.historique-header {
  opacity: 0.9;
  margin-bottom: 8px;
  text-align: center;
  font-size: 0.95rem;
}

.historique-grid {
  display: grid;
  gap: 2px;
  justify-items: center;
}

.historique-grid p {
  margin: 0;
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace;
  font-size: 0.95rem;
}

.historique-hashtag {
  margin-top: 10px;
  font-weight: 800;
  letter-spacing: 0.2px;
  opacity: 0.95;
  text-align: center;
}

.gg-share-title {
  font-weight: 700;
  opacity: 0.95;
  text-align: center;
}

.gg-share-buttons {
  display: flex;
  justify-content: center;
  gap: 10px;
  margin-top: 8px;
}

.share-btn {
  border: 1px solid rgba(255, 255, 255, 0.12);
  background: rgba(255, 255, 255, 0.04);
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 10px 12px;
  border-radius: 14px;
  transition: transform 120ms ease, background 120ms ease;
}

.share-btn:hover {
  transform: translateY(-1px);
  background: rgba(255, 255, 255, 0.10);
}

.x_logo {
  padding: 0;
}

.gg-auth {
  text-align: center;
}

.white {
  color: white;
}

.gg-other-dles {
  display: flex;
  justify-content: center;
  gap: 10px;
  flex-wrap: wrap;
  padding-block: 30px;
}

.gg-other-dles :deep(.btn-game) {
  max-width: 42vw;
}

.gg-other-dles :deep(.btn-game img) {
  width: 100%;
  height: auto;
  max-height: 52px;
  object-fit: contain;
}

.gg-ad {
  display: none;
}

@media (max-width: 380px) {
  .gg-panel {
    width: 96vw;
  }

  .historique-header {
    font-size: 0.9rem;
  }

  .historique-grid p {
    font-size: 0.9rem;
  }

  .gg-other-dles {
    padding-block: 15px;
  }

  .gg-other-dles :deep(.btn-game) {
    width: 145px;
  }

  .gg-other-dles :deep(.btn-game img) {
    max-height: 46px;
  }
}

@media (max-height: 720px) {
  .gg-panel {
    min-height: 0;
  }

  .gg-body {
    gap: 10px;
  }

  .historique-visuel {
    padding: 10px;
  }

  .share-btn {
    padding: 9px 10px;
  }

  .gg-other-dles {
    padding-block: 15px;
  }

  .gg-other-dles :deep(.btn-game img) {
    max-height: 46px;
  }
}

@keyframes gg-panel-in {
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
