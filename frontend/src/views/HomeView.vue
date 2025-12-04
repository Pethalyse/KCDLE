<script setup lang="ts">
import SimpleImg from '@/components/SimpleImg.vue'
import GameButton from '@/components/GameButton.vue'
import Credit from '@/components/Credit.vue'

const data = [
  { dle: 'KCDLE', active: true },
  { dle: 'LECDLE', active: true },
  { dle: 'LFLDLE', active: true },
]
</script>

<template>
  <div>
    <header class="header_HOME">
      <SimpleImg :img="'HOMEDLE_Header.png'" />
    </header>

    <div class="dle_body HOME">
      <div class="btn-container">
        <GameButton
          v-for="val in data"
          :key="val.dle"
          :data="val"
        />
      </div>
    </div>

    <Credit />
  </div>
</template>
