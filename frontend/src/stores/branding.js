import { defineStore } from 'pinia'
import { api } from '../services/api'

// The organisation's name, شعار and logos, as configured in «تنظیمات».
// GET /api/branding is public, so the login screen can render the right
// company's mark before anyone signs in.
//
// `fallback*` are the bundled assets used until the API answers, and whenever
// a slot has no logo assigned.
const FALLBACK_MARK = '/assets/logo-mark.png'
const FALLBACK_FULL = '/assets/logo-full-white.png'

export const useBrandingStore = defineStore('branding', {
  state: () => ({
    name: 'گروه متحد 7',
    tagline: '',
    nameLatin: '7CivilGroup',
    letterheadLogo: null,
    loginLogo: null,
    sidebarLogo: null,
    loaded: false,
  }),
  getters: {
    // Each slot falls back to a bundled asset so no screen ever shows a gap.
    letterheadLogoUrl: (s) => s.letterheadLogo || FALLBACK_MARK,
    sidebarLogoUrl: (s) => s.sidebarLogo || FALLBACK_MARK,
    loginLogoUrl: (s) => s.loginLogo || FALLBACK_FULL,
  },
  actions: {
    apply(data) {
      if (!data) return
      this.name = data.name || this.name
      this.tagline = data.tagline ?? ''
      this.nameLatin = data.name_latin ?? this.nameLatin
      this.letterheadLogo = data.logo_letterhead_url || null
      this.loginLogo = data.logo_login_url || null
      this.sidebarLogo = data.logo_sidebar_url || null
      this.loaded = true
      if (this.name) document.title = `سامانه اتوماسیون اداری ${this.name}`
    },
    async load(force = false) {
      if (this.loaded && !force) return
      try {
        this.apply(await api.get('/branding'))
      } catch {
        // Branding is cosmetic: keep the bundled defaults and carry on, so a
        // backend hiccup never blocks the login screen.
        this.loaded = true
      }
    },
  },
})
