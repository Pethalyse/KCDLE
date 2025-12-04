<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import SimpleImg from '@/components/SimpleImg.vue'

const props = defineProps<{
  dleCode: string
  guesses: any[]
}>()

const root = ref<HTMLElement | null>(null)

const headerText = computed(
  () => `J'ai joué au ${props.dleCode} sur http://kcdle.fr/ et voici mes résultats :\n`,
)

const hashtagText = '#KCORP'

const resultLines = computed(() => {
  console.log(props.guesses)
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
    console.error(e)
  }
}

onMounted(() => {
  if (root.value) {
    root.value.scrollIntoView({ behavior: 'smooth' })
    setTimeout(() => {
      root.value && root.value.classList.add('fade-in')
    }, 500)
  }
})
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
  </div>
</template>

<style>
.popup-gg {
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
</style>
