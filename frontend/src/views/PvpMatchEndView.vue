<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useFlashStore } from '@/stores/flash'
import { usePvpStore } from '@/stores/pvp'
import { pvpGetMatch } from '@/api/pvpApi'
import type { BestOf, PvpGame } from '@/types/pvp'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const flash = useFlashStore()
const pvp = usePvpStore()

const matchId = computed(() => {
  const raw = route.params.matchId
  const n = typeof raw === 'string' ? Number(raw) : Array.isArray(raw) ? Number(raw[0]) : Number(raw)
  return Number.isFinite(n) ? n : null
})

const loading = ref(true)
const error = ref<string | null>(null)
const match = ref<any | null>(null)

const currentUserId = computed(() => Number(auth.user?.id ?? 0))

const players = computed(() => (Array.isArray(match.value?.players) ? match.value.players : []))
const left = computed(() => players.value[0] ?? null)
const right = computed(() => players.value[1] ?? null)

const winnerUserId = computed(() => Number(match.value?.result?.winner_user_id ?? 0))
const endedReason = computed(() => String(match.value?.result?.ended_reason ?? ''))

const startedAt = computed(() => (typeof match.value?.started_at === 'string' ? match.value.started_at : null))
const finishedAt = computed(() => (typeof match.value?.finished_at === 'string' ? match.value.finished_at : null))

const game = computed(() => (typeof match.value?.game === 'string' ? (match.value.game as PvpGame) : null))
const bestOf = computed(() => (typeof match.value?.best_of === 'number' ? (match.value.best_of as BestOf) : null))

const expandedRound = ref<number | null>(null)

function toggleRound(round: number) {
  expandedRound.value = expandedRound.value === round ? null : round
}

function isExpanded(round: number) {
  return expandedRound.value === round
}

function nameFromUserId(uid: number): string {
  const p = players.value.find((x: any) => Number(x?.user_id) === Number(uid))
  return p?.name ?? 'Joueur'
}

function formatGame(g: string): string {
  if (g === 'kcdle') return 'KCDLE'
  if (g === 'lecdle') return 'LECDLE'
  if (g === 'lfldle') return 'LFLDLE'
  return g.toUpperCase()
}

function formatRoundType(rt: string): string {
  if (rt === 'classic') return 'Classic'
  if (rt === 'draft') return 'Draft'
  if (rt === 'locked_infos') return 'Infos verrouillées'
  if (rt === 'whois') return 'Whois'
  if (rt === 'reveal_race') return 'Reveal race'
  if (rt === 'reveal_face') return 'Reveal face'
  return rt
}

function formatDurationMs(ms: number): string {
  const s = Math.max(0, Math.floor(ms / 1000))
  const mm = Math.floor(s / 60)
  const ss = s % 60
  return `${mm}:${String(ss).padStart(2, '0')}`
}

const matchDuration = computed(() => {
  if (!startedAt.value || !finishedAt.value) return null
  const a = new Date(startedAt.value)
  const b = new Date(finishedAt.value)
  if (Number.isNaN(a.getTime()) || Number.isNaN(b.getTime())) return null
  const ms = b.getTime() - a.getTime()
  if (!Number.isFinite(ms) || ms < 0) return null
  return formatDurationMs(ms)
})

const roundHistory = computed(() => {
  const rh = Array.isArray(match.value?.round_history) ? match.value.round_history : []
  return rh
    .map((x: any) => ({
      round: Number(x?.round ?? 0),
      round_type: String(x?.round_type ?? ''),
      winner_user_id: Number(x?.winner_user_id ?? 0),
    }))
    .filter((x: any) => x.round > 0 && x.round_type.length > 0 && x.winner_user_id > 0)
})

const recapRows = computed(() => {
  const lId = Number(left.value?.user_id ?? 0)
  const rId = Number(right.value?.user_id ?? 0)

  let lScore = 0
  let rScore = 0

  return roundHistory.value.map((r: any) => {
    if (r.winner_user_id === lId) lScore += 1
    if (r.winner_user_id === rId) rScore += 1

    return {
      round: r.round,
      roundTypeRaw: r.round_type,
      roundTypeLabel: formatRoundType(r.round_type),
      winnerUserId: r.winner_user_id,
      winnerName: nameFromUserId(r.winner_user_id),
      scoreLabel: `${lScore} - ${rScore}`,
    }
  })
})

const roundsWonLeft = computed(() => {
  const id = Number(left.value?.user_id ?? 0)
  if (!id) return 0
  return roundHistory.value.filter((r: any) => Number(r.winner_user_id) === id).length
})

const roundsWonRight = computed(() => {
  const id = Number(right.value?.user_id ?? 0)
  if (!id) return 0
  return roundHistory.value.filter((r: any) => Number(r.winner_user_id) === id).length
})

const scoreLeft = computed(() => Number(left.value?.points ?? 0))
const scoreRight = computed(() => Number(right.value?.points ?? 0))
const leftName = computed(() => (left.value?.name ?? 'J1') as string)
const rightName = computed(() => (right.value?.name ?? 'J2') as string)

const leftId = computed(() => Number(left.value?.user_id ?? 0))
const rightId = computed(() => Number(right.value?.user_id ?? 0))

const leftIsWinner = computed(() => winnerUserId.value > 0 && leftId.value > 0 && winnerUserId.value === leftId.value)
const rightIsWinner = computed(() => winnerUserId.value > 0 && rightId.value > 0 && winnerUserId.value === rightId.value)

const resultTitle = computed(() => {
  if (winnerUserId.value <= 0) return 'Match terminé'
  if (currentUserId.value > 0 && winnerUserId.value === currentUserId.value) return 'Victoire'
  if (currentUserId.value > 0 && winnerUserId.value !== currentUserId.value) return 'Défaite'
  return 'Match terminé'
})

const resultSubtitle = computed(() => {
  if (endedReason.value === 'leave' || endedReason.value === 'afk') {
    return 'Fin par abandon'
  }

  if (endedReason.value === 'points') {
    return 'Fin au score'
  }

  return 'Match terminé'
})

async function load() {
  if (!matchId.value) return
  loading.value = true
  error.value = null

  try {
    const m = await pvpGetMatch(matchId.value)
    match.value = m

    if (m?.status !== 'finished') {
      await router.replace({ name: 'pvp_match_play', params: { matchId: matchId.value } })
      return
    }

    if (pvp.isInMatch && pvp.matchId === matchId.value) {
      pvp.clearMatch()
    }
  } catch {
    error.value = 'Impossible de charger la fin de match.'
  } finally {
    loading.value = false
  }
}

function backToPvp() {
  router.push({ name: 'pvp' })
}

function replay() {
  if (!game.value || !bestOf.value) {
    flash.error('Impossible de relancer une file sur ce match.', 'PvP')
    return
  }

  pvp.clearMatch()
  pvp.setQueued(game.value, bestOf.value)
  flash.info(`File relancée sur ${formatGame(game.value)} (BO${bestOf.value}).`, 'PvP', 3000)
  router.push({ name: 'pvp' })
}

onMounted(async () => {
  if (!matchId.value) {
    flash.error('Match introuvable.', 'PvP')
    await router.push({ name: 'pvp' })
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
        <section class="card hero">
          <div class="hero-top">
            <div class="hero-title">{{ resultTitle }}</div>
            <div class="hero-subtitle">{{ resultSubtitle }}</div>
          </div>

          <div class="scoreline" role="group" aria-label="Score final">
            <div class="pname" :class="{ win: leftIsWinner, lose: !leftIsWinner && winnerUserId > 0 }" :title="leftName">
              {{ leftName }}
            </div>

            <div class="pscore" :class="{ win: leftIsWinner, lose: !leftIsWinner && winnerUserId > 0 }">
              {{ scoreLeft }}
            </div>

            <div class="dash">-</div>

            <div class="pscore" :class="{ win: rightIsWinner, lose: !rightIsWinner && winnerUserId > 0 }">
              {{ scoreRight }}
            </div>

            <div class="pname" :class="{ win: rightIsWinner, lose: !rightIsWinner && winnerUserId > 0 }" :title="rightName">
              {{ rightName }}
            </div>
          </div>

          <div class="stats">
            <div class="stat">
              <div class="k">Jeu</div>
              <div class="v">{{ formatGame(match.game) }}</div>
            </div>
            <div class="stat">
              <div class="k">Format</div>
              <div class="v">BO{{ match.best_of }}</div>
            </div>
            <div class="stat">
              <div class="k">Rounds gagnés</div>
              <div class="v">{{ roundsWonLeft }} - {{ roundsWonRight }}</div>
            </div>
            <div class="stat">
              <div class="k">Durée</div>
              <div class="v">{{ matchDuration ?? '—' }}</div>
            </div>
            <div class="stat">
              <div class="k">Rounds joués</div>
              <div class="v">{{ roundHistory.length }}</div>
            </div>
          </div>

          <div class="actions">
            <button class="btn primary" type="button" @click="replay">Rejouer</button>
            <button class="btn ghost" type="button" @click="backToPvp">Retour PvP</button>
          </div>
        </section>

        <section v-if="recapRows.length > 0" class="card">
          <div class="card-title">Récap des rounds</div>

          <div class="recap">
            <button
              v-for="r in recapRows"
              :key="`recap-${r.round}`"
              class="recap-row"
              type="button"
              :aria-expanded="isExpanded(r.round)"
              @click="toggleRound(r.round)"
            >
              <div class="row-top">
                <div class="leftcol">
                  <div class="line1">Round {{ r.round }} — {{ r.roundTypeLabel }}</div>
                  <div class="line2">Gagné par <strong>{{ r.winnerName }}</strong></div>
                </div>

                <div class="rightcol">
                  <div class="score-pill" :title="`Score après round ${r.round}`">{{ r.scoreLabel }}</div>
                  <div class="chev" :class="{ open: isExpanded(r.round) }">▾</div>
                </div>
              </div>

              <div v-if="isExpanded(r.round)" class="row-more">
                <div class="more-grid">
                  <div class="more">
                    <div class="mk">Type</div>
                    <div class="mv">{{ r.roundTypeLabel }}</div>
                  </div>
                  <div class="more">
                    <div class="mk">Score après</div>
                    <div class="mv">{{ r.scoreLabel }}</div>
                  </div>
                  <div class="more">
                    <div class="mk">Gagnant</div>
                    <div class="mv">{{ r.winnerName }}</div>
                  </div>
                  <div class="more">
                    <div class="mk">Clé</div>
                    <div class="mv">{{ r.roundTypeRaw }}</div>
                  </div>
                </div>
              </div>
            </button>
          </div>
        </section>
      </div>
    </template>
  </div>
</template>

<style scoped>
.page {
  min-height: 100vh;
  padding: 18px 10px 24px;
  display: flex;
  flex-direction: column;
  align-items: center;
  color: #f3f3f3;
  background: radial-gradient(circle at top, #20263a 0, #05060a 75%);
}

.wrap {
  width: 100%;
  max-width: 900px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.state {
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

.card {
  max-width: 900px;
  background: rgba(6, 8, 18, 0.92);
  border-radius: 14px;
  padding: 14px 10px 16px;
  border: 1px solid rgba(255, 255, 255, 0.06);
}

@media (min-width: 520px) {
  .page { padding: 20px 12px 28px; }
  .card { padding: 16px 12px 18px; }
}

.hero {
  padding-top: 16px;
}

.hero-top {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.hero-title {
  font-size: 1.3rem;
  font-weight: 900;
  letter-spacing: 0.02em;
}

@media (min-width: 680px) {
  .hero-title { font-size: 1.55rem; }
}

.hero-subtitle {
  opacity: 0.85;
}

.scoreline {
  margin-top: 14px;
  display: flex;
  align-items: baseline;
  justify-content: center;
  gap: 10px;
  flex-wrap: nowrap;
}

.pname {
  font-size: 0.95rem;
  opacity: 0.95;
  max-width: 34vw;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  transition: opacity 120ms ease, filter 120ms ease;
}

.pscore {
  font-size: 1.5rem;
  font-weight: 950;
  min-width: 22px;
  text-align: center;
  transition: opacity 120ms ease, filter 120ms ease;
}

.pname.win,
.pscore.win {
  opacity: 1;
  filter: brightness(1.18);
}

.pname.lose,
.pscore.lose {
  opacity: 0.7;
}

.dash {
  opacity: 0.85;
  font-weight: 900;
}

.stats {
  margin-top: 14px;
  display: grid;
  grid-template-columns: 1fr;
  gap: 10px;
}

@media (min-width: 620px) {
  .stats { grid-template-columns: 1fr 1fr; }
}

@media (min-width: 980px) {
  .stats { grid-template-columns: 1fr 1fr 1fr; }
}

.stat {
  padding: 10px 10px;
  border-radius: 12px;
  background: rgba(255, 255, 255, 0.04);
  border: 1px solid rgba(255, 255, 255, 0.06);
}

.k {
  font-size: 0.82rem;
  opacity: 0.8;
  text-transform: uppercase;
  letter-spacing: 0.12em;
}

.v {
  margin-top: 6px;
  font-weight: 900;
  word-break: break-word;
}

.actions {
  margin-top: 14px;
  display: flex;
  gap: 10px;
  flex-direction: column;
}

@media (min-width: 520px) {
  .actions { flex-direction: row; }
}

.btn {
  border: 1px solid transparent;
  cursor: pointer;
  padding: 11px 12px;
  border-radius: 12px;
  font-weight: 900;
  transition: transform 120ms ease, background 120ms ease, border-color 120ms ease, filter 120ms ease;
}

.btn.primary {
  color: #0b1022;
  background: #e7e7e7;
}

.btn.primary:hover {
  filter: brightness(0.94);
}

.btn.ghost {
  background: rgba(255, 255, 255, 0.08);
  color: #f3f3f3;
  border-color: rgba(255, 255, 255, 0.14);
}

.btn.ghost:hover {
  background: rgba(255, 255, 255, 0.12);
  border-color: rgba(255, 255, 255, 0.22);
}

.btn:active {
  transform: translateY(1px);
}

.card-title {
  font-size: 0.9rem;
  opacity: 0.85;
  margin-bottom: 10px;
  text-transform: uppercase;
  letter-spacing: 0.12em;
}

.recap {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.recap-row {
  text-align: left;
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 10px 10px;
  border-radius: 12px;
  background: rgba(255, 255, 255, 0.04);
  border: 1px solid rgba(255, 255, 255, 0.06);
  cursor: pointer;
  color: #ffffff;
}

.recap-row:hover {
  background: rgba(255, 255, 255, 0.055);
}

.recap-row:active {
  transform: translateY(1px);
}

.row-top {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 10px;
}

.leftcol {
  display: flex;
  flex-direction: column;
  gap: 4px;
  min-width: 0;
  flex: 1 1 auto;
}

.line1 {
  font-weight: 900;
  overflow: hidden;
  text-overflow: ellipsis;
  color: #ffffff;
}

.line2 {
  opacity: 0.9;
  overflow: hidden;
  text-overflow: ellipsis;
  color: #ffffff;
}

.rightcol {
  display: flex;
  align-items: center;
  gap: 8px;
  flex: 0 0 auto;
}

.score-pill {
  font-weight: 950;
  padding: 6px 10px;
  border-radius: 999px;
  background: rgba(0, 0, 0, 0.25);
  border: 1px solid rgba(255, 255, 255, 0.10);
  white-space: nowrap;
  color: #ffffff;
}

.chev {
  opacity: 0.9;
  transition: transform 120ms ease;
  transform: rotate(0deg);
  line-height: 1;
  padding-top: 2px;
  color: #ffffff;
}

.chev.open {
  transform: rotate(180deg);
}

.row-more {
  padding-top: 4px;
  color: #ffffff;
}

.more-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 8px;
}

@media (min-width: 620px) {
  .more-grid { grid-template-columns: 1fr 1fr; }
}

.more {
  padding: 10px 10px;
  border-radius: 12px;
  background: rgba(0, 0, 0, 0.18);
  border: 1px solid rgba(255, 255, 255, 0.06);
  color: #ffffff;
}

.mk {
  font-size: 0.78rem;
  opacity: 0.85;
  text-transform: uppercase;
  letter-spacing: 0.12em;
  color: #ffffff;
}

.mv {
  margin-top: 6px;
  font-weight: 900;
  word-break: break-word;
  color: #ffffff;
}

@media (max-width: 360px) {
  .score-pill {
    padding: 6px 8px;
    font-size: 0.9rem;
  }
}
</style>
