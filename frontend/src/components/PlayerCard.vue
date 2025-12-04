<script setup lang="ts">
import SimpleImg from '@/components/SimpleImg.vue'

const props = defineProps<{
  joueur: any
}>()

const emit = defineEmits<{
  (e: 'click_card', joueur: any): void
}>()

function handleClick() {
  emit('click_card', props.joueur)
}

function displayName(joueur: any): string {
  const p = joueur?.player
  return p?.display_name ?? p?.slug ?? ''
}

function imagePath(joueur: any): string {
  const p = joueur?.player
  if (p?.image_url) {
    return p.image_url
  }
  return 'default_player.png'
}
</script>

<template>
  <div
    class="containt"
    @click="handleClick"
  >
    <SimpleImg
      :alt="displayName(joueur)"
      :img="imagePath(joueur)"
    />
    <div>{{ displayName(joueur) }}</div>
  </div>
</template>
