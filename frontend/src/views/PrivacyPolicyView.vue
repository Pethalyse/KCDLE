<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/api'

interface PolicySection {
  id: string
  title: string
  paragraphs: string[]
}

const router = useRouter()

const title = ref('')
const lastUpdated = ref('')
const sections = ref<PolicySection[]>([])
const loading = ref(true)
const error = ref<string | null>(null)

onMounted(async () => {
  try {
    const { data } = await api.get('/privacy-policy')
    title.value = data.title
    lastUpdated.value = data.last_updated
    sections.value = data.sections ?? []
  } catch (e: any) {
    console.error(e)
    error.value = "Impossible de charger la politique de confidentialité."
  } finally {
    loading.value = false
  }
})

function goHome() {
  router.push({ name: 'home' })
}
</script>

<template>
  <div class="privacy-page">
    <header class="privacy-header">
      <div>
        <h1>{{ title || 'Politique de confidentialité' }}</h1>
        <p v-if="lastUpdated" class="privacy-last-updated">
          Dernière mise à jour : {{ lastUpdated }}
        </p>
      </div>
    </header>

    <main class="privacy-content">
      <div v-if="loading">
        Chargement de la politique de confidentialité...
      </div>
      <div v-else-if="error">
        {{ error }}
      </div>
      <div v-else>
        <section
          v-for="section in sections"
          :key="section.id"
          class="privacy-section"
        >
          <h2>{{ section.title }}</h2>
          <p
            v-for="(p, idx) in section.paragraphs"
            :key="idx"
          >
            {{ p }}
          </p>
        </section>
      </div>
    </main>
  </div>
</template>

<style scoped>
.privacy-page {
  min-height: 100vh;
  padding: 20px;
  color: #f0f0f0;
  background: radial-gradient(circle at top, #20263a 0, #05060a 55%);
  font-size: 0.95rem;
}

.privacy-header {
  display: flex;
  justify-content: space-between;
  gap: 10px;
  margin-bottom: 18px;
}

.privacy-header h1 {
  margin: 0;
  font-size: 1.6rem;
}

.privacy-last-updated {
  margin: 4px 0 0;
  font-size: 0.85rem;
  opacity: 0.8;
}

.privacy-back {
  border: none;
  background: #00a6ff;
  color: #fff;
  padding: 6px 10px;
  border-radius: 4px;
  cursor: pointer;
  font-size: 0.9rem;
}

.privacy-back:hover {
  filter: brightness(1.1);
}

.privacy-content {
  max-width: 900px;
  margin: 0 auto;
}

.privacy-content div{
  text-align: justify;
}

.privacy-section {
  margin-bottom: 22px;
}

.privacy-section h2 {
  margin-bottom: 8px;
  font-size: 1.1rem;
}
</style>
