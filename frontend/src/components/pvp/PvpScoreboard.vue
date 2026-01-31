<script setup lang="ts">
import { computed } from 'vue'
import { useAuthStore } from '@/stores/auth'
import UserBadge from '@/components/UserBadge.vue'

const props = defineProps<{
  game: string
  bestOf: number
  currentRound: number
  players: Array<{
    user_id: number
    name?: string | null
    points: number
    avatar_url?: string | null
    avatar_frame_color?: string | null
    is_admin?: boolean
    is_streamer?: boolean
  }>
  youUserId?: number
  showLeave?: boolean
}>()

const emit = defineEmits<{
  (e: 'leave'): void
}>()

const auth = useAuthStore()
const myId = computed(() => Number(props.youUserId ?? auth.user?.id ?? 0))

const you = computed(() => (props.players || []).find(p => Number(p.user_id) === Number(myId.value)) ?? null)
const opp = computed(() => (props.players || []).find(p => Number(p.user_id) !== Number(myId.value)) ?? null)

const youName = computed(() => you.value?.name ?? 'Toi')
const oppName = computed(() => opp.value?.name ?? 'Adversaire')

const youAvatar = computed(() => you.value?.avatar_url ?? null)
const oppAvatar = computed(() => opp.value?.avatar_url ?? null)

const youFrame = computed(() => you.value?.avatar_frame_color ?? null)
const oppFrame = computed(() => opp.value?.avatar_frame_color ?? null)

const youAdmin = computed(() => Boolean(you.value?.is_admin))
const oppAdmin = computed(() => Boolean(opp.value?.is_admin))

const youStreamer = computed(() => Boolean(you.value?.is_streamer))
const oppStreamer = computed(() => Boolean(opp.value?.is_streamer))

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
      <div class="side left" :title="youName">
        <UserBadge
          :name="youName"
          :avatar-url="youAvatar"
          :frame-color="youFrame"
          :size="32"
          :show-name="true"
          :reverse="false"
          :admin="youAdmin"
          :streamer="youStreamer"
        />
      </div>
      <div class="points">{{ youPts }}</div>
      <div class="dash">-</div>
      <div class="points">{{ oppPts }}</div>
      <div class="side right" :title="oppName">
        <UserBadge
          :name="oppName"
          :avatar-url="oppAvatar"
          :frame-color="oppFrame"
          :size="32"
          :show-name="true"
          :reverse="true"
          :admin="oppAdmin"
          :streamer="oppStreamer"
        />
      </div>
    </div>

    <div v-if="props.showLeave" class="row">
      <button type="button" class="btn danger" @click="emit('leave')">Abandonner</button>
    </div>
  </div>
</template>

<style scoped>
.scoreboard {
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
  justify-content: center;
  gap: 10px;
  flex-wrap: nowrap;
  align-items: center;
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
