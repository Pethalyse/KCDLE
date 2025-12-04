<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/api'

interface LegalBlock {
  title: string
  paragraphs: string[]
}

const router = useRouter()

const title = ref('Mentions légales')
const lastUpdated = ref('')
const blocks = ref<LegalBlock[]>([])
const loading = ref(true)
const error = ref<string | null>(null)

onMounted(async () => {
  try {
    const { data } = await api.get('/legal')
    title.value = data.title ?? 'Mentions légales'
    lastUpdated.value = data.last_updated ?? ''
    // On recompose une liste ordonnée des sections
    blocks.value = [
      data.editor,
      data.host,
      data.intellectual_property,
      data.personal_data,
      data.cookies,
      data.responsibility,
      data.law,
      data.contact,
    ].filter(Boolean)
  } catch (e: any) {
    console.error(e)
    error.value = "Impossible de charger les mentions légales."
  } finally {
    loading.value = false
  }
})

function goHome() {
  router.push({ name: 'home' })
}
</script>

<template>
  <div class="legal-page">
    <header class="legal-header">
      <div>
        <h1>{{ title }}</h1>
        <p v-if="lastUpdated" class="legal-last-updated">
          Dernière mise à jour : {{ lastUpdated }}
        </p>
      </div>
      <button
        type="button"
        class="legal-back"
        @click="goHome"
      >
        ⟵ Retour au KCDLE
      </button>
    </header>

    <main class="legal-content">
      <div v-if="loading">
        Chargement des mentions légales...
      </div>
      <div v-else-if="error">
        {{ error }}
      </div>
      <div v-else>
        <section
          v-for="(block, idx) in blocks"
          :key="idx"
          class="legal-section"
        >
          <h2>{{ block.title }}</h2>
          <p
            v-for="(p, i) in block.paragraphs"
            :key="i"
          >
            {{ p }}
          </p>
        </section>
      </div>
    </main>
  </div>
</template>

<style scoped>
.legal-page {
  min-height: 100vh;
  padding: 20px;
  color: #f0f0f0;
  background: radial-gradient(circle at top, #20263a 0, #05060a 55%);
  font-size: 0.95rem;
}

.legal-header {
  display: flex;
  justify-content: space-between;
  gap: 10px;
  margin-bottom: 18px;
}

.legal-header h1 {
  margin: 0;
  font-size: 1.6rem;
}

.legal-last-updated {
  margin: 4px 0 0;
  font-size: 0.85rem;
  opacity: 0.8;
}

.legal-back {
  border: none;
  background: #00a6ff;
  color: #fff;
  padding: 6px 10px;
  border-radius: 4px;
  cursor: pointer;
  font-size: 0.9rem;
}

.legal-back:hover {
  filter: brightness(1.1);
}

.legal-content {
  max-width: 900px;
  margin: 0 auto;
}

.legal-content div{
  text-align: justify;
}

.legal-section {
  margin-bottom: 22px;
}

.legal-section h2 {
  margin-bottom: 8px;
  font-size: 1.1rem;
}
</style>
