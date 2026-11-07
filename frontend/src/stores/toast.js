import { defineStore } from 'pinia'

let seq = 0

export const useToastStore = defineStore('toast', {
  state: () => ({ items: [] }),
  actions: {
    show(message, tone = 'success') {
      const id = ++seq
      this.items.push({ id, message, tone })
      setTimeout(() => this.dismiss(id), 2800)
    },
    dismiss(id) {
      this.items = this.items.filter((t) => t.id !== id)
    },
  },
})
