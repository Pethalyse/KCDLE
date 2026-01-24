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
  }>(),
  {
    avatarUrl: null,
    frameColor: null,
    size: 34,
    showName: true,
    reverse: false,
    admin: false,
  },
)

const safeFrameColor = computed(() => {
  const c = (props.frameColor ?? '').trim()
  if (c.length === 0) return '#00a6ff'
  return c
})

const avatarSrc = computed(() => {
  const u = (props.avatarUrl ?? '').trim()
  if (!u) return ''
  if (u.startsWith('http://') || u.startsWith('https://') || u.startsWith('/')) return u
  return u
})

const avatarStyle = computed(() => {
  const px = Math.max(16, Number(props.size ?? 34))
  return {
    width: `${px}px`,
    height: `${px}px`,
    borderColor: safeFrameColor.value,
  }
})

const rootClass = computed(() => {
  return {
    'user-badge': true,
    'is-reverse': Boolean(props.reverse),
  }
})
</script>

<template>
  <div :class="rootClass">
    <div class="avatar" :style="avatarStyle" aria-hidden="true">
      <img v-if="avatarSrc" class="avatar-img" :src="avatarSrc" :alt="name" />
      <div v-else class="avatar-fallback">{{ name.slice(0, 1).toUpperCase() }}</div>
    </div>

    <span
      v-if="showName"
      class="name"
      :class="{ 'is-admin': admin }"
      :title="name"
    >
      {{ name }}
    </span>
  </div>
</template>

<style scoped>
.user-badge {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  min-width: 0;
}

.user-badge.is-reverse {
  flex-direction: row-reverse;
}

.avatar {
  border: 2px solid;
  border-radius: 999px;
  overflow: hidden;
  display: grid;
  place-items: center;
  background: rgba(255, 255, 255, 0.08);
  flex: 0 0 auto;
}

.avatar-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.avatar-fallback {
  width: 100%;
  height: 100%;
  display: grid;
  place-items: center;
  font-weight: 900;
  font-size: 0.95rem;
  opacity: 0.95;
}

.name {
  max-width: 100%;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.name.is-admin {
  color: #FFD85A;
  font-weight: 800;
}
</style>
