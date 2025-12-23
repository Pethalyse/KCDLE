<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import api from '@/api'
import { useAuthStore } from '@/stores/auth'
import { useFlashStore } from '@/stores/flash'
import PlayerCard from '@/components/PlayerCard.vue'
import SimpleImg from '@/components/SimpleImg.vue'

type GameCode = 'kcdle' | 'lecdle' | 'lfldle'
type Panel = 'keys' | 'ops' | 'values' | null

const props = defineProps<{
  game: GameCode
  players: Array<{ user_id: number; name?: string | null; points: number }>
  round: any
  events: any[]
}>()

const emit = defineEmits<{
  (e: 'chooseTurn', firstUserId: number): void
  (e: 'guess', playerId: number): void
  (e: 'ask', question: { key: string; op: string; value: any }): void
}>()

const auth = useAuthStore()
const flash = useFlashStore()

const dleCode = computed(() => String(props.game || 'kcdle').toUpperCase())
const myUserId = computed(() => auth.user?.id ?? 0)

const canChooseTurn = computed(() => !!props.round?.can_choose_turn)
const turnUserId = computed(() => {
  const v = props.round?.turn_user_id
  if (typeof v === 'number') return v
  if (v === null || v === undefined) return null
  const n = Number(v)
  return Number.isFinite(n) ? n : null
})
const isMyTurn = computed(() => !!turnUserId.value && turnUserId.value === myUserId.value)
const canInteract = computed(() => !canChooseTurn.value && isMyTurn.value)

const me = computed(() => (props.players ?? []).find(p => Number(p?.user_id) === myUserId.value) ?? null)
const opp = computed(() => (props.players ?? []).find(p => Number(p?.user_id) !== myUserId.value) ?? null)
const myName = computed(() => me.value?.name ?? 'Toi')
const oppName = computed(() => opp.value?.name ?? 'Adversaire')

const submittingChoose = ref(false)
function choose(firstId: number) {
  if (!canChooseTurn.value) return
  if (submittingChoose.value) return
  if (!Number.isFinite(firstId) || firstId <= 0) return
  submittingChoose.value = true
  emit('chooseTurn', firstId)
  window.setTimeout(() => {
    submittingChoose.value = false
  }, 1200)
}

const candidateIds = computed<number[]>(() => {
  const v = props.round?.candidate_ids
  if (!Array.isArray(v)) return []
  return v.map((x: any) => Number(x)).filter((n: number) => Number.isFinite(n) && n > 0)
})
const bannedIds = computed<Set<number>>(() => {
  const v = props.round?.banned_ids
  const set = new Set<number>()
  if (Array.isArray(v)) {
    for (const x of v) {
      const n = Number(x)
      if (Number.isFinite(n) && n > 0) set.add(n)
    }
  }
  return set
})

function normalizeKeyType(t: any): 'enum' | 'number' {
  const s = String(t ?? '').toLowerCase()
  if (['number', 'int', 'integer', 'numeric'].includes(s)) return 'number'
  return 'enum'
}

const availableKeys = computed(() => {
  const out: Array<{ key: string; type: 'enum' | 'number'; ops: string[] }> = []
  out.push({ key: 'player', type: 'enum', ops: ['eq'] })
  const v = props.round?.available_keys
  if (Array.isArray(v)) {
    for (const k of v) {
      const key = String(k?.key ?? '')
      if (!key) continue
      const type = normalizeKeyType(k?.type ?? 'enum')
      const ops = Array.isArray(k?.ops) ? k.ops.map((x: any) => String(x)) : ['eq']
      const cleanOps = ops.length ? ops : ['eq']
      out.push({ key, type, ops: cleanOps })
    }
  }
  return out
})

function keyLabel(key: string): string {
  return ({
    player: 'Joueur',
    country_code: 'Nationalité',
    role_id: 'Rôle',
    lol_role: 'LoL rôle',
    game_id: 'Jeu',
    current_team_id: 'Équipe actuelle',
    previous_team_id: 'Équipe précédente',
    trophies_count: 'Trophées',
    first_official_year: '1re année pro',
    age: 'Âge',
  } as any)[key] ?? key
}

function opLabel(op: string): string {
  return op === 'eq' ? '=' : op === 'lt' ? '<' : op === 'gt' ? '>' : op
}

const activePanel = ref<Panel>('keys')
const search = ref('')

const selectedKey = ref<string | null>(null)
const selectedKeyType = ref<'enum' | 'number' | null>(null)
const selectedOps = ref<string[]>([])
const selectedOp = ref<string | null>(null)
const selectedValue = ref<any>(null)

const turnLabel = computed(() => {
  if (canChooseTurn.value) return 'Choix du premier joueur…'
  if (!turnUserId.value) return 'Préparation du tour…'
  if (turnUserId.value === myUserId.value) return 'À toi de jouer'
  return `Au tour de ${oppName.value}`
})

const joueurs = ref<any[]>([])
const playersById = computed(() => {
  const map = new Map<number, any>()
  for (const j of joueurs.value) {
    if (typeof j?.id === 'number') map.set(j.id, j)
  }
  return map
})
const filteredSelectablePlayers = computed<any[]>(() => {
  const ids = new Set(candidateIds.value)
  const banned = bannedIds.value
  const out: any[] = []
  for (const j of joueurs.value) {
    const id = Number(j?.id)
    if (!Number.isFinite(id) || id <= 0) continue
    if (!ids.has(id)) continue
    if (banned.has(id)) continue
    out.push(j)
  }
  return out
})

const countries = ref<any[]>([])
const teams = ref<any[]>([])
const games = ref<any[]>([])
const roles = ref<any[]>([])
const lolRoles = ref<any[]>([])

const isNumberKey = computed(() => selectedKeyType.value === 'number')
const isEnumKey = computed(() => selectedKeyType.value === 'enum')
const isPlayerKey = computed(() => selectedKey.value === 'player')

const valueItems = computed<any[]>(() => {
  const key = selectedKey.value
  if (!key) return []
  if (key === 'player') return filteredSelectablePlayers.value
  if (key === 'country_code') return countries.value
  if (key === 'current_team_id' || key === 'previous_team_id') return teams.value
  if (key === 'game_id') return games.value
  if (key === 'role_id') return roles.value
  if (key === 'lol_role') return lolRoles.value
  return []
})

const searchedValueItems = computed(() => {
  const q = search.value.trim().toLowerCase()
  const items = valueItems.value
  if (!q) return items
  return items.filter((it: any) => {
    const label =
      (it?.name ?? it?.display_name ?? it?.label ?? it?.short_name ?? it?.code ?? it?.slug ?? '')
        .toString()
        .toLowerCase()
    return label.includes(q)
  })
})

function valueItemKey(it: any): string {
  const k = selectedKey.value
  if (k === 'country_code') return String(it?.code ?? '')
  if (k === 'current_team_id' || k === 'previous_team_id') return String(it?.id ?? '')
  if (k === 'game_id') return String(it?.id ?? '')
  if (k === 'role_id') return String(it?.id ?? '')
  if (k === 'lol_role') return String(it?.code ?? '')
  if (k === 'player') return String(it?.id ?? '')
  return String(it?.id ?? it?.code ?? '')
}

function valueItemLabel(it: any): string {
  const k = selectedKey.value
  if (k === 'country_code') return String(it?.name ?? it?.code ?? '')
  if (k === 'current_team_id' || k === 'previous_team_id') return String(it?.display_name ?? it?.short_name ?? it?.slug ?? '')
  if (k === 'game_id') return String(it?.name ?? it?.code ?? '')
  if (k === 'role_id') return String(it?.label ?? it?.code ?? '')
  if (k === 'lol_role') return String(it?.label ?? it?.code ?? '')
  if (k === 'player') return String(it?.name ?? it?.player?.name ?? it?.player?.slug ?? '')
  return String(it?.label ?? it?.name ?? it?.code ?? '')
}

function valueItemImage(it: any): string | null {
  const k = selectedKey.value
  if (k === 'country_code') return it?.flag_url ?? null
  if (k === 'current_team_id' || k === 'previous_team_id') return it?.logo_url ?? null
  if (k === 'game_id') return it?.logo_url ?? null
  if (k === 'lol_role') return it?.icon_url ?? null
  if (k === 'player') return it?.image_url ?? it?.player?.image_url ?? null
  return null
}

const selectedPlayer = computed(() => {
  if (selectedKey.value !== 'player') return null
  const id = Number(selectedValue.value ?? 0)
  if (!id) return null
  return playersById.value.get(id) ?? null
})

function resetSelection() {
  selectedKey.value = null
  selectedKeyType.value = null
  selectedOps.value = []
  selectedOp.value = null
  selectedValue.value = null
  search.value = ''
  activePanel.value = canInteract.value ? 'keys' : null
}

function clickCaseKeys() {
  if (canChooseTurn.value) return
  if (!canInteract.value) return
  activePanel.value = 'keys'
}

function clickCaseOps() {
  if (canChooseTurn.value) return
  if (!canInteract.value) return
  if (!selectedKey.value) {
    activePanel.value = 'keys'
    return
  }
  if (selectedOps.value.length <= 1) {
    selectedOp.value = selectedOps.value[0] ?? 'eq'
    activePanel.value = 'values'
    return
  }
  activePanel.value = 'ops'
}

function clickCaseValues() {
  if (canChooseTurn.value) return
  if (!canInteract.value) return
  if (!selectedKey.value) {
    activePanel.value = 'keys'
    return
  }
  if (!selectedOp.value) {
    if (selectedOps.value.length <= 1) selectedOp.value = selectedOps.value[0] ?? 'eq'
    else {
      activePanel.value = 'ops'
      return
    }
  }
  activePanel.value = 'values'
}

function selectKey(k: { key: string; type: 'enum' | 'number'; ops: string[] }) {
  if (!canInteract.value) return
  selectedKey.value = k.key
  selectedKeyType.value = k.type
  selectedOps.value = k.ops
  selectedOp.value = null
  selectedValue.value = null
  search.value = ''
  numberDraft.value = ''

  if (k.ops.length <= 1) {
    selectedOp.value = k.ops[0] ?? 'eq'
    activePanel.value = 'values'
    return
  }

  activePanel.value = 'ops'
}

function selectOp(op: string) {
  if (!canInteract.value) return
  selectedOp.value = op
  activePanel.value = 'values'
}

function selectEnumValue(it: any) {
  if (!canInteract.value) return
  const k = selectedKey.value
  if (!k) return

  if (k === 'country_code') selectedValue.value = String(it?.code ?? '').toUpperCase()
  else if (k === 'current_team_id' || k === 'previous_team_id') selectedValue.value = Number(it?.id ?? 0)
  else if (k === 'game_id') selectedValue.value = Number(it?.id ?? 0)
  else if (k === 'role_id') selectedValue.value = Number(it?.id ?? 0)
  else if (k === 'lol_role') selectedValue.value = String(it?.code ?? '').toUpperCase()
  else if (k === 'player') selectedValue.value = Number(it?.id ?? 0)
  else selectedValue.value = it

  activePanel.value = null
}

const numberDraft = ref<string>('')

function commitNumber() {
  if (!canInteract.value) return
  const raw = numberDraft.value.trim()
  if (raw === '') return
  const n = Number(raw)
  if (!Number.isFinite(n)) return
  if (n < 0) return
  selectedValue.value = Math.trunc(n)
  activePanel.value = null
}

const canSubmit = computed(() => {
  if (!canInteract.value) return false
  if (!selectedKey.value) return false
  if (!selectedOp.value) return false
  if (selectedValue.value === null || selectedValue.value === undefined || selectedValue.value === '') return false
  if (isNumberKey.value) {
    const n = Number(selectedValue.value)
    if (!Number.isFinite(n) || n < 0) return false
  }
  return true
})

const submitting = ref(false)
function submit() {
  if (!canSubmit.value) return
  if (submitting.value) return
  submitting.value = true

  const key = String(selectedKey.value)
  const op = String(selectedOp.value)
  const value = selectedValue.value

  resetSelection()

  if (key === 'player') {
    emit('guess', Number(value))
  } else {
    emit('ask', { key, op, value })
  }

  window.setTimeout(() => {
    submitting.value = false
  }, 1200)
}

function caseLeftText(): string {
  if (!selectedKey.value) return '—'
  return keyLabel(selectedKey.value)
}

function caseMidText(): string {
  if (!selectedOp.value) return '—'
  return opLabel(selectedOp.value)
}

function caseRightText(): string {
  if (selectedValue.value === null || selectedValue.value === undefined || selectedValue.value === '') return '—'
  if (selectedKey.value === 'player') {
    const p = selectedPlayer.value
    return p ? String(p?.name ?? p?.player?.name ?? '') : 'Joueur'
  }
  if (selectedKey.value === 'country_code') {
    const c = countries.value.find(x => String(x?.code ?? '').toUpperCase() === String(selectedValue.value).toUpperCase())
    return c ? String(c?.name ?? c?.code ?? '') : String(selectedValue.value)
  }
  if (selectedKey.value === 'current_team_id' || selectedKey.value === 'previous_team_id') {
    const t = teams.value.find(x => Number(x?.id ?? 0) === Number(selectedValue.value))
    return t ? String(t?.display_name ?? t?.short_name ?? '') : 'Équipe'
  }
  if (selectedKey.value === 'game_id') {
    const g = games.value.find(x => Number(x?.id ?? 0) === Number(selectedValue.value))
    return g ? String(g?.name ?? '') : 'Jeu'
  }
  if (selectedKey.value === 'role_id') {
    const r = roles.value.find(x => Number(x?.id ?? 0) === Number(selectedValue.value))
    return r ? String(r?.label ?? '') : 'Rôle'
  }
  if (selectedKey.value === 'lol_role') {
    const lr = lolRoles.value.find(x => String(x?.code ?? '').toUpperCase() === String(selectedValue.value).toUpperCase())
    return lr ? String(lr?.label ?? lr?.code ?? '') : String(selectedValue.value)
  }
  return String(selectedValue.value)
}

function caseRightImg(): string | null {
  if (selectedKey.value === 'player') {
    const p = selectedPlayer.value
    return p?.image_url ?? p?.player?.image_url ?? null
  }
  if (selectedKey.value === 'country_code') {
    const c = countries.value.find(x => String(x?.code ?? '').toUpperCase() === String(selectedValue.value).toUpperCase())
    return c?.flag_url ?? null
  }
  if (selectedKey.value === 'current_team_id' || selectedKey.value === 'previous_team_id') {
    const t = teams.value.find(x => Number(x?.id ?? 0) === Number(selectedValue.value))
    return t?.logo_url ?? null
  }
  if (selectedKey.value === 'game_id') {
    const g = games.value.find(x => Number(x?.id ?? 0) === Number(selectedValue.value))
    return g?.logo_url ?? null
  }
  if (selectedKey.value === 'lol_role') {
    const lr = lolRoles.value.find(x => String(x?.code ?? '').toUpperCase() === String(selectedValue.value).toUpperCase())
    return lr?.icon_url ?? null
  }
  return null
}

const history = computed(() => {
  const evs = Array.isArray(props.events) ? props.events : []
  return evs.slice(-10).reverse()
})

async function loadRefs() {
  try {
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
  } catch {
    flash.error('Impossible de charger les références.', 'PvP')
  }
}

async function loadPlayers() {
  try {
    const { data } = await api.get(`/games/${props.game}/players`, { params: { active: 1 } })
    joueurs.value = data.players ?? []
  } catch {
    flash.error('Impossible de charger les joueurs.', 'PvP')
  }
}

watch(candidateIds, () => {
  if (selectedKey.value === 'player' && selectedValue.value) {
    const id = Number(selectedValue.value)
    if (!candidateIds.value.includes(id) || bannedIds.value.has(id)) {
      selectedValue.value = null
      if (canInteract.value) activePanel.value = 'values'
    }
  }
})

watch(isMyTurn, (v, prev) => {
  if (canChooseTurn.value) return
  if (v === true && prev === false) {
    activePanel.value = 'keys'
  } else if (v === false) {
    activePanel.value = null
  }
})

watch(canInteract, (v) => {
  if (!v) activePanel.value = null
  else if (activePanel.value === null) activePanel.value = 'keys'
})

onMounted(async () => {
  await Promise.all([loadRefs(), loadPlayers()])
  activePanel.value = canInteract.value ? 'keys' : null
})
</script>

<template>
  <section class="whois" :class="dleCode">
    <header class="whois-header">
      <div class="brand">
        <SimpleImg class="logo" :alt="dleCode" :img="dleCode + '_page_Logo.png'" />
        <div class="title">
          <div class="mode">WHOIS</div>
          <div class="sub">{{ turnLabel }}</div>
        </div>
      </div>
    </header>

    <div class="card" v-if="canChooseTurn">
      <div class="card-title">Choisir qui commence</div>
      <div class="card-sub">Tu dois sélectionner le joueur qui aura le premier tour.</div>

      <div class="choices">
        <button class="choice" :disabled="submittingChoose" @click="choose(myUserId)">
          <div class="choice-name">{{ myName }}</div>
          <div class="choice-hint">Commencer</div>
        </button>

        <button class="choice" :disabled="submittingChoose || !opp" @click="opp && choose(Number(opp.user_id))">
          <div class="choice-name">{{ oppName }}</div>
          <div class="choice-hint">Laisser commencer</div>
        </button>
      </div>
    </div>

    <div class="card" v-else>
      <div class="history" v-if="history.length">
        <div class="history-title">Historique</div>

        <div class="history-list">
          <div v-for="(e, idx) in history" :key="idx" class="history-item">
            <div class="history-type">{{ String(e?.type ?? '') }}</div>

            <div class="history-line" v-if="e?.type === 'whois_question'">
              <span class="h-key">{{ keyLabel(String(e?.payload?.question?.key ?? '')) }}</span>
              <span class="h-op">{{ opLabel(String(e?.payload?.question?.op ?? '')) }}</span>
              <span class="h-val">{{ String(e?.payload?.question?.value ?? '') }}</span>
              <span class="h-res" :class="{ ok: !!e?.payload?.answer }">{{ e?.payload?.answer ? 'VRAI' : 'FAUX' }}</span>
            </div>

            <div class="history-line" v-else-if="e?.type === 'whois_guess'">
              <span class="h-key">Joueur</span>
              <span class="h-op">=</span>
              <span class="h-val">{{ String(e?.payload?.player_id ?? '') }}</span>
              <span class="h-res" :class="{ ok: !!e?.payload?.correct }">{{ e?.payload?.correct ? 'JUSTE' : 'FAUX' }}</span>
            </div>

            <div class="history-line" v-else-if="e?.type === 'whois_turn_chosen'">
              <span class="h-val">Premier tour: {{ String(e?.payload?.first_user_id ?? '') }}</span>
            </div>

            <div class="history-line" v-else-if="e?.type === 'whois_eliminated'">
              <span class="h-val">Éliminé: {{ String(e?.payload?.player_id ?? '') }}</span>
            </div>
          </div>
        </div>
      </div>

      <div class="cases">
        <button class="case" :class="{ active: activePanel === 'keys' }" @click="clickCaseKeys">
          <div class="case-label">Clé</div>
          <div class="case-value">{{ caseLeftText() }}</div>
        </button>

        <button class="case case--mid" :class="{ active: activePanel === 'ops' }" @click="clickCaseOps">
          <div class="case-label">Op</div>
          <div class="case-value">{{ caseMidText() }}</div>
        </button>

        <button class="case" :class="{ active: activePanel === 'values' }" @click="clickCaseValues">
          <div class="case-label">Valeur</div>
          <div class="case-value">
            <div class="picked" v-if="caseRightText() !== '—'">
              <SimpleImg v-if="caseRightImg()" class="picked-img" :alt="caseRightText()" :img="caseRightImg()!" />
              <div class="picked-name">{{ caseRightText() }}</div>
            </div>
            <div v-else>—</div>
          </div>
        </button>
      </div>

      <div class="panel" v-if="activePanel">
        <div class="panel-head">
          <div class="panel-title">
            <template v-if="activePanel === 'keys'">Choisir une clé</template>
            <template v-else-if="activePanel === 'ops'">Choisir une opération</template>
            <template v-else>Choisir une valeur</template>
          </div>

          <button class="panel-reset" v-if="(selectedKey || selectedOp || selectedValue) && canInteract" @click="resetSelection">
            Reset
          </button>
        </div>

        <div class="panel-body" v-if="activePanel === 'keys'">
          <div class="grid grid--keys">
            <button
              v-for="k in availableKeys"
              :key="k.key"
              class="big-item"
              :disabled="!canInteract"
              @click="selectKey(k)"
            >
              {{ keyLabel(k.key) }}
            </button>
          </div>
        </div>

        <div class="panel-body" v-else-if="activePanel === 'ops'">
          <div class="grid grid--ops">
            <button
              v-for="op in selectedOps"
              :key="op"
              class="big-item"
              :disabled="!canInteract"
              @click="selectOp(op)"
            >
              {{ opLabel(op) }}
            </button>
          </div>
        </div>

        <div class="panel-body" v-else>
          <div v-if="isNumberKey" class="num">
            <input
              v-model="numberDraft"
              class="num-input"
              type="number"
              inputmode="numeric"
              min="0"
              step="1"
              :disabled="!canInteract"
              placeholder="Entrer une valeur…"
            />
            <button class="num-btn" :disabled="!canInteract || numberDraft.trim() === '' || Number(numberDraft) < 0" @click="commitNumber">
              Valider
            </button>
          </div>

          <template v-else>
            <div class="search">
              <input v-model="search" class="search-input" type="text" placeholder="Rechercher…" :disabled="!canInteract" />
            </div>

            <div class="grid">
              <div
                v-for="it in searchedValueItems"
                :key="valueItemKey(it)"
                class="grid-item"
                :class="{ disabled: !canInteract }"
                @click="selectEnumValue(it)"
              >
                <template v-if="isPlayerKey">
                  <PlayerCard :joueur="it" />
                </template>

                <template v-else>
                  <div class="ref">
                    <SimpleImg v-if="valueItemImage(it)" class="ref-img" :alt="valueItemLabel(it)" :img="valueItemImage(it)!" />
                    <div class="ref-label">{{ valueItemLabel(it) }}</div>
                  </div>
                </template>
              </div>

              <div v-if="searchedValueItems.length === 0" class="empty">Aucun résultat</div>
            </div>
          </template>
        </div>
      </div>

      <div class="footer">
        <button class="submit" :disabled="!canSubmit || submitting" @click="submit">
          <template v-if="selectedKey === 'player'">Guess</template>
          <template v-else>Indice</template>
        </button>
      </div>
    </div>
  </section>
</template>

<style scoped>
.whois {
  width: 100%;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.whois-header {
  width: 100%;
  background: rgba(6, 8, 18, 0.92);
  border-radius: 14px;
  padding: 12px;
  border: 1px solid rgba(255, 255, 255, 0.06);
}

.brand {
  display: flex;
  align-items: center;
  gap: 12px;
}

.logo {
  width: 44px;
  height: 44px;
  flex: 0 0 auto;
}

.title {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}

.mode {
  font-weight: 800;
  letter-spacing: 0.12em;
  font-size: 0.85rem;
  opacity: 0.9;
}

.sub {
  font-size: 0.95rem;
  opacity: 0.85;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.card {
  width: 100%;
  background: rgba(6, 8, 18, 0.92);
  border-radius: 14px;
  padding: 16px 12px 18px;
  border: 1px solid rgba(255, 255, 255, 0.06);
}

.card-title {
  font-size: 0.9rem;
  opacity: 0.85;
  margin-bottom: 8px;
  text-transform: uppercase;
  letter-spacing: 0.12em;
}

.card-sub {
  opacity: 0.85;
  margin-bottom: 12px;
  line-height: 1.3;
}

.choices {
  display: grid;
  grid-template-columns: 1fr;
  gap: 10px;
}

.choice {
  width: 100%;
  text-align: left;
  border-radius: 12px;
  padding: 14px 12px;
  border: 1px solid rgba(255, 255, 255, 0.10);
  background: rgba(255, 255, 255, 0.04);
  color: #f3f3f3;
  cursor: pointer;
}

.choice:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.choice-name {
  font-weight: 800;
  font-size: 1.05rem;
}

.choice-hint {
  opacity: 0.8;
  margin-top: 2px;
  font-size: 0.9rem;
}

.history {
  margin-bottom: 12px;
  border-radius: 14px;
  border: 1px solid rgba(255, 255, 255, 0.08);
  background: rgba(255, 255, 255, 0.03);
  padding: 12px;
}

.history-title {
  font-weight: 900;
  opacity: 0.9;
  margin-bottom: 10px;
}

.history-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.history-item {
  border-radius: 12px;
  padding: 10px 10px;
  border: 1px solid rgba(255, 255, 255, 0.08);
  background: rgba(0, 0, 0, 0.18);
}

.history-type {
  font-size: 0.75rem;
  opacity: 0.65;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  margin-bottom: 6px;
}

.history-line {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  align-items: center;
  font-weight: 800;
}

.h-res {
  margin-left: auto;
  opacity: 0.85;
}

.h-res.ok {
  opacity: 1;
}

.cases {
  display: grid;
  grid-template-columns: 1fr 0.6fr 1fr;
  gap: 10px;
  margin-top: 8px;
}

.case {
  border-radius: 12px;
  border: 1px solid rgba(255, 255, 255, 0.10);
  background: rgba(255, 255, 255, 0.04);
  color: #f3f3f3;
  padding: 10px;
  cursor: pointer;
  text-align: left;
  min-height: 74px;
}

.case.active {
  border-color: rgba(255, 255, 255, 0.22);
  background: rgba(255, 255, 255, 0.06);
}

.case-label {
  font-size: 0.75rem;
  opacity: 0.7;
  letter-spacing: 0.12em;
  text-transform: uppercase;
}

.case-value {
  margin-top: 6px;
  font-weight: 900;
  opacity: 0.92;
}

.case--mid {
  text-align: center;
}

.picked {
  display: flex;
  align-items: center;
  gap: 10px;
}

.picked-img {
  width: 34px;
  height: 34px;
  border-radius: 10px;
  flex: 0 0 auto;
}

.picked-name {
  font-weight: 900;
  line-height: 1.1;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.panel {
  margin-top: 12px;
  border-radius: 14px;
  border: 1px solid rgba(255, 255, 255, 0.08);
  background: rgba(255, 255, 255, 0.03);
  padding: 12px;
}

.panel-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  margin-bottom: 10px;
}

.panel-title {
  font-weight: 900;
  opacity: 0.9;
}

.panel-reset {
  border-radius: 10px;
  border: 1px solid rgba(255, 255, 255, 0.12);
  background: rgba(255, 255, 255, 0.04);
  color: #f3f3f3;
  padding: 8px 10px;
  cursor: pointer;
}

.grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 10px;
}

.grid--keys {
  grid-template-columns: 1fr;
}

.grid--ops {
  grid-template-columns: repeat(3, 1fr);
}

.big-item {
  width: 100%;
  border-radius: 12px;
  padding: 14px 12px;
  border: 1px solid rgba(255, 255, 255, 0.10);
  background: rgba(255, 255, 255, 0.04);
  color: #f3f3f3;
  font-weight: 900;
  cursor: pointer;
}

.big-item:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.search {
  margin-bottom: 10px;
}

.search-input {
  width: 100%;
  border-radius: 12px;
  padding: 10px 12px;
  border: 1px solid rgba(255, 255, 255, 0.10);
  background: rgba(0, 0, 0, 0.25);
  color: #f3f3f3;
  outline: none;
}

.grid-item.disabled {
  opacity: 0.55;
  pointer-events: none;
}

.ref {
  border-radius: 14px;
  border: 1px solid rgba(255, 255, 255, 0.10);
  background: rgba(255, 255, 255, 0.03);
  padding: 12px;
  display: flex;
  align-items: center;
  gap: 12px;
}

.ref-img {
  width: 42px;
  height: 42px;
  border-radius: 12px;
  flex: 0 0 auto;
}

.ref-label {
  font-weight: 900;
  line-height: 1.1;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.empty {
  opacity: 0.7;
  padding: 10px;
  text-align: center;
}

.num {
  display: flex;
  gap: 10px;
}

.num-input {
  width: 100%;
  border-radius: 12px;
  padding: 10px 12px;
  border: 1px solid rgba(255, 255, 255, 0.10);
  background: rgba(0, 0, 0, 0.25);
  color: #f3f3f3;
  outline: none;
}

.num-btn {
  border-radius: 12px;
  padding: 10px 14px;
  border: 1px solid rgba(255, 255, 255, 0.12);
  background: rgba(255, 255, 255, 0.08);
  color: #f3f3f3;
  font-weight: 900;
  cursor: pointer;
}

.footer {
  margin-top: 12px;
  display: flex;
  justify-content: flex-end;
}

.submit {
  width: 100%;
  border-radius: 12px;
  padding: 12px;
  border: 1px solid rgba(255, 255, 255, 0.12);
  background: rgba(255, 255, 255, 0.08);
  color: #f3f3f3;
  font-weight: 900;
  cursor: pointer;
}

.submit:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

@media (min-width: 720px) {
  .choices {
    grid-template-columns: 1fr 1fr;
  }

  .grid {
    grid-template-columns: 1fr 1fr;
  }

  .grid--keys {
    grid-template-columns: 1fr 1fr;
  }

  .submit {
    width: auto;
    min-width: 180px;
  }
}

@media (min-width: 980px) {
  .grid {
    grid-template-columns: 1fr 1fr 1fr;
  }
}
</style>
