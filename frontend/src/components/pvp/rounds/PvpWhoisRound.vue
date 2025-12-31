<script setup lang="ts">
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import api from '@/api'
import { useAuthStore } from '@/stores/auth'
import { useFlashStore } from '@/stores/flash'
import PlayerCard from '@/components/PlayerCard.vue'
import SimpleImg from '@/components/SimpleImg.vue'
import PvpChooseFirstPlayer from "@/components/pvp/PvpChooseFirstPlayer.vue";
import {handleError} from "@/utils/handleError.ts";

type GameCode = 'kcdle' | 'lecdle' | 'lfldle'
type Panel = 'keys' | 'ops' | 'values' | null
type KeyType = 'enum' | 'number'

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

const NUMERIC_KEYS = new Set<string>(['trophies_count', 'first_official_year', 'age'])

function normalizeKeyType(key: string, t: any): KeyType {
  if (NUMERIC_KEYS.has(key)) return 'number'
  const s = String(t ?? '').toLowerCase().trim()
  if (!s) return 'enum'
  if (s === 'enum') return 'enum'
  if (s === 'number') return 'number'
  if (s === 'int' || s === 'integer' || s === 'float' || s === 'double' || s === 'decimal' || s === 'numeric') return 'number'
  return 'enum'
}

function normalizeOpsForKey(type: KeyType, opsRaw: any): string[] {
  const ops = Array.isArray(opsRaw) ? opsRaw.map((x: any) => String(x)) : []
  const cleaned = ops.filter(x => x === 'eq' || x === 'lt' || x === 'gt')
  if (type === 'number') {
    const set = new Set(cleaned.length ? cleaned : ['lt', 'eq', 'gt'])
    set.add('lt')
    set.add('eq')
    set.add('gt')
    return Array.from(set)
  }
  return cleaned.length ? cleaned : ['eq']
}

const availableKeys = computed(() => {
  const out: Array<{ key: string; type: KeyType; ops: string[] }> = []
  out.push({ key: 'player', type: 'enum', ops: ['eq'] })
  const v = props.round?.available_keys
  if (Array.isArray(v)) {
    for (const k of v) {
      const key = String(k?.key ?? '')
      if (!key) continue
      const type = normalizeKeyType(key, k?.type)
      const ops = normalizeOpsForKey(type, k?.ops)
      out.push({ key, type, ops })
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
    first_official_year: '1re année officielle chez KC',
    age: 'Âge',
  } as any)[key] ?? key
}

function opLabel(op: string): string {
  return op === 'eq' ? '=' : op === 'lt' ? '<' : op === 'gt' ? '>' : op
}

const activePanel = ref<Panel>('keys')
const search = ref('')

const selectedKey = ref<string | null>(null)
const selectedKeyType = ref<KeyType | null>(null)
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
    const id = Number(j?.id)
    if (Number.isFinite(id) && id > 0) map.set(id, j)
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

const isNumberKey = computed(() => selectedKeyType.value === 'number')
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

const numberDraft = ref<string>('')
const numberError = ref<string | null>(null)

function parseNonNegativeInt(raw: string): number | null {
  const s = raw.trim().replace(',', '.')
  if (s === '') return null
  const n = Number(s)
  if (!Number.isFinite(n)) return null
  if (n < 0) return null
  return Math.trunc(n)
}

function resetSelection() {
  selectedKey.value = null
  selectedKeyType.value = null
  selectedOps.value = []
  selectedOp.value = null
  selectedValue.value = null
  search.value = ''
  numberDraft.value = ''
  numberError.value = null
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
  if (selectedKeyType.value === 'number') {
    activePanel.value = 'ops'
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

function selectKey(k: { key: string; type: KeyType; ops: string[] }) {
  if (!canInteract.value) return
  selectedKey.value = k.key
  selectedKeyType.value = k.type
  selectedOps.value = k.ops
  selectedOp.value = null
  selectedValue.value = null
  search.value = ''
  numberDraft.value = ''
  numberError.value = null
  activePanel.value = k.ops.length <= 1 ? 'values' : 'ops'
  if (k.ops.length <= 1) selectedOp.value = k.ops[0] ?? 'eq'
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

function commitNumber() {
  if (!canInteract.value) return
  numberError.value = null
  const parsed = parseNonNegativeInt(numberDraft.value)
  if (parsed === null) {
    numberError.value = 'Valeur invalide (entier ≥ 0)'
    return
  }
  selectedValue.value = parsed
  activePanel.value = null
}

function onNumberEnter() {
  if (!canInteract.value) return
  commitNumber()
}

const canSubmit = computed(() => {
  if (!canInteract.value) return false
  if (!selectedKey.value) return false
  if (!selectedOp.value) return false
  if (selectedValue.value === null || selectedValue.value === undefined || selectedValue.value === '') return false
  if (selectedKeyType.value === 'number') {
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
  if (key === 'player') emit('guess', Number(value))
  else emit('ask', { key, op, value })
  window.setTimeout(() => {
    submitting.value = false
  }, 1200)
}

function resolveEnumLabel(key: string, value: any): string {
  if (key === 'country_code') {
    const code = String(value ?? '').toUpperCase()
    const c = countriesByCode.value.get(code)
    return c ? String(c?.name ?? code) : code
  }
  if (key === 'current_team_id' || key === 'previous_team_id') {
    const id = Number(value ?? 0)
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
  return String(value ?? '')
}

function resolveEnumImage(key: string, value: any): string | null {
  if (key === 'country_code') {
    const code = String(value ?? '').toUpperCase()
    const c = countriesByCode.value.get(code)
    return c?.flag_url ?? null
  }
  if (key === 'current_team_id' || key === 'previous_team_id') {
    const id = Number(value ?? 0)
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

function resolvePlayerLabel(playerId: any): string {
  const id = Number(playerId ?? 0)
  const p = playersById.value.get(id)
  return p ? String(p?.name ?? p?.player?.name ?? p?.player?.slug ?? id) : String(id)
}

function resolvePlayerImage(playerId: any): string | null {
  const id = Number(playerId ?? 0)
  const p = playersById.value.get(id)
  return p?.image_url ?? p?.player?.image_url ?? null
}

function caseRightText(): string {
  if (selectedValue.value === null || selectedValue.value === undefined || selectedValue.value === '') return '—'
  if (selectedKey.value === 'player') return resolvePlayerLabel(selectedValue.value)
  if (!selectedKey.value) return '—'
  if (NUMERIC_KEYS.has(selectedKey.value)) return String(selectedValue.value)
  return resolveEnumLabel(selectedKey.value, selectedValue.value)
}

function caseRightImg(): string | null {
  if (selectedValue.value === null || selectedValue.value === undefined || selectedValue.value === '') return null
  if (selectedKey.value === 'player') return resolvePlayerImage(selectedValue.value)
  if (!selectedKey.value) return null
  if (NUMERIC_KEYS.has(selectedKey.value)) return null
  return resolveEnumImage(selectedKey.value, selectedValue.value)
}

function pluralize(n: number, singular: string, plural: string): string {
  return n === 1 ? singular : plural
}

function startsWithVowelSound(s: string): boolean {
  const t = (s ?? '').trim().toLowerCase()
  if (!t) return false
  if(!t[0]) return false
  const first = t[0]
  if ('aeiouyàâäéèêëîïôöùûüœ'.includes(first)) return true
  const hWords = ['honorable', 'honnête', 'honnêtes', 'honneur', 'histoire', 'histoires', 'héritier', 'héritière', 'héros']
  for (const w of hWords) {
    if (t.startsWith(w)) return true
  }
  return false
}

function artDe(label: string): string {
  return startsWithVowelSound(label) ? `d’${label}` : `de ${label}`
}

function artAu(label: string): string {
  return startsWithVowelSound(label) ? `à ${label}` : `au ${label}`
}

function artDansEquipe(label: string): string {
  return startsWithVowelSound(label) ? `chez ${label}` : `chez ${label}`
}

const NATIONALITY_ADJ: Record<string, string> = {
  FR: 'français',
  BE: 'belge',
  CH: 'suisse',
  CA: 'canadien',
  US: 'américain',
  GB: 'britannique',
  UK: 'britannique',
  ES: 'espagnol',
  PT: 'portugais',
  IT: 'italien',
  DE: 'allemand',
  NL: 'néerlandais',
  LU: 'luxembourgeois',
  IE: 'irlandais',
  PL: 'polonais',
  RO: 'roumain',
  HU: 'hongrois',
  SE: 'suédois',
  NO: 'norvégien',
  DK: 'danois',
  FI: 'finlandais',
  GR: 'grec',
  TR: 'turc',
  RU: 'russe',
  UA: 'ukrainien',
  BR: 'brésilien',
  AR: 'argentin',
  MX: 'mexicain',
  CO: 'colombien',
  CL: 'chilien',
  PE: 'péruvien',
  CN: 'chinois',
  JP: 'japonais',
  KR: 'coréen',
  VN: 'vietnamien',
  TH: 'thaïlandais',
  MA: 'marocain',
  DZ: 'algérien',
  TN: 'tunisien',
  SN: 'sénégalais',
  CI: 'ivoirien',
  CM: 'camerounais',
  NG: 'nigérian',
  EG: 'égyptien',
  ZA: 'sud-africain',
  AU: 'australien',
  NZ: 'néo-zélandais',
}

const ROLE: Record<string, string> = {
  SUP: 'support',
  TOP: 'toplaner',
  BOT: 'adc',
  JNG: 'jungler',
  MID: 'midlaner'
}

function nationalityAdjective(code: string, countryName: string): { m: string; f: string } {
  const c = (code ?? '').toUpperCase()
  const base = NATIONALITY_ADJ[c]
  if (base) return { m: base, f: `${base}e` }
  const n = (countryName ?? '').trim()
  return { m: n, f: n }
}

function whoisSentenceForEnum(key: string, value: any, ok: boolean): string {
  if (key === 'country_code') {
    const code = String(value ?? '').toUpperCase()
    const c = countriesByCode.value.get(code)
    const name = String(c?.name ?? code)
    const adj = nationalityAdjective(code, name)
    return ok ? `Il est ${adj.m}.` : `Il n’est pas ${adj.m}.`
  }
  if (key === 'game_id') {
    const label = resolveEnumLabel(key, value)
    const chunk = artDe(label)
    return ok ? `Il est un joueur ${chunk}.` : `Il n’est pas un joueur ${chunk}.`
  }
  if (key === 'current_team_id') {
    const label = resolveEnumLabel(key, value)
    const chunk = artDansEquipe(label)
    return ok ? `Il est ${chunk}.` : `Il n’est pas ${chunk}.`
  }
  if (key === 'previous_team_id') {
    const label = resolveEnumLabel(key, value)
    const chunk = artDansEquipe(label)
    return ok ? `Il a joué ${chunk} avant KC.` : `Il n’a pas joué ${chunk} avant KC.`
  }
  if (key === 'role_id') {
    const label = resolveEnumLabel(key, value)
    return ok ? `Il a le rôle de ${label}.` : `Il n’a pas le rôle de ${label}.`
  }
  if (key === 'lol_role') {
    const label = resolveEnumLabel(key, value)
    const label_role = ROLE[label];
    return ok ? `Il joue au poste de ${label_role}.` : `Il ne joue pas au poste de ${label_role}.`
  }
  const label = resolveEnumLabel(key, value)
  return ok ? `Il correspond à ${label}.` : `Il ne correspond pas à ${label}.`
}

function whoisSentenceForNumber(key: string, op: string, value: any, ok: boolean): string {
  const n = Number(value ?? 0)
  if (key === 'first_official_year') {
    const y = Math.trunc(n)
    if (op === 'eq') return ok ? `Sa première année officielle chez KC est en ${y}.` : `Sa première année officielle chez KC n’est pas ${y}.`
    if (op === 'gt') return ok ? `Sa première année officielle chez KC est après ${y}.` : `Sa première année officielle chez KC n’est pas après ${y}.`
    if (op === 'lt') return ok ? `Sa première année officielle chez KC est avant ${y}.` : `Sa première année officielle chez KC n’est pas avant ${y}.`
    return ok ? `Sa première année officielle chez KC correspond à ${y}.` : `Sa première année officielle chez KC ne correspond pas à ${y}.`
  }

  let unitSing = ''
  let unitPlur = ''
  if (key === 'trophies_count') {
    unitSing = 'trophée'
    unitPlur = 'trophées'
  } else if (key === 'age') {
    unitSing = 'an'
    unitPlur = 'ans'
  } else {
    unitSing = 'unité'
    unitPlur = 'unités'
  }

  const unit = pluralize(Math.trunc(n), unitSing, unitPlur)

  if (op === 'gt') return ok ? `Il a plus de ${Math.trunc(n)} ${unit}.` : `Il n’a pas plus de ${Math.trunc(n)} ${unit}.`
  if (op === 'lt') return ok ? `Il a moins de ${Math.trunc(n)} ${unit}.` : `Il n’a pas moins de ${Math.trunc(n)} ${unit}.`
  if (op === 'eq') return ok ? `Il a ${Math.trunc(n)} ${unit}.` : `Il n’a pas ${Math.trunc(n)} ${unit}.`
  return ok ? `Il correspond à ${Math.trunc(n)} ${unit}.` : `Il ne correspond pas à ${Math.trunc(n)} ${unit}.`
}

function historySentence(it: HistoryActionItem): string {
  if (it.type === 'whois_guess') {
    return it.ok ? `C’est ${it.playerLabel}.` : `Ce n’est pas ${it.playerLabel}.`
  }
  if (NUMERIC_KEYS.has(it.key)) {
    return whoisSentenceForNumber(it.key, it.op, it.valueRaw, it.ok)
  }
  return whoisSentenceForEnum(it.key, it.valueRaw, it.ok)
}

type HistoryActionItem =
  | {
  actionIndex: number
  type: 'whois_question'
  key: string
  op: string
  valueLabel: string
  valueImage: string | null
  valueRaw: any
  ok: boolean
}
  | {
  actionIndex: number
  type: 'whois_guess'
  playerLabel: string
  playerImage: string | null
  ok: boolean
}

function uniqEvents(evs: any[]): any[] {
  const arr = Array.isArray(evs) ? evs.slice() : []
  const hasId = arr.some(e => e && (typeof e.id === 'number' || typeof e.id === 'string'))
  if (hasId) {
    const map = new Map<string, any>()
    for (const e of arr) {
      const id = e?.id
      if (id === null || id === undefined) continue
      const key = String(id)
      if (!map.has(key)) map.set(key, e)
    }
    const out = Array.from(map.values())
    out.sort((a, b) => Number(a.id) - Number(b.id))
    return out
  }

  const map = new Map<string, any>()
  for (const e of arr) {
    const type = String(e?.type ?? '')
    const payload = e?.payload ?? {}
    const sig = `${type}:${JSON.stringify(payload)}`
    if (!map.has(sig)) map.set(sig, e)
  }
  return Array.from(map.values())
}

const historyItems = computed<HistoryActionItem[]>(() => {
  const evs = uniqEvents(props.events ?? [])
  const items: HistoryActionItem[] = []
  let actionIndex = 0

  for (const e of evs) {
    const type = String(e?.type ?? '')
    if (type === 'whois_question') {
      actionIndex += 1
      const q = e?.payload?.question ?? {}
      const key = String(q?.key ?? '')
      const op = String(q?.op ?? 'eq')
      const value = q?.value
      const ok = !!e?.payload?.answer
      const valueLabel = NUMERIC_KEYS.has(key) ? String(value ?? '') : resolveEnumLabel(key, value)
      const valueImage = NUMERIC_KEYS.has(key) ? null : resolveEnumImage(key, value)

      items.push({
        actionIndex,
        type: 'whois_question',
        key,
        op,
        valueLabel,
        valueImage,
        valueRaw: value,
        ok,
      })
    } else if (type === 'whois_guess') {
      actionIndex += 1
      const pid = Number(e?.payload?.player_id ?? 0)
      const ok = !!e?.payload?.correct
      items.push({
        actionIndex,
        type: 'whois_guess',
        playerLabel: resolvePlayerLabel(pid),
        playerImage: resolvePlayerImage(pid),
        ok,
      })
    }
  }

  return items.slice().reverse()
})

const hasHistory = computed(() => historyItems.value.length > 0)
const historyScrollRef = ref<HTMLElement | null>(null)

async function scrollHistoryToTop() {
  await nextTick()
  const el = historyScrollRef.value
  if (!el) return
  el.scrollTop = 0
}

watch(
  () => historyItems.value.length,
  async (n, p) => {
    if (n > (p ?? 0)) await scrollHistoryToTop()
  }
)

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
  } catch (e) {
    handleError(e, 'Impossible de charger les références.')
  }
}

async function loadPlayers() {
  try {
    const { data } = await api.get(`/games/${props.game}/players`, { params: { active: 1 } })
    joueurs.value = data.players ?? []
  } catch (e) {
    handleError(e, 'Impossible de charger les joueurs.')
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
  if (v && !prev) activePanel.value = 'keys'
  else if (!v) activePanel.value = null
})

watch(canInteract, (v) => {
  if (!v) activePanel.value = null
  else if (activePanel.value === null) activePanel.value = 'keys'
})

onMounted(async () => {
  await Promise.all([loadRefs(), loadPlayers()])
  activePanel.value = canInteract.value ? 'keys' : null
  await scrollHistoryToTop()
})
</script>

<template>
  <section class="whois" :class="dleCode">
    <header class="whois-header">
      <div class="brand">
        <div class="title">
          <div class="mode">WHOIS</div>
          <div class="sub">{{ turnLabel }}</div>
        </div>
      </div>
    </header>

    <PvpChooseFirstPlayer
      v-if="canChooseTurn"
      :game="props.game"
      :players="props.players"
      :can-choose="canChooseTurn"
      :disabled="submittingChoose"
      @choose="choose"
    />

    <div class="card card--layout" v-else>
      <div class="left">
        <div class="cases">
          <button class="case" :class="{ active: activePanel === 'keys' }" :disabled="!canInteract" @click="clickCaseKeys">
            <div class="case-label">Clé</div>
            <div class="case-value">{{ selectedKey ? keyLabel(selectedKey) : '—' }}</div>
          </button>

          <button class="case case--mid" :class="{ active: activePanel === 'ops' }" :disabled="!canInteract" @click="clickCaseOps">
            <div class="case-label">Op</div>
            <div class="case-value">{{ selectedOp ? opLabel(selectedOp) : '—' }}</div>
          </button>

          <button class="case" :class="{ active: activePanel === 'values' }" :disabled="!canInteract" @click="clickCaseValues">
            <div class="case-label">Valeur</div>
            <div class="case-value">
              <div class="picked" v-if="caseRightText() !== '—'">
                <SimpleImg v-if="caseRightImg()" class="picked-img" :alt="caseRightText()" :img="caseRightImg()!" />
                <div class="picked-name" :class="{noImage: !caseRightImg()}">{{ caseRightText() }}</div>
              </div>
              <div v-else>—</div>
            </div>
          </button>
        </div>

        <div class="panel" v-if="canInteract && activePanel">
          <div class="panel-head">
            <div class="panel-title">
              <template v-if="activePanel === 'keys'">Choisir une clé</template>
              <template v-else-if="activePanel === 'ops'">Choisir une opération</template>
              <template v-else>Choisir une valeur</template>
            </div>

            <button class="panel-reset" v-if="selectedKey || selectedOp || selectedValue" @click="resetSelection">
              Reset
            </button>
          </div>

          <div class="panel-body panel-body--scroll" v-if="activePanel === 'keys'">
            <div class="grid grid--keys">
              <button v-for="k in availableKeys" :key="k.key" class="big-item" @click="selectKey(k)">
                {{ keyLabel(k.key) }}
              </button>
            </div>
          </div>

          <div class="panel-body panel-body--scroll" v-else-if="activePanel === 'ops'">
            <div class="grid grid--ops">
              <button v-for="op in selectedOps" :key="op" class="big-item" @click="selectOp(op)">
                {{ opLabel(op) }}
              </button>
            </div>
          </div>

          <div class="panel-body panel-body--scroll" v-else>
            <div v-if="isNumberKey" class="num">
              <input
                v-model="numberDraft"
                class="num-input"
                type="text"
                inputmode="numeric"
                placeholder="Entier ≥ 0"
                @keydown.enter.prevent="onNumberEnter"
              />
              <button class="num-btn" @click="commitNumber">Valider</button>
            </div>

            <div v-if="isNumberKey && numberError" class="num-error">{{ numberError }}</div>

            <template v-if="!isNumberKey">
              <div class="search">
                <input v-model="search" class="search-input" type="text" placeholder="Rechercher…" />
              </div>

              <div class="grid">
                <div
                  v-for="it in searchedValueItems"
                  :key="valueItemKey(it)"
                  class="grid-item"
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

        <div class="footer" v-if="canInteract">
          <button class="submit" :disabled="!canSubmit || submitting" @click="submit">
            <template v-if="selectedKey === 'player'">Guess</template>
            <template v-else>Indice</template>
          </button>
        </div>
      </div>

      <aside class="right">
        <div class="history">
          <div class="history-title">Indices</div>

          <div ref="historyScrollRef" class="history-scroll">
            <div class="history-list">
              <div v-if="!hasHistory" class="empty">Aucun indice pour le moment</div>

              <div
                v-for="(it, idx) in historyItems"
                :key="idx"
                class="history-item"
                :class="it.ok ? 'item-ok' : 'item-ko'"
              >
                <div class="history-meta">
                  <span class="meta-chip">Tour {{ it.actionIndex }}</span>
                </div>

                <div class="history-line">
                  <span class="h-val">
                    <span class="h-picked" v-if="it.type === 'whois_question' && it.valueImage">
                      <SimpleImg class="h-img" :alt="it.valueLabel" :img="it.valueImage" />
                    </span>
                    <span class="h-picked" v-else-if="it.type === 'whois_guess' && it.playerImage">
                      <SimpleImg class="h-img" :alt="it.playerLabel" :img="it.playerImage" />
                    </span>
                    <span class="h-txt">{{ historySentence(it) }}</span>
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </aside>
    </div>
  </section>
</template>

<style scoped>
.whois{width:100%;max-width:1200px;margin:0 auto;display:flex;flex-direction:column;gap:12px;padding:0 10px;box-sizing:border-box;overflow:hidden}
.whois-header{width:100%;background:rgba(6,8,18,.92);border-radius:16px;padding:12px;border:1px solid rgba(255,255,255,.06);box-sizing:border-box;overflow:hidden}
.brand{display:flex;flex-direction:column;align-items:flex-start;gap:8px;max-width:100%}
.title{display:flex;flex-direction:column;gap:2px;min-width:0;max-width:100%}
.mode{font-weight:900;letter-spacing:.16em;font-size:1.5rem;opacity:.9}
.sub{font-size:.95rem;opacity:.85;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:100%; margin: 0}

.card{width:100%;background:rgba(6,8,18,.92);border-radius:16px;padding:14px 12px 16px;border:1px solid rgba(255,255,255,.06);box-sizing:border-box;overflow:hidden}
.card-title{font-size:.9rem;opacity:.85;margin-bottom:8px;text-transform:uppercase;letter-spacing:.12em}
.card-sub{opacity:.85;margin-bottom:12px;line-height:1.3}

.choices{display:grid;grid-template-columns:1fr;gap:10px}
.choice{width:100%;text-align:left;border-radius:14px;padding:14px 12px;border:1px solid rgba(255,255,255,.10);background:rgba(255,255,255,.04);color:#f3f3f3;cursor:pointer;box-sizing:border-box}
.choice:disabled{opacity:.55;cursor:not-allowed}
.choice-name{font-weight:900;font-size:1.05rem}
.choice-hint{opacity:.8;margin-top:2px;font-size:.9rem}

.card--layout{display:flex;flex-direction:column;gap:12px;align-items:stretch}
.left{display:flex;flex-direction:column;gap:12px;min-width:0;max-width:100%}
.right{display:block;min-width:0;max-width:100%}

.cases{display:grid;grid-template-columns:1fr .6fr 1fr;gap:10px;max-width:100%}
.case{border-radius:14px;border:1px solid rgba(255,255,255,.10);background:rgba(255,255,255,.04);color:#f3f3f3;padding:10px;cursor:pointer;text-align:center;min-height:74px;box-sizing:border-box;max-width:100%;overflow:hidden;
  display: flex; flex-direction: column; align-items:center;}
.case:hover{background:rgba(255,255,255,.06)}
.case:disabled{opacity:.55;cursor:not-allowed}
.case.active{border-color:rgba(255,255,255,.22);background:rgba(255,255,255,.06)}
.case-label{font-size:.75rem;opacity:.7;letter-spacing:.12em;text-transform:uppercase}
.case-value{margin-top:6px;font-weight:900;opacity:.92;min-width:0}
.case--mid{text-align:center}
.picked{display:flex;align-items:center;gap:10px;min-width:0}
.picked-img{width:34px;height:34px;border-radius:12px;flex:0 0 auto}
.picked-name{font-weight:900;line-height:1.1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;min-width:0; display: none}
.picked-name.noImage{display: block}

.panel{border-radius:16px;border:1px solid rgba(255,255,255,.08);background:rgba(255,255,255,.03);padding:12px;box-sizing:border-box;min-width:0;max-width:100%;overflow:hidden}
.panel-head{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:10px;min-width:0}
.panel-title{font-weight:900;opacity:.9;min-width:0}
.panel-reset{border-radius:12px;border:1px solid rgba(255,255,255,.12);background:rgba(255,255,255,.04);color:#f3f3f3;padding:8px 10px;cursor:pointer;flex:0 0 auto}
.panel-reset:hover{background:rgba(255,255,255,.06)}
.panel-body{min-width:0;max-width:100%}
.panel-body--scroll{max-height:360px;overflow:auto;padding-right:6px;box-sizing:border-box}

.grid{display:grid;grid-template-columns:1fr;gap:10px;max-width:100%}
.grid--keys{grid-template-columns:1fr}
.grid--ops{grid-template-columns:repeat(3,1fr)}
.big-item{width:100%;border-radius:14px;padding:14px 12px;border:1px solid rgba(255,255,255,.10);background:rgba(255,255,255,.04);color:#f3f3f3;font-weight:900;cursor:pointer;box-sizing:border-box}
.big-item:hover{background:rgba(255,255,255,.06)}

.search{margin-bottom:10px}
.search-input{width:100%;border-radius:14px;padding:10px 12px;border:1px solid rgba(255,255,255,.10);background:rgba(0,0,0,.25);color:#f3f3f3;outline:none;box-sizing:border-box}

.grid-item{cursor:pointer;min-width:0}
.ref{border-radius:16px;border:1px solid rgba(255,255,255,.10);background:rgba(255,255,255,.03);padding:12px;display:flex;align-items:center;gap:12px;cursor:pointer;box-sizing:border-box;min-width:0}
.ref:hover{background:rgba(255,255,255,.05)}
.ref-img{width:42px;height:42px;border-radius:12px;flex:0 0 auto;object-fit:cover}
.ref-label{font-weight:900;line-height:1.1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;min-width:0}

.num{display:flex;gap:10px;min-width:0}
.num-input{width:100%;border-radius:14px;padding:10px 12px;border:1px solid rgba(255,255,255,.10);background:rgba(0,0,0,.25);color:#f3f3f3;outline:none;box-sizing:border-box}
.num-btn{border-radius:14px;padding:10px 14px;border:1px solid rgba(255,255,255,.12);background:rgba(255,255,255,.08);color:#f3f3f3;font-weight:900;cursor:pointer;flex:0 0 auto}
.num-btn:hover{background:rgba(255,255,255,.10)}
.num-error{margin-top:10px;opacity:.9}

.footer{display:flex;justify-content:flex-end}
.submit{width:100%;border-radius:14px;padding:12px;border:1px solid rgba(255,255,255,.12);background:rgba(255,255,255,.08);color:#f3f3f3;font-weight:900;cursor:pointer;box-sizing:border-box}
.submit:hover{background:rgba(255,255,255,.10)}
.submit:disabled{opacity:.55;cursor:not-allowed}

.history{border-radius:16px;border:1px solid rgba(255,255,255,.08);background:rgba(255,255,255,.03);padding:12px;box-sizing:border-box;min-width:0;max-width:100%;overflow:hidden}
.history-title{font-weight:900;opacity:.9;margin-bottom:10px}
.history-scroll{max-height:520px;overflow:auto;padding-right:6px;box-sizing:border-box}
.history-list{display:flex;flex-direction:column;gap:8px;max-width:100%}
.history-item{border-radius:14px;padding:10px 10px;border:1px solid rgba(255,255,255,.08);background:rgba(0,0,0,.18);box-sizing:border-box;max-width:100%;overflow:hidden}
.history-item.item-ok{border-color:rgba(53,208,127,.35);background:rgba(53,208,127,.12)}
.history-item.item-ko{border-color:rgba(255,77,77,.35);background:rgba(255,77,77,.12)}
.history-meta{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:8px}
.meta-chip{font-size:.72rem;letter-spacing:.1em;text-transform:uppercase;opacity:.8;border:1px solid rgba(255,255,255,.10);background:rgba(255,255,255,.03);border-radius:999px;padding:4px 8px}
.history-line{display:flex;flex-wrap:wrap;gap:8px;align-items:center;font-weight:900;max-width:100%}
.h-val{display:flex;align-items:center;gap:8px;min-width:0;max-width:100%}
.h-picked{display:flex;align-items:center;gap:8px;min-width:0;max-width:100%}
.h-img{width:26px;height:26px;border-radius:8px;flex:0 0 auto}
.h-txt{overflow-x: scroll;white-space:nowrap;min-width:0}
.h-res{margin-left:auto}

.empty{opacity:.7;padding:10px;text-align:center}

@media(min-width:720px){
  .choices{grid-template-columns:1fr 1fr}
  .grid{grid-template-columns:1fr 1fr}
  .grid--keys{grid-template-columns:1fr 1fr}
  .submit{width:auto;min-width:180px}
  .panel-body--scroll{max-height:420px}
  .whois{padding:0 14px}
  .picked-name{display: block}
}

@media(min-width:980px){
  .card--layout{display:grid;grid-template-columns:1.05fr .95fr;gap:12px;align-items:stretch}
  .history{height:100%}
  .history-scroll{max-height:520px}
  .right .history{position:sticky;top:12px}
  .grid{grid-template-columns:1fr 1fr 1fr}
  .panel-body--scroll{max-height:520px}
  .picked-name{display: block}
}
</style>
