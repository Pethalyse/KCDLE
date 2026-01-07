<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/api'
import SimpleImg from '@/components/SimpleImg.vue'

interface Contributor {
  name: string
  slug?: string
  twitter: string
  role: string
  description?: string
  avatar?: string
}

const router = useRouter()

const contributors = ref<Contributor[]>([])
const technologies = ref([])
const disclaimers = ref([])

const loading = ref(true)
const error = ref<string | null>(null)

onMounted(async () => {
  try {
    const { data } = await api.get('/credits')
    contributors.value = data.contributors
    technologies.value = data.technologies
    disclaimers.value = data.disclaimers
  } catch (e: any) {
    console.error(e)
    error.value = 'Impossible de charger les crédits.'
  } finally {
    loading.value = false
  }
})

function twitterUrl(twitter: string) {
  const handle = twitter.startsWith('@') ? twitter.slice(1) : twitter
  return `https://twitter.com/${handle}`
}

function goHome() {
  router.push({ name: 'home' })
}
</script>

<template>
  <div class="credits-page">
    <header class="credits-header">
      <h1>Crédits & Remerciements</h1>
    </header>

    <main class="credits-content">
      <section class="credits-section">
        <h2>Équipe & contributions</h2>

        <p>
          Le KCDLE est un projet de fans, développé sur le temps libre. Merci à toutes les personnes qui ont contribué à le faire vivre.
        </p>

        <div v-if="loading">
          Chargement des crédits...
        </div>
        <div v-else-if="error">
          {{ error }}
        </div>
        <ul
          v-else
          class="credits-people"
        >
          <li
            v-for="c in contributors"
            :key="c.slug ?? c.twitter"
          >
            <SimpleImg
              v-if="c.avatar"
              class="credits-avatar"
              :img="c.avatar"
              :alt="c.name"
            />
            <div class="credits-person-header">
              <div>
                <h3>{{ c.name }}</h3>
                <p class="credits-role">{{ c.role }}</p>
              </div>
            </div>

            <p
              v-if="c.description"
              class="credits-details"
            >
              {{ c.description }}
            </p>

            <p class="credits-twitter">
              Twitter :
              <a
                :href="twitterUrl(c.twitter)"
                target="_blank"
                rel="noopener noreferrer"
              >
                {{ c.twitter.startsWith('@') ? c.twitter : '@' + c.twitter }}
              </a>
            </p>
          </li>
        </ul>
      </section>

      <section class="credits-section">
        <h2>Technologies utilisées</h2>
        <ul class="credits-tech-list">
          <li
            v-for="tech in technologies"
            :key="tech"
          >
            {{ tech }}
          </li>
        </ul>
      </section>

      <section class="credits-section">
        <h2>Mentions & disclaimers</h2>
        <ul class="credits-disclaimers">
          <li v-for="d in disclaimers"
              :key="d"
          >
            {{ d }}
          </li>
        </ul>
      </section>
    </main>
  </div>
</template>

<style scoped>
.credits-page {
  padding: 20px;
  color: #f0f0f0;
  background: radial-gradient(circle at top, #20263a 0, #05060a 75%);
  font-size: 0.95rem;
}

.credits-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 18px;
}

.credits-back {
  border: none;
  background: #00a6ff;
  color: #fff;
  padding: 6px 10px;
  border-radius: 4px;
  cursor: pointer;
}

.credits-content {
  max-width: 900px;
  margin: 0 auto;
}

.credits-section {
  margin-bottom: 24px;
}

.credits-people {
  list-style: none;
  padding: 0;
  margin: 12px 0 0;
  display: grid;
  gap: 10px;
}

.credits-people li {
  padding: 20px 10px;
  background: rgba(0, 0, 0, 0.35);
  border-radius: 6px;
  display: flex;
  align-items: center;
  flex-direction: column;
}

.credits-person-header {
  display: flex;
  align-items: center;
  gap: 25px;
}

.credits-avatar {
  width: 48px;
  height: 48px;
  border-radius: 50%;
}

.credits-role {
  margin: 0;
  font-weight: 600;
}

.credits-details {
  margin: 4px 0;
  opacity: 0.9;
}

.credits-twitter a {
  color: #00a6ff;
}

.credits-tech-list,
.credits-disclaimers {
  list-style: none;
  padding-left: 18px;
  margin-top: 8px;
}
</style>
