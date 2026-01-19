<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'

type RoundHelpItem = {
  type: string
  title: string
  lines: string[]
}

const props = defineProps<{
  buttonLabel?: string
  buttonVariant?: 'default' | 'ghost'
}>()

const isOpen = ref(false)

const buttonText = computed(() => props.buttonLabel ?? 'Règles des rounds')
const buttonVariant = computed(() => props.buttonVariant ?? 'default')

const rounds = computed<RoundHelpItem[]>(() => [
  {
    type: 'classic',
    title: 'Classique',
    lines: [
      'Même principe qu’un DLE classique : tu dois trouver le joueur secret.',
      'Les deux joueurs jouent en parallèle sur le même joueur secret.',
      'Quand vous avez tous les deux trouvé, le vainqueur est départagé selon vos performances (vitesse / nombre d’essais).',
    ],
  },
  {
    type: 'locked_infos',
    title: 'Informations limitées',
    lines: [
      'Même objectif : trouver le joueur secret.',
      'Seules 2 informations sont révélées au départ ; le reste est masqué.',
      'Le round se gagne comme en classique : le meilleur départage s’applique quand les deux ont trouvé.',
    ],
  },
  {
    type: 'whois',
    title: 'Qui est-ce ?',
    lines: [
      'Tour par tour : un joueur pose une question (sur une info du joueur) puis l’autre joue.',
      'La liste des candidats se réduit selon la réponse (vrai/faux).',
      'À ton tour, tu peux aussi tenter un guess : si c’est faux, le candidat est éliminé.',
      'Le premier à deviner le joueur secret gagne le round.',
    ],
  },
  {
    type: 'draft',
    title: 'Draft',
    lines: [
      'Phase de draft au début : un joueur est désigné pour choisir qui pick en premier.',
      'Chaque joueur choisit 2 indices à révéler (ordre : A → B → B → A).',
      'Ensuite, chacun joue un round “classique” avec uniquement ses 2 indices révélés.',
      'Le vainqueur est départagé comme en classique quand les deux ont trouvé.',
    ],
  },
  {
    type: 'reveal_race',
    title: 'Course contre la montre',
    lines: [
      'Les indices se révèlent automatiquement au fil du temps.',
      'Un nouvel indice apparaît toutes les ~8 secondes.',
      'Si tu te trompes, tu es bloqué ~5 secondes avant de pouvoir retenter.',
      'Le premier à trouver le joueur secret gagne immédiatement le round.',
    ],
  },
])

function open() {
  isOpen.value = true
}

function close() {
  isOpen.value = false
}

function onKeydown(e: KeyboardEvent) {
  if (!isOpen.value) return
  if (e.key === 'Escape') close()
}

watch(
  () => isOpen.value,
  (v) => {
    if (typeof document === 'undefined') return
    document.body.style.overflow = v ? 'hidden' : ''
  },
)

onMounted(() => window.addEventListener('keydown', onKeydown))
onBeforeUnmount(() => {
  window.removeEventListener('keydown', onKeydown)
  if (typeof document !== 'undefined') document.body.style.overflow = ''
})
</script>

<template>
  <button
    type="button"
    class="help-btn"
    :class="{ 'help-btn--ghost': buttonVariant === 'ghost' }"
    @click="open"
  >
    <span class="help-btn__icon" aria-hidden="true">i</span>
    <span class="help-btn__text">{{ buttonText }}</span>
  </button>

  <Teleport to="body">
    <div
      v-if="isOpen"
      class="overlay"
      role="dialog"
      aria-modal="true"
      aria-label="Règles des rounds PvP"
      @click.self="close"
    >
      <div class="panel">
        <div class="panel-head">
          <div class="panel-title">Règles des rounds PvP</div>
          <button type="button" class="close-btn" aria-label="Fermer" @click="close">×</button>
        </div>

        <div class="panel-body">
          <div class="panel-hint">
            Chaque manche du PvP tire un type de round dans le pool. Le but reste toujours de trouver le joueur secret,
            mais les contraintes changent selon le round.
          </div>

          <div class="grid">
            <div v-for="r in rounds" :key="r.type" class="card">
              <div class="card-title">{{ r.title }}</div>
              <ul class="card-list">
                <li v-for="(l, i) in r.lines" :key="i">{{ l }}</li>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<style scoped>
.overlay,
.overlay * {
  box-sizing: border-box;
}

.help-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 9px 12px;
  border-radius: 999px;
  border: 1px solid rgba(255, 255, 255, 0.14);
  background: rgba(255, 255, 255, 0.08);
  color: rgba(243, 244, 246, 0.96);
  cursor: pointer;
  transition: transform 120ms ease, background 120ms ease;
  white-space: nowrap;
}

.help-btn:hover {
  transform: translateY(-1px);
  background: rgba(255, 255, 255, 0.14);
}

.help-btn--ghost {
  background: rgba(255, 255, 255, 0.05);
}

.help-btn__icon {
  width: 20px;
  height: 20px;
  border-radius: 999px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border: 1px solid rgba(255, 255, 255, 0.25);
  font-weight: 800;
  font-size: 0.85rem;
  line-height: 1;
  opacity: 0.95;
}

.help-btn__text {
  font-size: 0.92rem;
  opacity: 0.95;
}

.overlay {
  position: fixed;
  inset: 0;
  z-index: 9999;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px 12px;
  background: rgba(0, 0, 0, 0.55);
  backdrop-filter: blur(6px);
  overflow: hidden;
}

.panel {
  width: min(920px, 92vw);
  max-width: calc(100vw - 24px);
  max-height: calc(100vh - 24px);
  border-radius: 18px;
  border: 1px solid rgba(255, 255, 255, 0.10);
  background: radial-gradient(circle at top, #20263a, rgba(10, 12, 22, 0.92) 65%);
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.65);
  overflow: hidden;
  display: flex;
  flex-direction: column;
}

.panel-head {
  display: grid;
  grid-template-columns: 1fr auto;
  align-items: center;
  gap: 10px;
  padding: 14px 14px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}

.panel-title {
  font-size: 1.05rem;
  font-weight: 800;
  color: rgba(249, 250, 251, 0.98);
}

.close-btn {
  width: 34px;
  height: 34px;
  border-radius: 12px;
  border: 1px solid rgba(255, 255, 255, 0.14);
  background: rgba(255, 255, 255, 0.06);
  color: rgba(243, 244, 246, 0.96);
  cursor: pointer;
  display: grid;
  place-items: center;
  font-size: 1.25rem;
  line-height: 1;
  padding: 0;
  transition: background 120ms ease, transform 120ms ease;
}

.close-btn:hover {
  background: rgba(255, 255, 255, 0.12);
  transform: translateY(-1px);
}

.panel-body {
  padding: 12px 14px 14px;
  overflow-y: auto;
  overscroll-behavior: contain;
}

.panel-hint {
  font-size: 0.92rem;
  line-height: 1.45;
  color: rgba(229, 231, 235, 0.88);
}

.grid {
  margin-top: 12px;
  display: grid;
  grid-template-columns: 1fr;
  gap: 10px;
}

.card {
  padding: 12px;
  border-radius: 14px;
  border: 1px solid rgba(255, 255, 255, 0.08);
  background: rgba(255, 255, 255, 0.04);
}

.card-title {
  font-weight: 800;
  color: rgba(249, 250, 251, 0.98);
  margin-bottom: 6px;
}

.card-list {
  margin: 0;
  padding-left: 18px;
  color: rgba(229, 231, 235, 0.90);
  line-height: 1.35;
}

.card-list li {
  margin: 6px 0;
}

@media (min-width: 860px) {
  .grid {
    grid-template-columns: 1fr 1fr;
  }
}

@media (max-width: 420px) {
  .help-btn__text {
    display: none;
  }

  .panel-head {
    padding: 12px 12px;
  }

  .panel-body {
    padding: 10px 12px 12px;
  }
}
</style>
