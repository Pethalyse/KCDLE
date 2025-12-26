<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import api from '@/api'
import SearchBar from '@/components/SearchBar.vue'
import SimpleImg from '@/components/SimpleImg.vue'
import { useAuthStore } from '@/stores/auth'
import { useFlashStore } from '@/stores/flash'

type GameCode = 'kcdle' | 'lecdle' | 'lfldle'

const props = defineProps<{
  matchId: number
  game: GameCode
  players: Array<{ user_id: number; name?: string | null; points: number }>
  round: any
}>()

const emit = defineEmits<{
  (e: 'guess', playerId: number): void
}>()

const auth = useAuthStore()
const flash = useFlashStore()

const dleCode = computed(() => String(props.game || 'kcdle').toUpperCase())
const myUserId = computed(() => auth.user?.id ?? 0)

const backendYouSolved = computed(() => !!props.round?.you?.solved_at)
const opponentSolved = computed(() => !!props.round?.opponent?.solved_at)

const uiYouSolved = ref(false)
const inputLocked = ref(false)

const joueurs = ref<any[]>([])
const pendingGuessedIds = ref<Set<number>>(new Set())

const playersById = computed(() => {
  const map = new Map<number, any>()
  for (const j of joueurs.value) {
    const id = Number(j?.id)
    if (Number.isFinite(id) && id > 0) map.set(id, j)
  }
  return map
})

const opponentName = computed(() => {
  const opp = (props.players ?? []).find(p => Number(p?.user_id) !== myUserId.value)
  return opp?.name ?? 'Adversaire'
})

const yourName = computed(() => {
  const me = (props.players ?? []).find(p => Number(p?.user_id) === myUserId.value)
  return me?.name ?? 'Toi'
})

let winTimer: number | null = null

function scheduleUiWin() {
  if (uiYouSolved.value) return
  if (winTimer !== null) window.clearTimeout(winTimer)

  winTimer = window.setTimeout(() => {
    uiYouSolved.value = true
    winTimer = null
    if (!opponentSolved.value) {
      flash.info('En attente de l’adversaire…', 'PvP', 3500)
    }
  }, 800)
}

async function loadPlayers() {
  const { data } = await api.get(`/games/${props.game}/players`, { params: { active: 1 } })
  joueurs.value = data.players ?? []
}

const guessedIds = computed<number[]>(() => {
  const list = Array.isArray(props.round?.you?.guesses) ? props.round.you.guesses : []
  return list
    .map((g: any) => Number(g?.player_id ?? 0))
    .filter((id: number) => Number.isFinite(id) && id > 0)
})

const guessedIdsEffective = computed<number[]>(() => {
  const base = guessedIds.value
  const pend = Array.from(pendingGuessedIds.value)
  const merged = new Set<number>([...base, ...pend])
  return Array.from(merged)
})

watch(
  () => backendYouSolved.value,
  (now, prev) => {
    if (now && !prev) {
      inputLocked.value = true
      scheduleUiWin()
    }
    if (!now) {
      inputLocked.value = false
      uiYouSolved.value = false
      if (winTimer !== null) window.clearTimeout(winTimer)
      winTimer = null
    }
  },
  { immediate: true },
)

function handleClickCard(joueurWrapper: any) {
  const id = Number(joueurWrapper?.id ?? 0)
  if (!id) return
  if (inputLocked.value) return
  if (guessedIdsEffective.value.includes(id)) return
  pendingGuessedIds.value.add(id)
  emit('guess', id)
}

watch(
  () => guessedIds.value.slice().sort((a, b) => a - b).join(','),
  () => {
    for (const id of Array.from(pendingGuessedIds.value)) {
      if (guessedIds.value.includes(id)) pendingGuessedIds.value.delete(id)
    }
  },
  { immediate: true },
)

const countries = ref<any[]>([])
const teams = ref<any[]>([])
const games = ref<any[]>([])
const roles = ref<any[]>([])
const lolRoles = ref<any[]>([])

const countriesByCode = computed(() => {
  const m = new Map<string, any>()
  for (const c of countries.value) {
    const code = String(c?.code ?? '').toUpperCase()
    if (code) m.set(code, c)
  }
  return m
})

const teamsById = computed(() => {
  const m = new Map<number, any>()
  for (const t of teams.value) {
    const id = Number(t?.id)
    if (Number.isFinite(id) && id > 0) m.set(id, t)
  }
  return m
})

const teamsBySlug = computed(() => {
  const m = new Map<string, any>()
  for (const t of teams.value) {
    const slug = String(t?.slug ?? '').toLowerCase()
    if (slug) m.set(slug, t)
  }
  return m
})

const gamesById = computed(() => {
  const m = new Map<number, any>()
  for (const g of games.value) {
    const id = Number(g?.id)
    if (Number.isFinite(id) && id > 0) m.set(id, g)
  }
  return m
})

const rolesById = computed(() => {
  const m = new Map<number, any>()
  for (const r of roles.value) {
    const id = Number(r?.id)
    if (Number.isFinite(id) && id > 0) m.set(id, r)
  }
  return m
})

const lolRolesByCode = computed(() => {
  const m = new Map<string, any>()
  for (const r of lolRoles.value) {
    const code = String(r?.code ?? '').toUpperCase()
    if (code) m.set(code, r)
  }
  return m
})

async function loadRefs() {
  const [c, t, g, r, lr] = await Promise.all([
    api.get('/countries'),
    api.get('/teams'),
    api.get('/games'),
    api.get('/roles'),
    api.get('/lol-roles'),
  ])
  countries.value = c.data.countries ?? []
  teams.value = t.data.teams ?? []
  games.value = g.data.games ?? []
  roles.value = r.data.roles ?? []
  lolRoles.value = lr.data.lol_roles ?? []
}

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

function resolveEnumLabel(key: string, value: any): string {
  if (key === 'country_code') {
    const raw = String(value ?? '').toUpperCase().trim()
    const code = raw ? raw : 'NN'
    const c = countriesByCode.value.get(code)
    return c ? String(c?.name ?? code) : code
  }

  if (key === 'current_team_id' || key === 'previous_team_id') {
    const id = Number(value ?? 0)
    if (!Number.isFinite(id) || id <= 0) {
      const none = teamsBySlug.value.get('none')
      if (none) return String(none?.display_name ?? none?.short_name ?? none?.slug ?? 'none')
      return 'none'
    }
    const t = teamsById.value.get(id)
    return t ? String(t?.display_name ?? t?.short_name ?? t?.slug ?? id) : String(id)
  }

  if (key === 'game_id') {
    const id = Number(value ?? 0)
    const g = gamesById.value.get(id)
    return g ? String(g?.name ?? g?.code ?? id) : String(id)
  }

  if (key === 'role_id') {
    const id = Number(value ?? 0)
    const r = rolesById.value.get(id)
    return r ? String(r?.label ?? r?.code ?? id) : String(id)
  }

  if (key === 'lol_role') {
    const code = String(value ?? '').toUpperCase()
    const r = lolRolesByCode.value.get(code)
    return r ? String(r?.label ?? code) : code
  }

  return value === null || value === undefined ? '—' : String(value)
}

function resolveEnumImage(key: string, value: any): string | null {
  if (key === 'country_code') {
    const raw = String(value ?? '').toUpperCase().trim()
    const code = raw ? raw : 'NN'
    const c = countriesByCode.value.get(code)
    return c?.flag_url ?? null
  }

  if (key === 'current_team_id' || key === 'previous_team_id') {
    const id = Number(value ?? 0)
    if (!Number.isFinite(id) || id <= 0) {
      const none = teamsBySlug.value.get('none')
      return none?.logo_url ?? null
    }
    const t = teamsById.value.get(id)
    return t?.logo_url ?? null
  }

  if (key === 'game_id') {
    const id = Number(value ?? 0)
    const g = gamesById.value.get(id)
    return g?.logo_url ?? null
  }

  if (key === 'lol_role') {
    const code = String(value ?? '').toUpperCase()
    const r = lolRolesByCode.value.get(code)
    return r?.icon_url ?? null
  }

  return null
}

const revealedEntries = computed(() => {
  const raw = props.round?.revealed ?? null
  if (!raw || typeof raw !== 'object') return []
  return Object.keys(raw).map((k) => {
    const v = (raw as any)[k]
    return {
      key: k,
      label: keyLabel(k),
      value: resolveEnumLabel(k, v),
      img: resolveEnumImage(k, v),
    }
  })
})

const guessHistory = computed(() => {
  const list = Array.isArray(props.round?.you?.guesses) ? props.round.you.guesses : []
  const ordered = [...list].reverse()
  return ordered
    .map((g: any) => {
      const pid = Number(g?.player_id ?? 0)
      const ok = !!g?.correct
      const wrapper = playersById.value.get(pid)
      const name = wrapper?.player?.name ?? wrapper?.player?.display_name ?? wrapper?.player?.slug ?? String(pid)
      const img = wrapper?.player?.image_url ?? null
      return { pid, ok, name, img }
    })
    .filter((x: any) => Number(x?.pid ?? 0) > 0)
})

onMounted(async () => {
  try {
    await Promise.all([loadRefs(), loadPlayers()])
  } catch {
    flash.error('Impossible de charger les données.', 'PvP')
  }

  if (backendYouSolved.value) {
    inputLocked.value = true
    uiYouSolved.value = true
  }
})
</script>

<template>
  <div class="locked-root" :class="dleCode">
    <div class="locked">
      <header class="locked-header">
        <div class="header-top">
          <div class="btn-home">
            <SimpleImg class="logo" :alt="dleCode" :img="dleCode + '_page_Logo.png'" />

            <div class="pvp-indicators">
              <div class="pvp-indicator" :class="uiYouSolved ? 'ok' : 'ko'">
                <span class="dot" />
                <span class="label">{{ yourName }}</span>
              </div>
              <div class="pvp-indicator" :class="opponentSolved ? 'ok' : 'ko'">
                <span class="dot" />
                <span class="label">{{ opponentName }}</span>
              </div>
            </div>
          </div>
        </div>

        <div v-if="revealedEntries.length" class="revealed">
          <div class="revealed-title">Informations révélées</div>
          <div class="revealed-grid">
            <div v-for="it in revealedEntries" :key="it.key" class="reveal-chip">
              <SimpleImg v-if="it.img" class="chip-img" :alt="it.value" :img="it.img" />
              <div class="chip-text">
                <div class="chip-label">{{ it.label }}</div>
                <div class="chip-value">{{ it.value }}</div>
              </div>
            </div>
          </div>
        </div>

        <div class="search-wrap">
          <SearchBar
            v-if="!uiYouSolved"
            class="containt-name"
            :dle="dleCode"
            :joueurs="joueurs"
            :unwrittable="inputLocked"
            :guessed-ids="guessedIdsEffective"
            @click_card="handleClickCard"
          />
        </div>
      </header>

      <section class="history">
        <div class="history-title">Historique</div>

        <div v-if="guessHistory.length === 0" class="history-empty">Aucun guess pour l’instant.</div>

        <div v-else class="history-grid">
          <div
            v-for="g in guessHistory"
            :key="g.pid"
            class="history-item"
            :class="g.ok ? 'ok' : 'ko'"
            :title="g.name"
          >
            <SimpleImg v-if="g.img" class="pimg" :alt="g.name" :img="g.img" />
            <div v-else class="pimg pimg--empty" />
            <div class="pname">{{ g.name }}</div>
          </div>
        </div>
      </section>
    </div>
  </div>
</template>

<style scoped>
.locked-root {
  min-height: 100vh;
  width: 100%;
  background: radial-gradient(circle at top, #20263a 0, #05060a 75%);
  padding: 0 0 18px;
}

.locked {
  width: 100%;
  margin: 0 auto;
}

.header-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  flex-wrap: wrap;
}

.pvp-indicators {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
  align-items: center;
  justify-content: center;
  margin-top: 6px;
}

.pvp-indicator {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 6px 10px;
  border-radius: 999px;
  border: 1px solid rgba(255, 255, 255, 0.12);
  background: rgba(255, 255, 255, 0.06);
  font-size: 0.9rem;
}

.pvp-indicator .dot {
  width: 9px;
  height: 9px;
  border-radius: 999px;
  display: inline-block;
  background: rgba(255, 66, 66, 0.95);
}

.pvp-indicator.ok .dot {
  background: rgba(80, 220, 140, 0.95);
}

.revealed {
  margin-top: 10px;
  padding: 12px;
  border-radius: 14px;
  border: 1px solid rgba(255, 255, 255, 0.08);
  background: rgba(0, 0, 0, 0.22);
}

.revealed-title {
  font-weight: 800;
  letter-spacing: 0.03em;
  text-transform: uppercase;
  font-size: 0.9rem;
  opacity: 0.92;
  margin-bottom: 10px;
}

.revealed-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 10px;
}

.reveal-chip {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  border-radius: 14px;
  padding: 10px;
  border: 1px solid rgba(255, 255, 255, 0.08);
  background: rgba(255, 255, 255, 0.06);
  min-width: 0;
}

.chip-img {
  width: 32px;
  height: 32px;
  border-radius: 10px;
  object-fit: cover;
  flex: 0 0 auto;
}

.chip-text {
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.chip-label {
  font-size: 0.8rem;
  opacity: 0.78;
}

.chip-value {
  font-weight: 700;
  font-size: 0.95rem;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.search-wrap {
  position: relative;
  z-index: 10;
}

.history {
  margin-top: 50px;
  padding: 12px;
  border-radius: 14px;
  position: relative;
  z-index: 1;
}

.history-title {
  font-weight: 800;
  margin-bottom: 10px;
}

.history-empty {
  opacity: 0.75;
}

.history-grid {
  display: grid;
  grid-template-columns: repeat(10, minmax(0, 1fr));
  gap: 8px;
}

.history-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 7px 9px;
  border-radius: 12px;
  border: 1px solid rgba(255, 255, 255, 0.07);
  background: rgba(255, 255, 255, 0.035);
  min-width: 0;
}

.history-item.ok {
  border-color: rgba(80, 220, 140, 0.18);
  background: rgba(80, 220, 140, 0.055);
}

.history-item.ko {
  border-color: rgba(255, 66, 66, 0.14);
  background: rgba(255, 66, 66, 0.04);
}

.pimg {
  width: 50%;
  border-radius: 10px;
  object-fit: cover;
  flex: 0 0 auto;
}

.pimg--empty {
  background: rgba(255, 255, 255, 0.08);
}

.pname {
  font-weight: 700;
  font-size: 0.9rem;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  min-width: 0;
}

@media (max-width: 520px) {
  .revealed-grid {
    grid-template-columns: 1fr;
  }
  .history-grid {
    grid-template-columns: 1fr;
  }
  .pimg {
    width: 25%;
  }
}
</style>
