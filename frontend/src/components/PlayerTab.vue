<script setup lang="ts">
import { computed, ref } from 'vue'
import SimpleImg from '@/components/SimpleImg.vue'

type GameCode = 'kcdle' | 'lecdle' | 'lfldle'

interface GuessEntry {
  correct: boolean
  comparison: {
    correct: boolean
    fields: Record<string, number | null>
  }
  stats: any
  player: any
}

const props = defineProps<{
  game: GameCode
  guesses: GuessEntry[]
}>()

const dleCode = computed(() => props.game.toUpperCase())

interface InfoCol {
  className: string
  text: string
  cmpField: string
  type: 'eq' | 'cmp'
}

const emit = defineEmits<{
  (e: 'endAnimaiton'): void
}>()

const lastEndAnimationEmitSignature = ref<string | null>(null)

const infoBar = computed<InfoCol[]>(() => {
  if (props.game === 'kcdle') {
    return [
      { className: 'nationalite', text: 'Nationalité', cmpField: 'country',             type: 'eq'  },
      { className: 'age',         text: 'Âge',         cmpField: 'birthday',            type: 'cmp' },
      { className: 'jeu',         text: 'Jeu',         cmpField: 'game',                type: 'eq'  },
      { className: 'arrivee',     text: 'Arrivée',     cmpField: 'first_official_year', type: 'cmp' },
      { className: 'titres',      text: 'Titre(s)',    cmpField: 'trophies',            type: 'cmp' },
      { className: 'avantkc',     text: 'AvantKC',     cmpField: 'previous_team',       type: 'eq'  },
      { className: 'maintenant',  text: 'Maintenant',  cmpField: 'current_team',        type: 'eq'  },
      { className: 'role',        text: 'Rôle',        cmpField: 'role',                type: 'eq'  },
      { className: 'joueur',      text: 'Joueur',      cmpField: 'slug',                type: 'eq'  },
    ]
  }

  return [
    { className: 'nationalite', text: 'Nationalité', cmpField: 'country',  type: 'eq'  },
    { className: 'age',         text: 'Âge',         cmpField: 'birthday', type: 'cmp' },
    { className: 'equipe',      text: 'Équipe',      cmpField: 'team',     type: 'eq'  },
    { className: 'role',        text: 'Rôle',        cmpField: 'lol_role', type: 'eq'  },
    { className: 'joueur',      text: 'Joueur',      cmpField: 'slug',     type: 'eq'  },
  ]
})

function verificationClass(cmp: number | null | undefined): string {
  const ok = cmp === 1
  return `${dleCode.value}_guess_${ok}`
}

function arrowOption(cmp: number | null | undefined): number {
  if (cmp === null || cmp === undefined) return 0
  if (cmp === 1) return 0
  if (cmp === 0) return -1
  if (cmp === -1) return 1
  return 0
}

function rotateStyle(option: number) {
  return { rotate: `${90 * option}deg` }
}

function blackTextStyle(option: number) {
  if (option === 0) return { color: 'black' }
  return {}
}

function fadeStyle(infoIndex: number) {
  const delay = infoIndex * 0.4
  return {
    animationDelay: `${delay}s`,
  }
}

function shouldEmitEndAnimation(infoIndex: number, guessIndex: number): boolean {
  return infoIndex === infoBar.value.length - 1 && guessIndex === 0
}

function onCellAnimationEnd(e: AnimationEvent, infoIndex: number, guessIndex: number, guess: GuessEntry) {
  if (e.target !== e.currentTarget) return
  if (e.animationName !== 'fade-in') return
  if (!shouldEmitEndAnimation(infoIndex, guessIndex)) return

  const playerId = guess?.player?.id ?? ''
  const signature = `${props.game}-${playerId}-${props.guesses.length}`

  if (signature === lastEndAnimationEmitSignature.value) return
  lastEndAnimationEmitSignature.value = signature

  emit('endAnimaiton')
}

function computeAge(dateStr?: string | null): number | null {
  if (!dateStr) return null
  const birth = new Date(dateStr)
  if (Number.isNaN(birth.getTime())) return null
  const today = new Date()
  let age = today.getFullYear() - birth.getFullYear()
  const m = today.getMonth() - birth.getMonth()
  if (m < 0 || (m === 0 && today.getDate() < birth.getDate())) age--
  return age
}

function displayValue(col: InfoCol, guess: GuessEntry): string {
  const wrapper = guess.player
  const p = wrapper?.player

  if (!wrapper || !p) return ''

  if (props.game === 'kcdle') {
    switch (col.cmpField) {
      case 'slug':
        return p.display_name ?? p.slug ?? ''
      case 'country':
        return p.country_code ?? ''
      case 'birthday': {
        const age = computeAge(p.birthdate)
        return age !== null ? `${age}` : ''
      }
      case 'game':
        return wrapper.game?.name ?? wrapper.game?.code ?? ''
      case 'first_official_year':
        return String(wrapper.first_official_year ?? '')
      case 'trophies':
        return String(wrapper.trophies_count ?? '')
      case 'previous_team':
        return wrapper.previous_team?.short_name ?? wrapper.previous_team?.display_name ?? wrapper.previous_team?.slug ?? ''
      case 'current_team':
        return wrapper.current_team?.short_name ?? wrapper.current_team?.display_name ?? wrapper.current_team?.slug ?? ''
      case 'role':
        return p.role?.label ?? p.role?.code ?? ''
    }
  } else {
    switch (col.cmpField) {
      case 'slug':
        return p.display_name ?? p.slug ?? ''
      case 'country':
        return p.country_code ?? ''
      case 'birthday': {
        const age = computeAge(p.birthdate)
        return age !== null ? `${age}` : ''
      }
      case 'team':
        return wrapper.team?.short_name ?? wrapper.team?.display_name ?? ''
      case 'lol_role':
        return wrapper.lol_role ?? ''
    }
  }

  return ''
}

function isImageCell(col: InfoCol): boolean {
  if (props.game === 'kcdle') {
    return ['country', 'game', 'previous_team', 'current_team', 'slug'].includes(col.cmpField)
  }
  return ['country', 'team', 'lol_role', 'slug'].includes(col.cmpField)
}

function getImageUrl(col: InfoCol, guess: GuessEntry): string | null {
  const wrapper = guess.player
  const p = wrapper?.player

  if (!wrapper || !p) return null

  if (props.game === 'kcdle') {
    switch (col.cmpField) {
      case 'slug':
        return p.image_url ?? null
      case 'game':
        return wrapper.game?.logo_url ?? null
      case 'previous_team':
        return wrapper.previous_team ? wrapper.previous_team.logo_url ?? null : wrapper.team_default_url
      case 'current_team':
        return wrapper.current_team ? wrapper.current_team.logo_url ?? null : wrapper.team_default_url
      case 'country' :
        return p.country ? p.country.flag_url ?? null : p.country_default_url
    }
  } else {
    switch (col.cmpField) {
      case 'slug':
        return p.image_url ?? null
      case 'team':
        return wrapper.team ? wrapper.team.logo_url ?? null : wrapper.team_default_url
      case 'country' :
        return p.country ? p.country.flag_url ?? null : p.country_default_url
      case 'lol_role':
        return wrapper.lol_role_url ?? ''
    }
  }

  return null
}

function imageClass(col: InfoCol): (string | Record<string, boolean>)[] {
  const base = ['imgInfoJoueur']
  if (col.cmpField === 'country') {
    return [...base, 'paysImg']
  }
  if (col.cmpField === 'game') {
    return [...base, 'jeuImg']
  }
  if (col.cmpField === 'previous_team' || col.cmpField === 'current_team' || col.cmpField === 'team') {
    return [...base, 'jeuImg']
  }
  if (col.cmpField === 'slug') {
    return [...base, 'teteImg']
  }
  if (col.cmpField === 'role' || col.cmpField === 'lol_role') {
    return [...base, 'roleImg']
  }
  return base
}

function textStyle(col: InfoCol, guess: GuessEntry) {
  const cmp = guess.comparison?.fields?.[col.cmpField]
  if (cmp === 1) {
    return { color: 'black' }
  }
  return {}
}

function textClass(col: InfoCol): string | null {
  if (col.className === 'role') {
    return 'roleText'
  }
  return null
}
</script>

<template>
  <div class="theBody">
    <div class="playerTabScroll">
      <div class="infoNom">
        <div
          v-for="(info, infoIndex) in infoBar"
          :key="info.className"
          class="divInfo"
        >
          <div class="divText">
            {{ info.text }}
          </div>
          <hr>
          <div :class="info.className">
            <div
              v-for="(guess, guessIndex) in guesses"
              :key="(guess.player?.id ?? '') + '-' + info.className + '-' + props.game"
              :class="[
                'divInfoJoueur',
                verificationClass(guess.comparison?.fields?.[info.cmpField]),
                'fade-in',
              ]"
              :style="fadeStyle(infoIndex)"
              @animationend="onCellAnimationEnd($event, infoIndex, guessIndex, guess)"
            >
              <template v-if="info.type === 'cmp'">
                <div class="ageContainer">
                  <SimpleImg
                    v-if="arrowOption(guess.comparison?.fields?.[info.cmpField]) !== 0"
                    class="arrow"
                    img="arrow-age.png"
                    alt=""
                    :style="rotateStyle(arrowOption(guess.comparison?.fields?.[info.cmpField]))"
                  />
                  <p
                    class="ageText"
                    :style="blackTextStyle(arrowOption(guess.comparison?.fields?.[info.cmpField]))"
                  >
                    {{ displayValue(info, guess) }}
                  </p>
                </div>
              </template>

              <template v-else-if="isImageCell(info)">
                <SimpleImg
                  v-if="getImageUrl(info, guess)"
                  :class="imageClass(info)"
                  :img="getImageUrl(info, guess)!"
                  :alt="displayValue(info, guess)"
                />
                <span
                  v-else
                  :class="textClass(info)"
                  :style="textStyle(info, guess)"
                >
                  {{ displayValue(info, guess) }}
                </span>
              </template>

              <template v-else>
                <span
                  :class="textClass(info)"
                  :style="textStyle(info, guess)"
                >
                  {{ displayValue(info, guess) }}
                </span>
              </template>

            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
