<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { usePvpStore } from '@/stores/pvp'
import { useAuthStore } from '@/stores/auth'
import { useFlashStore } from '@/stores/flash'
import { pvpGetMatch } from '@/api/pvpApi'
import PvpRoundTransition from '@/components/pvp/PvpRoundTransition.vue'
import PvpScoreboard from '@/components/pvp/PvpScoreboard.vue'

const route = useRoute()
const router = useRouter()
const pvp = usePvpStore()
const auth = useAuthStore()
const flash = useFlashStore()

const youUserId = computed<number>(() => Number(auth.user?.id ?? 0))

const matchId = computed(() => {
  const raw = route.params.matchId
  const n = typeof raw === 'string' ? Number(raw) : Array.isArray(raw) ? Number(raw[0]) : Number(raw)
  return Number.isFinite(n) ? n : null
})

const loading = ref(true)
const error = ref<string | null>(null)

const match = ref<any | null>(null)
const pool = ref<string[]>([])
const revealed = ref<string>('')

const showTransition = ref(false)
const roundNumber = ref<number>(1)

const transitionKey = computed(() => {
  if (!matchId.value) return 'pvp-transition-null'
  return `pvp-transition-${matchId.value}-r${roundNumber.value}`
})

function getRoundType(m: any): string {
  const stateRoundType = m?.state?.round_type
  if (typeof stateRoundType === 'string' && stateRoundType.length > 0) return stateRoundType

  const idx = Number(m?.current_round ?? 1) - 1
  const rounds = Array.isArray(m?.rounds) ? m.rounds : []
  const byList = rounds[idx]
  if (typeof byList === 'string' && byList.length > 0) return byList

  return 'classic'
}

function seenKey(mid: number, rn: number) {
  return `pvp_transition_seen_${mid}_${rn}`
}

async function load() {
  if (!matchId.value) return
  loading.value = true
  error.value = null

  try {
    const m = await pvpGetMatch(matchId.value)
    match.value = m
    pvp.setMatch(matchId.value)

    if (m?.status === 'finished') {
      showTransition.value = false
      await router.replace({name: 'pvp_match_end', params: {matchId: matchId.value}})
      return
    }

    roundNumber.value = Number(m?.current_round ?? 1)
    const rounds = Array.isArray(m?.rounds) ? m.rounds : [];

    pool.value = rounds.length > 0 ? rounds.map((x: any) => x.name) : ['classic', 'whois', 'locked_infos', 'draft', 'reveal_race']
    revealed.value = rounds.filter((x: any)  => x.type === getRoundType(m))[0].name;
    const key = seenKey(matchId.value, roundNumber.value)
    if (sessionStorage.getItem(key) === '1') {
      showTransition.value = false
      await router.replace({name: 'pvp_match_play', params: {matchId: matchId.value}})
      return
    }

    showTransition.value = true
  } catch {
    error.value = 'Impossible de charger le match.'
  } finally {
    loading.value = false
  }
}

function done() {
  if (!matchId.value) return
  const key = seenKey(matchId.value, roundNumber.value)
  sessionStorage.setItem(key, '1')
  showTransition.value = false
  router.replace({ name: 'pvp_match_play', params: { matchId: matchId.value } })
}

onMounted(async () => {
  if (!matchId.value) {
    flash.error('Match introuvable.', 'PvP')
    await router.push({name: 'pvp'})
    return
  }
  await load()
})
</script>

<template>
  <div class="page">
    <div v-if="loading" class="state">Chargement…</div>
    <div v-else-if="error" class="state state--error">{{ error }}</div>

    <template v-else>
      <div v-if="match" class="wrap">
        <PvpScoreboard
          :game="match.game"
          :best-of="match.best_of"
          :current-round="match.current_round"
          :players="match.players || []"
          :you-user-id="youUserId"
        />
      </div>

      <PvpRoundTransition
        v-if="showTransition"
        :key="transitionKey"
        :pool="pool"
        :revealed="revealed"
        @done="done"
      />
    </template>
  </div>
</template>

<style scoped>
.page {
  min-height: 100vh;
  padding: 20px 12px 28px;
  display: flex;
  flex-direction: column;
  align-items: center;
  color: #f3f3f3;
  background: radial-gradient(circle at top, #20263a 0, #05060a 75%);
}

.wrap {
  width: 100%;
  max-width: 900px;
  margin-bottom: 14px;
}

.state {
  width: 100%;
  max-width: 900px;
  text-align: center;
  opacity: 0.85;
  padding: 18px 10px;
  background: rgba(6, 8, 18, 0.92);
  border-radius: 14px;
  border: 1px solid rgba(255, 255, 255, 0.06);
  margin-top: 22px;
}

.state--error {
  color: #ffb4b4;
}
</style>
