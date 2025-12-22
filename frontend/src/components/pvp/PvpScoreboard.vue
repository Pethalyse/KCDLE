<script setup lang="ts">
import { computed } from 'vue'
import { useAuthStore } from '@/stores/auth'

const props = defineProps<{
  game: string
  bestOf: number
  currentRound: number
  players: Array<{ user_id: number; name?: string | null; points: number }>
}>()

const emit = defineEmits<{
  (e: 'leave'): void
}>()

const auth = useAuthStore()
const myId = computed(() => auth.user?.id ?? 0)

const you = computed(() => (props.players || []).find(p => Number(p.user_id) === Number(myId.value)) ?? null)
const opp = computed(() => (props.players || []).find(p => Number(p.user_id) !== Number(myId.value)) ?? null)

const youName = computed(() => you.value?.name ?? 'Toi')
const oppName = computed(() => opp.value?.name ?? 'Adversaire')

const youPts = computed(() => Number(you.value?.points ?? 0))
const oppPts = computed(() => Number(opp.value?.points ?? 0))

const metaLine = computed(() => {
  const g = String(props.game || '').toUpperCase()
  const bo = Number(props.bestOf ?? 1)
  const r = Number(props.currentRound ?? 1)
  return `${g} • BO${bo} • Round ${r} / ${bo}`
})
</script>

<template>
  <div class="scoreboard">
    <div class="meta">{{ metaLine }}</div>

    <div class="row">
      <div class="name you" :title="youName">{{ youName }}</div>
      <div class="points">{{ youPts }}</div>
      <div class="dash">-</div>
      <div class="points">{{ oppPts }}</div>
      <div class="name opp" :title="oppName">{{ oppName }}</div>
    </div>

    <div class="row">
      <button type="button" class="btn danger" @click="emit('leave')">Abandonner</button>
    </div>
  </div>
</template>

<style scoped>
.scoreboard {
  width: 100%;
  border-radius: 14px;
  padding: 10px 12px;
  border: 1px solid rgba(255, 255, 255, 0.08);
  background: rgba(0, 0, 0, 0.18);
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.meta {
  font-size: 0.85rem;
  opacity: 0.85;
  text-align: center;
}

.row {
  display: flex;
  align-items: baseline;
  justify-content: center;
  gap: 10px;
  flex-wrap: nowrap;
}

.name {
  font-size: 0.95rem;
  opacity: 0.95;
  max-width: 34vw;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.points {
  font-size: 1.25rem;
  font-weight: 800;
  min-width: 20px;
  text-align: center;
}

.dash {
  opacity: 0.8;
  font-weight: 700;
}

.btn {
  padding: 9px 14px;
  border-radius: 10px;
  border: 1px solid rgba(255, 255, 255, 0.14);
  background: rgba(255, 255, 255, 0.08);
  color: #f3f3f3;
  cursor: pointer;
}

.btn.danger {
  background: rgba(255, 66, 66, 0.18);
  border-color: rgba(255, 66, 66, 0.35);
}

@media (max-width: 520px) {
  .name { max-width: 30vw; }
  .points { font-size: 1.15rem; }
}
</style>
