<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import PlayerCard from '@/components/PlayerCard.vue'

const props = defineProps<{
  dle: string
  joueurs: any[]
  unwrittable: boolean
  guessedIds?: number[]
}>()

const emit = defineEmits<{
  (e: 'click_card', joueur: any): void
}>()

const joueursFiltres = ref<any[]>([])
const inputValue = ref('')

const backgroundStyle = computed(() => {
  return {
    backgroundImage: `url('/images/${props.dle}_texte.png')`,
    backgroundRepeat: 'no-repeat',
    backgroundPosition: 'center',
    backgroundSize: 'contain',
  }
})

const placeholder = computed(() => {
  switch (props.dle.toUpperCase()) {
    case 'KCDLE':
      return "Entrez le nom d'un membre qui est/a été à la KarmineCorp"
    case 'LECDLE':
      return "Entrez le nom d'un joueur de LEC"
    case 'LFLDLE':
      return "Entrez le nom d'un joueur de LFL"
    default:
      return "Entrez le nom d'un joueur"
  }
})

function handleInputBar() {
  const value = inputValue.value.trim().toLowerCase()
  if (!value) {
    joueursFiltres.value = []
    return
  }

  const guessed = props.guessedIds ?? []

  joueursFiltres.value = (props.joueurs ?? []).filter((j: any) => {
    if (j?.id && guessed.includes(j.id)) return false
    const p = j?.player
    const name: string = p?.display_name ?? p?.slug ?? ''
    return name.toLowerCase().startsWith(value)
  })
}

watch(inputValue, handleInputBar)

function handleClickCard(joueur: any | null) {
  if (!joueur) return
  emit('click_card', joueur)
  inputValue.value = ''
  joueursFiltres.value = []
}

function handleEnter() {
  if (joueursFiltres.value.length > 0) {
    handleClickCard(joueursFiltres.value[0])
  }
}
</script>

<template>
  <div class="search-wrapper">
    <div
      class="search-bar"
      :style="backgroundStyle"
    >
      <input
        :disabled="unwrittable"
        class="sub"
        type="text"
        :placeholder="placeholder"
        autofocus
        v-model="inputValue"
        @input="handleInputBar"
        @keyup.enter="handleEnter"
      >
    </div>

    <div id="search" class="search-results">
      <PlayerCard
        v-for="joueur in joueursFiltres"
        :key="joueur.id ?? joueur.player?.slug"
        :joueur="joueur"
        @click_card="handleClickCard"
      />
    </div>
  </div>
</template>

<style scoped>
.search-wrapper {
  position: relative;
}

.search-results {
  margin-top: 5px;
  position: absolute;
  width: 30vw;
  z-index: 1;

  max-height: min(55vh, 420px);
  overflow-y: auto;
  overflow-x: visible;

  background: transparent;

  box-sizing: border-box;
  padding: 12px 10px;

  overscroll-behavior: contain;

  -ms-overflow-style: none;
  scrollbar-width: none;
}

.search-results::-webkit-scrollbar {
  width: 0;
  height: 0;
}

.search-results :deep(.containt.player-card:nth-child(2n)),
.search-results :deep(.containt.player-card:nth-child(2n+1)) {
  background: transparent radial-gradient(
    circle at left,
    rgba(15, 23, 42, 0.96),
    rgba(15, 23, 42, 0.86)
  ) !important;
}

.search-results :deep(.containt.player-card) {
  margin-bottom: 6px;
  transform-origin: center;
}

@media screen and (max-width: 1280px) {
  .search-results {
    width: 90vw;
  }
}
</style>
