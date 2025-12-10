import { defineStore } from 'pinia'

export type FlashType = 'success' | 'error' | 'info' | 'warning'

export interface FlashMessage {
  id: number
  type: FlashType
  title?: string
  message: string
  timeout?: number
}

interface FlashState {
  messages: FlashMessage[]
  lastId: number
}

export const useFlashStore = defineStore('flash', {
  state: (): FlashState => ({
    messages: [],
    lastId: 0,
  }),

  actions: {
    push(type: FlashType, message: string, title?: string, timeout = 4000) {
      this.lastId += 1
      const msg: FlashMessage = {
        id: this.lastId,
        type,
        message,
        title,
        timeout,
      }
      this.messages.push(msg)

      if (timeout && timeout > 0) {
        setTimeout(() => {
          this.remove(msg.id)
        }, timeout)
      }
    },

    success(message: string, title?: string, timeout = 4000) {
      this.push('success', message, title, timeout)
    },

    error(message: string, title?: string, timeout = 6000) {
      this.push('error', message, title, timeout)
    },

    info(message: string, title?: string, timeout = 4000) {
      this.push('info', message, title, timeout)
    },

    warning(message: string, title?: string, timeout = 5000) {
      this.push('warning', message, title, timeout)
    },

    remove(id: number) {
      this.messages = this.messages.filter(m => m.id !== id)
    },

    clear() {
      this.messages = []
    },
  },
})
