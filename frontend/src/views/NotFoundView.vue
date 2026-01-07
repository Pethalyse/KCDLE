<template>
  <main class="not-found">
    <h1>Page introuvable</h1>
    <p>La page que vous recherchez n’existe pas ou n’est plus disponible.</p>
    <router-link to="/" class="btn-home">
      Retour à l’accueil
    </router-link>
  </main>
</template>

<script setup lang="ts">
</script>

<style scoped>
.not-found {
  background: radial-gradient(circle at top, #20263a 0, #05060a 70%);
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 1rem;
  text-align: center;
  padding: 0;
}

.btn-home {
  padding: 0.75rem 1.5rem;
  border-radius: 999px;
  border: none;
  background: #1f2933;
  color: #ffffff;
  text-decoration: none;
  font-weight: 600;
}
</style>
