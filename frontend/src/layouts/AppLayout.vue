<script setup>
import { ref, onMounted, onUnmounted, computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { useUiStore } from '../stores/ui'
import { api } from '../services/api'
import Icon from '../components/ui/Icon.vue'
import AppAvatar from '../components/ui/AppAvatar.vue'
import NewLetterModal from '../components/modals/NewLetterModal.vue'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const ui = useUiStore()

const counts = ref({ in: 0, in_unread: 0, out: 0, out_unread: 0, draft: 0 })
const notifications = ref({ count: 0, items: [] })
const showNotif = ref(false)
const notifTab = ref('unread')
const notifItems = computed(() => notifTab.value === 'all' ? notifications.value.items : notifications.value.items.filter((n) => !n.is_read))
const showUserMenu = ref(false)
const search = ref('')
const notifWrap = ref(null)
const userWrap = ref(null)

function doSearch() {
  const q = search.value.trim()
  router.push({ name: 'cartable', query: q ? { q } : {} })
}

// Close header popovers when clicking anywhere outside them.
function onDocClick(e) {
  if (showNotif.value && notifWrap.value && !notifWrap.value.contains(e.target)) showNotif.value = false
  if (showUserMenu.value && userWrap.value && !userWrap.value.contains(e.target)) showUserMenu.value = false
}

// Permission-gated navigation.
const nav = computed(() => [
  { name: 'dashboard', label: 'داشبورد', icon: 'home', color: 'var(--color-brand)', show: true },
  { name: 'cartable', key: 'inbox', label: 'نامه‌های دریافتی', icon: 'inbox', color: '#0093a6', query: { tab: 'in' }, badge: counts.value.in_unread ?? counts.value.in, show: auth.can('letters.view') },
  { name: 'cartable', key: 'outbox', label: 'نامه‌های ارسالی', icon: 'send', color: 'var(--color-brand)', query: { tab: 'out' }, badge: counts.value.out_unread || 0, show: auth.can('letters.view') },
  { name: 'cartable', key: 'todo', label: 'منتظر اقدام', icon: 'clock', color: '#d49c0f', query: { tab: 'mine' }, badge: counts.value.mine, show: auth.can('letters.view') },
  { name: 'secretariat', label: 'دبیرخانه', icon: 'archive', color: '#7b87a5', show: auth.can('secretariat.use') },
  { name: 'admin-letters', label: 'همهٔ نامه‌ها (ادمین)', icon: 'inbox', color: '#7b87a5', show: auth.can('departments.view') },
  { name: 'workflows', label: 'گردش کارها', icon: 'chart', color: '#d49c0f', show: auth.can('workflow.manage') },
  { name: 'templates', label: 'قالب‌ها', icon: 'file', color: '#0093a6', show: auth.can('templates.view') },
  { name: 'office-files', label: 'فایل‌های Word/Excel', icon: 'paperclip', color: 'var(--color-brand)', show: auth.can('templates.manage') },
  { name: 'personnel', label: 'کارکنان', icon: 'users', color: 'var(--color-brand)', show: auth.can('users.view') },
  { name: 'departments', label: 'واحدها', icon: 'building', color: '#0093a6', show: auth.can('departments.view') },
  { name: 'roles', label: 'نقش‌ها و دسترسی', icon: 'users', color: 'var(--color-brand)', show: auth.can('roles.manage') },
  { name: 'access-grants', label: 'دسترسی کاربران', icon: 'users', color: '#7b87a5', show: auth.can('roles.manage') },
  { name: 'messaging', label: 'مجوزهای مکاتبه', icon: 'send', color: '#0093a6', show: auth.can('messaging.manage') },
  { name: 'chat', label: 'گفتگو', icon: 'send', color: 'var(--color-brand)', show: auth.can('chat.use') },
  { name: 'events', label: 'رویدادها', icon: 'clock', color: '#d49c0f', show: true },
  { name: 'my-notes', label: 'یادداشت‌های من', icon: 'file', color: '#0093a6', show: true },
  { name: 'settings', label: 'تنظیمات', icon: 'cog', color: '#7b87a5', show: auth.can('settings.manage') },
].filter((n) => n.show))

const activeName = computed(() => (route.name === 'letter' ? 'cartable' : route.name))

// A nav item is active when its route matches; for the mailbox items we also
// match the tab query (دریافتی/ارسالی/منتظر اقدام) and ignore folder views.
function isActive(n) {
  if (activeName.value !== n.name) return false
  if (n.query?.tab) {
    if (route.query.folder) return false
    return (route.query.tab || 'in') === n.query.tab
  }
  return true
}

function fa(n) {
  return String(n).replace(/[0-9]/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d])
}

async function loadCounts() {
  try {
    counts.value = (await api.get('/letters')).counts
  } catch { /* ignore */ }
}
async function loadNotifications() {
  try {
    notifications.value = await api.get('/notifications')
  } catch { /* ignore */ }
}

function openNotif(letterId) {
  showNotif.value = false
  router.push({ name: 'letter', params: { id: letterId } })
}

function logout() {
  auth.logout()
  router.push('/login')
}

const drawerOpen = ref(false)
// Close popovers/drawer on route change and refresh the badges/notifications.
watch(() => route.fullPath, () => { showNotif.value = false; showUserMenu.value = false; drawerOpen.value = false; loadCounts(); loadNotifications() })

onMounted(() => { loadCounts(); loadNotifications(); document.addEventListener('mousedown', onDocClick) })
onUnmounted(() => document.removeEventListener('mousedown', onDocClick))
</script>

<template>
  <div class="min-h-screen md:grid md:grid-cols-[268px_1fr]" style="background: var(--color-ink-100)">
    <!-- Mobile drawer overlay -->
    <div v-if="drawerOpen" @click="drawerOpen = false" class="fixed inset-0 z-40 bg-black/40 md:hidden"></div>

    <!-- Sidebar (static on desktop, off-canvas drawer on mobile) -->
    <aside
      class="fixed inset-y-0 right-0 z-50 flex w-[276px] transform flex-col gap-1 overflow-y-auto border-l border-ink-200 bg-white p-[18px_14px] transition-transform md:static md:z-auto md:h-screen md:w-auto md:translate-x-0"
      :class="drawerOpen ? 'translate-x-0' : 'translate-x-full md:translate-x-0'">
      <div class="flex items-center gap-3 px-2 pt-1.5 pb-4">
        <img src="/assets/logo-mark.png" alt="گروه متحد 7" class="h-10" />
        <div class="leading-tight">
          <div class="text-[15px] font-extrabold text-brand-strong">گروه متحد 7</div>
          <div class="text-[11px] text-ink-500">اتوماسیون اداری</div>
        </div>
      </div>

      <button
        @click="ui.openNewLetter()"
        class="mx-1 mb-2.5 flex h-[42px] items-center justify-center gap-2 rounded-xl text-sm font-semibold text-white shadow-brand"
        style="background: var(--grad-brand)"
      >
        <Icon name="plus" :size="18" /> نامه جدید
      </button>

      <div class="px-2.5 pt-1.5 pb-1 text-[11px] font-semibold text-ink-500">منوی اصلی</div>
      <RouterLink
        v-for="n in nav"
        :key="n.key || n.name"
        :to="{ name: n.name, query: n.query }"
        class="flex items-center gap-3 rounded-[10px] border px-3 py-2.5 text-[13.5px] font-medium transition-colors"
        :class="isActive(n)
          ? 'border-blue-300 bg-blue-50 font-semibold text-brand-strong'
          : 'border-transparent text-ink-700 hover:bg-ink-100'"
      >
        <span class="flex" :style="{ color: isActive(n) ? 'var(--color-brand)' : n.color }">
          <Icon :name="n.icon" :size="18" />
        </span>
        <span class="flex-1">{{ n.label }}</span>
        <span v-if="n.badge" class="num rounded-full bg-blue-50 px-2 py-px text-[11px] font-semibold text-brand-strong">
          {{ fa(n.badge) }}
        </span>
      </RouterLink>

      <div class="mt-auto flex flex-col gap-1 border-t border-ink-200 pt-2.5">
        <button @click="logout" class="flex items-center gap-3 rounded-[10px] px-3 py-2.5 text-[13.5px] font-medium text-ink-700 hover:bg-ink-100">
          <Icon name="logout" :size="18" /> <span class="flex-1 text-right">خروج</span>
        </button>
      </div>
    </aside>

    <!-- Main column -->
    <div class="flex min-w-0 flex-col">
      <header class="sticky top-0 z-30 flex h-[66px] items-center gap-3 border-b border-ink-200 bg-white px-4 sm:px-6">
        <!-- hamburger (mobile) -->
        <button @click="drawerOpen = true" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-[10px] text-ink-700 hover:bg-ink-100 md:hidden" aria-label="منو">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
        </button>
        <div class="flex max-w-[460px] flex-1 items-center gap-2">
          <div class="relative flex-1">
            <span class="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-ink-500">
              <Icon name="search" :size="17" />
            </span>
            <input v-model="search" @keyup.enter="doSearch" placeholder="جستجوی نامه، شخص یا شناسه…"
              class="h-10 w-full rounded-xl border border-ink-200 bg-ink-50 px-9 text-[13.5px]" />
          </div>
          <button v-if="search.trim()" @click="doSearch"
            class="hidden h-10 shrink-0 rounded-xl px-4 text-[13px] font-semibold text-white shadow-brand sm:block" style="background: var(--grad-brand)">
            جستجو در کارتابل
          </button>
        </div>
        <div class="flex-1"></div>

        <!-- Notifications -->
        <div class="relative" ref="notifWrap">
          <button @click="showNotif = !showNotif; showUserMenu = false"
            class="relative flex h-10 w-10 items-center justify-center rounded-[10px] text-ink-600 hover:bg-ink-100">
            <Icon name="bell" :size="18" />
            <span v-if="notifications.count" class="num absolute -top-0.5 -left-0.5 flex h-[18px] min-w-[18px] items-center justify-center rounded-full bg-danger-500 px-1 text-[10px] font-bold text-white">
              {{ fa(notifications.count) }}
            </span>
          </button>
          <div v-if="showNotif" class="absolute left-0 top-12 z-40 w-[340px] overflow-hidden rounded-2xl border border-ink-200 bg-white shadow-pop">
            <div class="flex items-center justify-between border-b border-ink-200 px-4 py-3">
              <b class="text-[13.5px] text-ink-900">اعلان‌ها</b>
              <div class="flex gap-1 rounded-lg bg-ink-100 p-0.5 text-[11px]">
                <button @click="notifTab = 'unread'" class="rounded-md px-2 py-1" :class="notifTab === 'unread' ? 'bg-white font-semibold text-brand-strong shadow-card' : 'text-ink-600'">خوانده‌نشده</button>
                <button @click="notifTab = 'all'" class="rounded-md px-2 py-1" :class="notifTab === 'all' ? 'bg-white font-semibold text-brand-strong shadow-card' : 'text-ink-600'">همه</button>
              </div>
            </div>
            <div class="max-h-[360px] overflow-y-auto af-scroll">
              <button v-for="n in notifItems" :key="n.id" @click="openNotif(n.letter_id)"
                class="flex w-full items-start gap-3 border-b border-ink-100 px-4 py-3 text-right last:border-0 hover:bg-blue-50"
                :class="n.is_read ? '' : 'bg-[#fbfcff]'">
                <span class="mt-1.5 shrink-0 text-[13px]">{{ n.is_read ? '📭' : '✉️' }}</span>
                <span class="min-w-0 flex-1">
                  <span class="block text-[13px] text-ink-900" :class="n.is_read ? 'font-medium' : 'font-bold'">{{ n.subject }}</span>
                  <span class="block text-[11.5px] text-ink-500">{{ n.title }} · {{ n.from }}</span>
                  <span class="num block text-[11px] text-ink-400">{{ n.date }}</span>
                </span>
              </button>
              <div v-if="!notifItems.length" class="px-4 py-8 text-center text-[13px] text-ink-500">{{ notifTab === 'unread' ? 'نامهٔ خوانده‌نشده‌ای ندارید' : 'اعلانی وجود ندارد' }}</div>
            </div>
          </div>
        </div>

        <div class="h-7 w-px bg-ink-200"></div>

        <!-- User menu -->
        <div class="relative" ref="userWrap">
          <button @click="showUserMenu = !showUserMenu; showNotif = false" class="flex items-center gap-2.5 rounded-xl px-1.5 py-1 hover:bg-ink-100">
            <AppAvatar :name="auth.user?.name || ''" :photo="auth.user?.photo_url" :size="38" />
            <div class="leading-tight text-right">
              <div class="text-[13.5px] font-semibold text-ink-900">{{ auth.user?.name }}</div>
              <div class="text-[11.5px] text-ink-500">{{ auth.user?.role?.name }} · {{ auth.user?.role?.level_label }}</div>
            </div>
          </button>
          <div v-if="showUserMenu" class="absolute left-0 top-14 z-40 w-56 overflow-hidden rounded-2xl border border-ink-200 bg-white shadow-pop">
            <div class="border-b border-ink-200 px-4 py-3">
              <div class="text-[13.5px] font-semibold text-ink-900">{{ auth.user?.name }}</div>
              <div class="text-[12px] text-ink-500">{{ auth.user?.department?.name }}</div>
            </div>
            <RouterLink :to="{ name: 'profile' }" class="flex w-full items-center gap-2.5 border-b border-ink-100 px-4 py-3 text-right text-[13px] text-ink-700 hover:bg-ink-100">
              <Icon name="user" :size="16" /> پروفایل من
            </RouterLink>
            <button @click="logout" class="flex w-full items-center gap-2.5 px-4 py-3 text-right text-[13px] text-danger-600 hover:bg-danger-50">
              <Icon name="logout" :size="16" /> خروج از حساب
            </button>
          </div>
        </div>
      </header>

      <main class="af-scroll flex-1 overflow-y-auto p-4 sm:p-6">
        <RouterView @letters-changed="loadCounts(); loadNotifications()" />
      </main>
    </div>
  </div>

  <NewLetterModal v-if="ui.showNewLetter" @close="ui.closeNewLetter()" />
</template>
