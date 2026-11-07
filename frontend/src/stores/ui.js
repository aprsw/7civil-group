import { defineStore } from 'pinia'

// Cross-view UI state: the "new letter" chooser is opened from the sidebar,
// the dashboard and the cartable, so it lives in a shared store.
export const useUiStore = defineStore('ui', {
  state: () => ({ showNewLetter: false }),
  actions: {
    openNewLetter() {
      this.showNewLetter = true
    },
    closeNewLetter() {
      this.showNewLetter = false
    },
  },
})
