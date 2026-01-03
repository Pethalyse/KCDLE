<script setup lang="ts">
import { computed } from 'vue'
import { useFlashStore } from '@/stores/flash'

const flash = useFlashStore()

const messages = computed(() => flash.messages)

function onClose(id: number) {
  flash.remove(id)
}
</script>

<template>
  <div class="flash-container" v-if="messages.length">
    <div
      v-for="msg in messages"
      :key="msg.id"
      class="flash"
      :class="`flash--${msg.type}`"
    >
      <div class="flash__content">
        <div class="flash__text">
          <div v-if="msg.title" class="flash__title">
            {{ msg.title }}
          </div>
          <div class="flash__message">
            {{ msg.message }}
          </div>
        </div>
        <button
          type="button"
          class="flash__close"
          @click="onClose(msg.id)"
        >
          ✕
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.flash-container {
  position: fixed;
  top: 72px;
  right: 16px;
  display: flex;
  flex-direction: column;
  gap: 8px;
  z-index: 60;
}

.flash {
  min-width: 260px;
  max-width: 340px;
  border-radius: 10px;
  padding: 10px 12px;
  background: rgba(10, 12, 20, 0.98);
  border: 1px solid rgba(255, 255, 255, 0.12);
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.7);
  backdrop-filter: blur(10px);
}

.flash__content {
  display: flex;
  align-items: flex-start;
  gap: 8px;
}

.flash__text {
  flex: 1;
}

.flash__title {
  font-size: 0.85rem;
  font-weight: 600;
  margin-bottom: 2px;
}

.flash__message {
  font-size: 0.85rem;
  opacity: 0.95;
}

.flash__close {
  border: none;
  background: transparent;
  color: #f5f5f5;
  font-size: 0.9rem;
  cursor: pointer;
  padding: 0 2px;
}

.flash--success {
  border-color: rgba(0, 200, 140, 0.85);
  box-shadow: 0 0 0 1px rgba(0, 200, 140, 0.5), 0 10px 25px rgba(0, 0, 0, 0.7);
}

.flash--error {
  border-color: rgba(255, 90, 90, 0.9);
  box-shadow: 0 0 0 1px rgba(255, 90, 90, 0.7), 0 10px 25px rgba(0, 0, 0, 0.7);
}

.flash--info {
  border-color: rgba(0, 166, 255, 0.9);
  box-shadow: 0 0 0 1px rgba(0, 166, 255, 0.7), 0 10px 25px rgba(0, 0, 0, 0.7);
}

.flash--warning {
  border-color: rgba(255, 190, 70, 0.9);
  box-shadow: 0 0 0 1px rgba(255, 190, 70, 0.7), 0 10px 25px rgba(0, 0, 0, 0.7);
}

@media (max-width: 640px) {
  .flash-container {
    top: 70px;
    right: 8px;
    left: 8px;
    align-items: stretch;
  }

  .flash {
    max-width: none;
  }
}
</style>
