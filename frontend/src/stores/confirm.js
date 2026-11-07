import { defineStore } from 'pinia'

// Global confirmation dialog. Any view can `await confirm.ask({...})` to get a
// promise that resolves true (confirmed) or false (cancelled / dismissed).
export const useConfirmStore = defineStore('confirm', {
  state: () => ({
    open: false,
    title: 'تأیید',
    message: '',
    confirmText: 'تأیید',
    cancelText: 'انصراف',
    tone: 'danger', // danger | brand
    _resolve: null,
  }),
  actions: {
    ask(opts = {}) {
      this.title = opts.title || 'تأیید عملیات'
      this.message = opts.message || ''
      this.confirmText = opts.confirmText || 'تأیید'
      this.cancelText = opts.cancelText || 'انصراف'
      this.tone = opts.tone || 'danger'
      this.open = true
      return new Promise((resolve) => { this._resolve = resolve })
    },
    _settle(value) {
      this.open = false
      const r = this._resolve
      this._resolve = null
      if (r) r(value)
    },
    confirm() { this._settle(true) },
    cancel() { this._settle(false) },
  },
})
