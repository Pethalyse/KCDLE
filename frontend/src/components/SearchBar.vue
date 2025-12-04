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

  joueursFiltres.value = (props.joueurs ?? [])
    .filter((j: any) => {
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
  <div>
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
    <div id="search">
      <PlayerCard
        v-for="joueur in joueursFiltres"
        :key="joueur.id ?? joueur.player?.slug"
        :joueur="joueur"
        @click_card="handleClickCard"
      />
    </div>
  </div>
</template>
