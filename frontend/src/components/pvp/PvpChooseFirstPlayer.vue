<script setup lang="ts">
import { computed } from 'vue'

type GameCode = 'kcdle' | 'lecdle' | 'lfldle'
type Player = { user_id: number; name?: string | null }

const props = defineProps<{
  game: GameCode
  players: Player[]
  canChoose: boolean
  disabled?: boolean
}>()

const emit = defineEmits<{
  (e: 'choose', firstUserId: number): void
}>()

const left = computed<Player | null>(() => props.players?.[0] ?? null)
const right = computed<Player | null>(() => props.players?.[1] ?? null)

const leftId = computed<number | null>(() => left.value?.user_id ?? null)
const rightId = computed<number | null>(() => right.value?.user_id ?? null)

const leftName = computed(() => left.value?.name ?? 'Joueur 1')
const rightName = computed(() => right.value?.name ?? 'Joueur 2')

function choose(id: number | null) {
  if (id === null) return
  if (!props.canChoose || props.disabled) return
  emit('choose', id)
}
</script>

<template>
  <section class="choose-card" :class="String(props.game || 'kcdle').toUpperCase()">
    <div class="title">Choisir qui commence</div>

    <template v-if="canChoose">
      <div class="subtitle">Ce joueur fera le premier pick du draft.</div>

      <div class="choices">
        <button class="choice" :disabled="disabled || leftId === null" @click="choose(leftId)">
          <span class="name">{{ leftName }}</span>
          <span class="meta">Commence</span>
        </button>

        <button class="choice" :disabled="disabled || rightId === null" @click="choose(rightId)">
          <span class="name">{{ rightName }}</span>
          <span class="meta">Commence</span>
        </button>
      </div>
    </template>

    <template v-else>
      <div class="waiting">En attente du choix de l’adversaire…</div>
    </template>
  </section>
</template>

<style scoped>
.choose-card {
  //width: 100%;
  background: rgba(6, 8, 18, 0.92);
  border-radius: 14px;
  border: 1px solid rgba(255, 255, 255, 0.08);
  padding: 14px 12px 12px;
}

.title {
  font-size: 0.95rem;
  text-transform: uppercase;
  letter-spacing: 0.12em;
  opacity: 0.9;
}

.subtitle {
  margin-top: 6px;
  font-size: 0.95rem;
  opacity: 0.8;
}

.waiting {
  margin-top: 14px;
  text-align: center;
  opacity: 0.75;
  font-size: 1rem;
}

.choices {
  margin-top: 12px;
  display: grid;
  grid-template-columns: 1fr;
  gap: 10px;
}

.choice {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  width: 100%;
  padding: 12px;
  border-radius: 12px;
  border: 1px solid rgba(255, 255, 255, 0.12);
  background: rgba(255, 255, 255, 0.06);
  color: #f3f3f3;
  cursor: pointer;
}

.choice:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.name {
  font-weight: 800;
}

.meta {
  font-size: 0.9rem;
  opacity: 0.8;
}

@media (min-width: 720px) {
  .choices {
    grid-template-columns: 1fr 1fr;
  }
}
</style>
