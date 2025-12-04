<script setup lang="ts">
import { useRouter } from 'vue-router'
import SimpleImg from '@/components/SimpleImg.vue'

interface GameData {
  dle: string
  url?: string
  active: boolean
}

const props = defineProps<{
  data: GameData
}>()

const router = useRouter()

function goGame() {
  const dle = props.data.dle.toUpperCase()
  switch (dle) {
    case 'KCDLE':
      router.push({ name: 'kcdle' })
      break
    case 'LECDLE':
      router.push({ name: 'lecdle' })
      break
    case 'LFLDLE':
      router.push({ name: 'lfldle' })
      break
    default:
      console.warn('Jeu inconnu:', dle)
  }
}
</script>

<template>
  <div
    class="btn-game"
    v-if="data.active"
    @click="goGame"
  >
    <SimpleImg :alt="data.dle" :img="data.dle + '_Barre.png'" />
  </div>
</template>
