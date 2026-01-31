<script setup lang="ts">
import { computed } from 'vue'

const props = withDefaults(
  defineProps<{
    name: string
    avatarUrl?: string | null
    frameColor?: string | null
    size?: number
    showName?: boolean
    reverse?: boolean
    admin?: boolean
    streamer?: boolean
  }>(),
  {
    avatarUrl: null,
    frameColor: null,
    size: 34,
    showName: true,
    reverse: false,
    admin: false,
    streamer: false,
  },
)

const initials = computed(() => {
  const parts = props.name.trim().split(/\s+/)
  const first = parts[0]?.[0] ?? '?'
  const last = parts.length > 1 ? parts[parts.length - 1]?.[0] ?? '' : ''
  return (first + last).toUpperCase()
})

const frameStyle = computed(() => {
  const color = props.frameColor || '#3B82F6'
  return {
    borderColor: color,
  }
})

const showAvatar = computed(() => Boolean(props.avatarUrl))

const isStreamerEffective = computed(() => Boolean(props.streamer) && !Boolean(props.admin))
</script>

<template>
  <div class="user-badge" :class="{ reverse: props.reverse }">
    <div class="avatar" :style="[frameStyle, { width: `${props.size}px`, height: `${props.size}px` }]">
      <img v-if="showAvatar" :src="props.avatarUrl ?? undefined" alt="" />
      <div v-else class="initials">{{ initials }}</div>
    </div>

    <div v-if="props.showName" class="name-wrap">
      <span
        class="name"
        :class="{ 'is-admin': props.admin, 'is-streamer': isStreamerEffective }"
        :title="props.name"
      >
        <span class="name-text">{{ props.name }}</span>

        <span v-if="isStreamerEffective" class="role-icon" aria-hidden="true">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-twitch" viewBox="0 0 16 16">
            <path d="M3.857 0 1 2.857v10.286h3.429V16l2.857-2.857H9.57L14.714 8V0zm9.714 7.429-2.285 2.285H9l-2 2v-2H4.429V1.143h9.142z"/>
            <path d="M11.857 3.143h-1.143V6.57h1.143zm-3.143 0H7.571V6.57h1.143z"/>
          </svg>
        </span>
      </span>
    </div>
  </div>
</template>

<style scoped>
.user-badge {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  min-width: 0;
}

.user-badge.reverse {
  flex-direction: row-reverse;
}

.avatar {
  border: 2px solid #3B82F6;
  border-radius: 9999px;
  overflow: hidden;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(255, 255, 255, 0.05);
  flex: 0 0 auto;
}

.avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.initials {
  font-weight: 700;
  font-size: 0.9rem;
  opacity: 0.9;
}

.name-wrap {
  min-width: 0;
  display: flex;
  align-items: center;
}

.name {
  min-width: 0;
  max-width: 100%;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-weight: 600;
  color: #f3f3f3;
}

.name-text {
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.role-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex: 0 0 auto;
}

.name.is-streamer {
  color: #9146FF;
  font-weight: 800;
}

.name.is-admin {
  color: #f6c343;
  font-weight: 800;
}
</style>
