<script setup lang="ts">
import { onMounted, ref } from 'vue'
import SimpleImg from '@/components/SimpleImg.vue'
import api from '@/api'
import {handleError} from "@/utils/handleError.ts";

interface Contributor {
  name: string
  slug?: string
  twitter: string
  role: string
  description?: string
  avatar?: string
}

const contributors = ref<Contributor[]>([])
const loading = ref(true)
const error = ref<string | null>(null)

onMounted(async () => {
  try {
    const { data } = await api.get('/credits')
    contributors.value = data.contributors
  } catch (e: any) {
    handleError(e)
    error.value = 'Impossible de charger les crédits.'
  } finally {
    loading.value = false
  }
})

function twitterUrl(twitter: string) {
  const handle = twitter.startsWith('@') ? twitter.slice(1) : twitter
  return `https://twitter.com/${handle}`
}
</script>

<template>
  <div class="credit">
    <div class="containt-containt-credit">
      <div v-if="loading">
        Chargement...
      </div>
      <div v-else-if="error">
        {{ error }}
      </div>
      <template v-else>
        <div
          class="containt-credit"
          v-for="user in contributors"
          :key="user.slug ?? user.twitter"
        >
          <a
            :href="twitterUrl(user.twitter)"
            target="_blank"
            rel="noopener noreferrer"
          >
            <SimpleImg
              v-if="user.avatar"
              :alt="user.name"
              :img="user.avatar"
            />
            <span>{{ user.name }}</span>
          </a>
        </div>
      </template>
    </div>
  </div>
</template>
