import { defineStore } from 'pinia'
import { api, setToken } from '../services/api'

function readUser() {
  const raw = localStorage.getItem('arta_user') || sessionStorage.getItem('arta_user')
  if (!raw) return null
  try {
    return JSON.parse(raw)
  } catch {
    // Corrupted/partial storage must not crash app boot.
    localStorage.removeItem('arta_user')
    sessionStorage.removeItem('arta_user')
    return null
  }
}

export const useAuthStore = defineStore('auth', {
  state: () => ({
    token: localStorage.getItem('arta_token') || sessionStorage.getItem('arta_token') || null,
    user: readUser(),
    remember: localStorage.getItem('arta_token') !== null,
  }),

  getters: {
    isAuthenticated: (s) => !!s.token && !!s.user,
    permissions: (s) => s.user?.role?.permissions || [],
    can: (s) => (permission) => (s.user?.role?.permissions || []).includes(permission),
    isManager: (s) => (s.user?.role?.permissions || []).includes('workflow.manage'),
    initial: (s) => (s.user?.name || '؟').trim().charAt(0),
    firstName: (s) => (s.user?.name || '').split(' ')[0] || '',
  },

  actions: {
    persist() {
      setToken(this.token, this.remember)
      const store = this.remember ? localStorage : sessionStorage
      const other = this.remember ? sessionStorage : localStorage
      other.removeItem('arta_user')
      if (this.user) store.setItem('arta_user', JSON.stringify(this.user))
      else { localStorage.removeItem('arta_user'); sessionStorage.removeItem('arta_user') }
    },

    async login(username, password, remember = true) {
      const data = await api.post('/auth/login', { username, password })
      this.remember = remember
      this.token = data.token
      this.user = data.user
      this.persist()
      return data.user
    },

    async refresh() {
      if (!this.token) return
      try {
        this.user = await api.get('/auth/me')
        this.persist()
      } catch {
        this.logout()
      }
    },

    logout() {
      this.token = null
      this.user = null
      this.persist()
    },
  },
})
