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
  if (!joueur) return ''
  if (joueur.name) return joueur.name
  if (joueur.player?.name) return joueur.player.name
  if (joueur.display_name) return joueur.display_name
  if (joueur.player?.display_name) return joueur.player.display_name
  return joueur.player?.slug ?? ''
}

function imagePath(joueur: any): string {
  if (!joueur) return ""
  if (joueur.image_url) return joueur.image_url
  if (joueur.player?.image_url) return joueur.player.image_url
  return ""
}
</script>

<template>
  <div
    class="containt player-card"
    @click="handleClick"
  >
    <SimpleImg
      class="player-card__avatar"
      :alt="displayName(joueur)"
      :img="imagePath(joueur)"
    />
    <div class="player-card__name">
      {{ displayName(joueur) }}
    </div>
  </div>
</template>


<style scoped>
.player-card {
  display: flex;
  align-items: center;
  gap: 10px;

  padding: 6px 10px;
  margin-bottom: 4px;

  border-radius: 999px;
  background: radial-gradient(
    circle at left,
    rgba(15, 23, 42, 0.96),
    rgba(15, 23, 42, 0.86)
  );
  border: 1px solid rgba(148, 163, 184, 0.5);
  //box-shadow: 0 10px 20px rgba(0, 0, 0, 0.45);

  cursor: pointer;
  transition:
    transform 0.12s ease-out,
    box-shadow 0.12s ease-out,
    border-color 0.12s ease-out,
    background 0.12s ease-out;
}

.player-card:hover {
  transform: translateY(-1px);
  border-color: var(#38bdf8, #38bdf8);
  //box-shadow: 0 14px 26px rgba(0, 0, 0, 0.7);
  background: linear-gradient(
    90deg,
    rgba(15, 23, 42, 0.98),
    rgba(15, 23, 42, 0.85)
  );
}

.player-card__avatar {
  flex: 0 0 auto;
  width: 42px;
  height: 42px;
  border-radius: 999px;
  overflow: hidden;
  box-shadow: 0 0 0 2px rgba(15, 23, 42, 0.95);
}

.player-card__avatar :deep(img) {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.player-card__name {
  font-size: 0.9rem;
  font-weight: 600;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
</style>

