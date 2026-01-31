<script setup lang="ts">
import { computed } from 'vue'
import UserBadge from '@/components/UserBadge.vue'

const props = defineProps<{
  players: Array<{
    user_id: number
    name?: string | null
    points: number
    avatar_url?: string | null
    avatar_frame_color?: string | null
    is_admin?: boolean
    is_streamer?: boolean
  }>
  youUserId: number
}>()

const you = computed(() => props.players.find(p => p.user_id === props.youUserId) ?? null)
const opp = computed(() => props.players.find(p => p.user_id !== props.youUserId) ?? null)

const youName = computed(() => you.value?.name ?? 'Vous')
const oppName = computed(() => opp.value?.name ?? 'Adversaire')

const youAvatarUrl = computed(() => you.value?.avatar_url ?? null)
const oppAvatarUrl = computed(() => opp.value?.avatar_url ?? null)

const youFrameColor = computed(() => you.value?.avatar_frame_color ?? null)
const oppFrameColor = computed(() => opp.value?.avatar_frame_color ?? null)

const youPoints = computed(() => you.value?.points ?? 0)
const oppPoints = computed(() => opp.value?.points ?? 0)

const youAdmin = computed(() => Boolean(you.value?.is_admin))
const oppAdmin = computed(() => Boolean(opp.value?.is_admin))

const youStreamer = computed(() => Boolean(you.value?.is_streamer))
const oppStreamer = computed(() => Boolean(opp.value?.is_streamer))
</script>

<template>
  <div class="pvp-scoreboard">
    <div class="player-card">
      <UserBadge
        :name="youName"
        :avatar-url="youAvatarUrl"
        :frame-color="youFrameColor"
        :size="40"
        :admin="youAdmin"
        :streamer="youStreamer"
      />
      <div class="points">{{ youPoints }}</div>
    </div>

    <div class="vs">VS</div>

    <div class="player-card reverse">
      <UserBadge
        :name="oppName"
        :avatar-url="oppAvatarUrl"
        :frame-color="oppFrameColor"
        :size="40"
        :reverse="true"
        :admin="oppAdmin"
        :streamer="oppStreamer"
      />
      <div class="points">{{ oppPoints }}</div>
    </div>
  </div>
</template>

<style scoped>
.pvp-scoreboard {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 14px;
  padding: 10px 12px;
  border-radius: 14px;
  background: rgba(10, 12, 22, 0.75);
  border: 1px solid rgba(255, 255, 255, 0.06);
}

.player-card {
  display: flex;
  align-items: center;
  gap: 10px;
  min-width: 0;
}

.player-card.reverse {
  flex-direction: row-reverse;
}

.points {
  font-weight: 900;
  font-size: 1.2rem;
  padding: 4px 10px;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.06);
  border: 1px solid rgba(255, 255, 255, 0.08);
}

.vs {
  font-weight: 900;
  letter-spacing: 0.08em;
  opacity: 0.75;
}
</style>
