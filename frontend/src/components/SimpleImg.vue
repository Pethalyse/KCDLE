<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
  img: string | null
  alt?: string
}>()

const src = computed(() => {
  if (!props.img) return ''
  if (props.img.startsWith('http://') || props.img.startsWith('https://')) {
    return props.img
  }
  return `/images/${props.img}`
})
</script>

<template>
  <img
    :src="src"
    :alt="alt ?? ''"
    @click="$emit('onclick', $event)"
  />
</template>
