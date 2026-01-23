<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import SimpleImg from '@/components/SimpleImg.vue'
import GameButton from "@/components/GameButton.vue";
import {trackEvent} from "@/analytics.ts";
import AdSlot from "@/components/AdSlot.vue";
import {handleError} from "@/utils/handleError.ts";
import {useAuthStore} from "@/stores/auth.ts";

const auth = useAuthStore();

interface dle {
  dle : string,
  active : boolean
}

const props = defineProps<{
  dleCode: string
  guesses: any[]
}>()

const root = ref<HTMLElement | null>(null)

const headerText = computed(
  () => `J'ai joué au ${props.dleCode} sur https://kcdle.com/ et voici mes résultats :\n`,
)

const hashtagText = '#KCORP'

const resultLines = computed(() => {
  return props.guesses.map(g => {
    const comparison = g.comparison.fields || {}
    const keys = Object.keys(comparison)
    if (keys.length === 0) {
      return g.correct ? '🟩' : '🟥'
    }
    return keys
      .map(k => {
        const v = comparison[k]
        if (v === 'correct' || v === true || v === 1) {
          return '🟩'
        }
        return '🟥'
      })
      .join('')
  })
})

const shareBody = computed(() => {
  const reversed = [...resultLines.value].reverse()
  return reversed.join('\n') + '\n'
})

const fullText = computed(
  () => headerText.value + shareBody.value + hashtagText,
)

function getOthersDle() : dle[] {
  if(props.dleCode === 'KCDLE')
    return [
      { dle: 'LECDLE', active: true },
      { dle: 'LFLDLE', active: true },
    ]
  else if (props.dleCode === 'LECDLE')
    return [
      { dle: 'KCDLE', active: true },
      { dle: 'LFLDLE', active: true },
    ]
  else if (props.dleCode === 'LFLDLE')
    return [
      { dle: 'KCDLE', active: true },
      { dle: 'LECDLE', active: true },
    ]
  else
    return []
}

function shareOnX() {
  const url =
    'https://twitter.com/intent/tweet?text=' +
    encodeURIComponent(fullText.value)
  window.open(url, '_blank')
}

async function copyText() {
  try {
    await navigator.clipboard.writeText(fullText.value)
  } catch (e) {
    handleError(e)
  }
}

onMounted(() => {
  if (root.value) {
    root.value.scrollIntoView({ behavior: 'smooth', block: 'center' })
    setTimeout(() => {
      root.value && root.value.classList.add('fade-in')
    }, 500)
  }
})

function handleGameButton(targetGame: string) {
  trackEvent('dle_switch_from_gg_popup', {
    from: props.dleCode,
    to: targetGame,
  })
}
</script>

<template>
  <div
    ref="root"
    class="popup-gg"
  >
    <div class="gg-text">
      Bravo ! Tu as trouvé le joueur
    </div>

    <div class="historique-visuel">
      <div class="historique-header">
        {{ headerText }}
      </div>
      <div class="historique-grid">
        <p
          v-for="(line, index) in resultLines"
          :key="index"
        >
          {{ line }}
        </p>
      </div>
      <div class="historique-hashtag">
        {{ hashtagText }}
      </div>
    </div>

    <div class="gg-text-link">
      Partage ta réussite :
    </div>

    <div class="gg-share-buttons">
      <button
        type="button"
        class="share-btn"
        @click="shareOnX"
      >
        <SimpleImg
          class="x_logo"
          alt="Partager sur X"
          img="x_logo.png"
        />
      </button>

      <button
        type="button"
        class="share-btn"
        @click="copyText"
      >
        <SimpleImg
          class="x_logo"
          alt="Copier"
          img="copy.png"
        />
      </button>
    </div>

    <div v-if="!auth.isAuthenticated">
      <router-link to="login" class="white">
        <p class="white">Connecte toi pour enregistrer tes résultats !</p>
      </router-link>
    </div>

    <div class="gg-other-dles">
      <GameButton
        v-for="val in getOthersDle()"
        :key="val.dle"
        :data="val"
        @click="handleGameButton(val.dle)"
      ></GameButton>
    </div>

    <div class="popup-ad">
      <AdSlot id="gg-popup-bottom" kind="banner" />
    </div>
  </div>
</template>

<style>
.gg-other-dles {
  display: flex;
  justify-content: center;
  gap: 8px;
  margin-top: 20px;
  flex-direction: column;
}

.gg-other-dles .btn-game img{
  width: 100%;
}

.historique-visuel {
  white-space: pre-wrap;
}

.gg-share-buttons {
  display: flex;
  justify-content: center;
  gap: 8px;
  margin-top: 8px;
}

.share-btn {
  border: none;
  background: none;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 4px;
}

.popup-ad {
  margin-top: 4px;
}

.white{
  color: white;
}
</style>
