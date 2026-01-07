<script setup lang="ts">
import PvpGuessWithHints from '@/components/pvp/PvpGuessWithHints.vue'

type GameCode = 'kcdle' | 'lecdle' | 'lfldle'

const props = defineProps<{
  matchId: number
  game: GameCode
  players: Array<{ user_id: number; name?: string | null; points: number }>
  round: any
}>()

const emit = defineEmits<{
  (e: 'guess', playerId: number): void
}>()
</script>

<template>
  <PvpGuessWithHints :matchId="matchId" :game="props.game" :players="props.players" :round="props.round" @guess="(id) => emit('guess', id)" />
</template>

<style scoped>
</style>
