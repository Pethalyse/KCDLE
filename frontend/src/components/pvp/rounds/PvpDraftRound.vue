<script setup lang="ts">
import { computed, ref } from 'vue'
import { useAuthStore } from '@/stores/auth'
import PvpChooseFirstPlayer from '@/components/pvp/PvpChooseFirstPlayer.vue'
import PvpGuessWithHints from '@/components/pvp/PvpGuessWithHints.vue'

type GameCode = 'kcdle' | 'lecdle' | 'lfldle'

const props = defineProps<{
  matchId: number
  game: GameCode
  players: Array<{ user_id: number; name?: string | null; points: number }>
  round: any
}>()

const emit = defineEmits<{
  (e: 'chooseOrder', firstPickerUserId: number): void
  (e: 'pickHint', key: string): void
  (e: 'guess', playerId: number): void
}>()

const auth = useAuthStore()
const myUserId = computed(() => auth.user?.id ?? 0)

const phase = computed(() => String(props.round?.phase ?? 'draft'))
const canChooseOrder = computed(() => !!props.round?.can_choose_order)

const turnUserId = computed(() => {
  const v = props.round?.turn_user_id
  if (typeof v === 'number') return v
  if (v === null || v === undefined) return null
  const n = Number(v)
  return Number.isFinite(n) ? n : null
})

const isMyTurn = computed(() => !!turnUserId.value && turnUserId.value === myUserId.value)

const allowedKeys = computed<string[]>(() => {
  const v = props.round?.allowed_keys
  if (!Array.isArray(v)) return []
  return v.map((x: any) => String(x)).filter((x: string) => x.length > 0)
})

const pickedKeys = computed<Set<string>>(() => {
  const set = new Set<string>()
  const v = props.round?.picked_keys
  if (Array.isArray(v)) {
    for (const k of v) {
      const s = String(k ?? '')
      if (s) set.add(s)
    }
  }
  return set
})

const pickedCount = computed(() => pickedKeys.value.size)

const submittingPick = ref(false)
const submittingChoose = ref(false)

function keyLabel(key: string): string {
  return ({
    country_code: 'Nationalité',
    role_id: 'Rôle',
    lol_role: 'LoL rôle',
    game_id: 'Jeu',
    current_team_id: 'Équipe actuelle',
    previous_team_id: 'Équipe précédente',
    trophies_count: 'Trophées',
    first_official_year: '1re année officielle chez KC',
    age: 'Âge',
  } as any)[key] ?? key
}

const turnLabel = computed(() => {
  if (canChooseOrder.value) return 'Choix du premier joueur…'
  if (!turnUserId.value) return 'Préparation du draft…'
  if (isMyTurn.value) return 'À toi de choisir un indice'
  return 'Au tour de l’adversaire'
})

function chooseOrder(firstPickerUserId: number) {
  if (!canChooseOrder.value || submittingChoose.value) return
  submittingChoose.value = true
  emit('chooseOrder', firstPickerUserId)
  window.setTimeout(() => (submittingChoose.value = false), 1200)
}

function pickHint(key: string) {
  if (phase.value !== 'draft') return
  if (!isMyTurn.value) return
  if (submittingPick.value) return
  if (pickedKeys.value.has(key)) return
  submittingPick.value = true
  emit('pickHint', key)
  window.setTimeout(() => (submittingPick.value = false), 900)
}

const visibleKeys = computed(() => {
  const picked = pickedKeys.value
  const all = allowedKeys.value
  return [...all.filter(k => !picked.has(k)), ...all.filter(k => picked.has(k))]
})
</script>

<template>
  <div class="draft-root" :class="String(props.game || 'kcdle').toUpperCase()">
    <PvpGuessWithHints
      v-if="phase === 'guess'"
      :match-id="props.matchId"
      :game="props.game"
      :players="props.players"
      :round="props.round"
      @guess="(id) => emit('guess', id)"
    />

    <div v-else class="draft">
      <header class="draft-header">
        <div class="draft-title">Draft</div>
        <div class="draft-sub">{{ turnLabel }}</div>
        <div class="draft-progress">{{ pickedCount }} / 4 indices</div>
      </header>

      <PvpChooseFirstPlayer
        v-if="canChooseOrder"
        :game="props.game"
        :players="props.players"
        :can-choose="canChooseOrder"
        :disabled="submittingChoose"
        @choose="chooseOrder"
      />

      <section v-else class="keys">
        <div class="keys-title">Choisir un indice</div>
        <div class="keys-sub">Sélection 1-2-1</div>

        <div class="keys-grid">
          <button
            v-for="k in visibleKeys"
            :key="k"
            class="key"
            :class="pickedKeys.has(k) ? 'picked' : (isMyTurn ? 'ready' : 'wait')"
            :disabled="pickedKeys.has(k) || !isMyTurn || submittingPick"
            @click="pickHint(k)"
          >
            <span class="kname">{{ keyLabel(k) }}</span>
            <span class="kmeta">
              {{ pickedKeys.has(k) ? 'Pris' : (isMyTurn ? 'Choisir' : 'Attente') }}
            </span>
          </button>
        </div>
      </section>
    </div>
  </div>
</template>

<style scoped>
.draft-root,
.draft-root * {
  box-sizing: border-box;
}

.draft-root {
  width: 100%;
  overflow-x: hidden;
}

.draft {
  width: 100%;
  max-width: 100%;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 12px;
  overflow-x: hidden;
}

.draft-header {
  width: 100%;
  max-width: 100%;
  background: rgba(6, 8, 18, 0.92);
  border-radius: 14px;
  border: 1px solid rgba(255, 255, 255, 0.08);
  padding: 14px 12px 12px;
  overflow: hidden;
}

.draft-title {
  font-size: 0.95rem;
  text-transform: uppercase;
  letter-spacing: 0.12em;
  opacity: 0.9;
}

.draft-sub {
  margin-top: 6px;
  font-size: 1.02rem;
  font-weight: 800;
  word-break: break-word;
}

.draft-progress {
  margin-top: 6px;
  opacity: 0.78;
  font-size: 0.95rem;
}

.keys {
  width: 100%;
  max-width: 100%;
  background: rgba(6, 8, 18, 0.92);
  border-radius: 14px;
  border: 1px solid rgba(255, 255, 255, 0.08);
  padding: 14px 12px 12px;
  overflow: hidden;
}

.keys-title {
  font-size: 0.9rem;
  text-transform: uppercase;
  letter-spacing: 0.12em;
  opacity: 0.9;
}

.keys-sub {
  margin-top: 6px;
  font-size: 0.95rem;
  opacity: 0.8;
}

.keys-grid {
  margin-top: 12px;
  display: grid;
  grid-template-columns: 1fr;
  gap: 10px;
  width: 100%;
  max-width: 100%;
}

.key {
  width: 100%;
  max-width: 100%;
  min-width: 0;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  padding: 12px 12px;
  border-radius: 12px;
  border: 1px solid rgba(255, 255, 255, 0.12);
  background: rgba(255, 255, 255, 0.06);
  color: #f3f3f3;
  cursor: pointer;
  overflow: hidden;
}

.key.ready {
  border-color: rgba(255, 255, 255, 0.18);
}

.key.picked {
  opacity: 0.55;
  cursor: not-allowed;
}

.key.wait {
  opacity: 0.75;
  cursor: not-allowed;
}

.key:disabled {
  cursor: not-allowed;
}

.kname {
  flex: 1 1 auto;
  min-width: 0;
  font-weight: 800;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.kmeta {
  flex: 0 0 auto;
  font-size: 0.9rem;
  opacity: 0.8;
}

@media (min-width: 720px) {
  .keys-grid {
    grid-template-columns: 1fr 1fr;
  }
}

@media (min-width: 980px) {
  .keys-grid {
    grid-template-columns: 1fr 1fr 1fr;
  }
}
</style>
