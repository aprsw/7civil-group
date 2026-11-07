<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '../services/api'
import { useAuthStore } from '../stores/auth'
import { useUiStore } from '../stores/ui'
import StatCard from '../components/ui/StatCard.vue'
import AppCard from '../components/ui/AppCard.vue'
import AppBadge from '../components/ui/AppBadge.vue'
import AppAvatar from '../components/ui/AppAvatar.vue'
import ProgressBar from '../components/ui/ProgressBar.vue'
import Icon from '../components/ui/Icon.vue'

const router = useRouter()
const auth = useAuthStore()
const ui = useUiStore()

const data = ref({ stats: [], recent: [], workflows: [], today_events: [], week_events: [], my_reminders: [], greeting_name: '' })
const loading = ref(true)

// ── customizable layout (persisted per-user in the browser) ──────────
const SECTIONS = { events: 'رویدادهای امروز', week: 'رویدادهای این هفته', recent: 'آخرین نامه‌های کارتابل', workflows: 'گردش کارهای فعال' }
const DEFAULT_ORDER = ['events', 'week', 'recent', 'workflows']
const DEFAULT_STATS = ['in', 'out', 'unseen', 'mine']
const editing = ref(false)
const layout = ref({ order: [...DEFAULT_ORDER], hidden: [], stats: [...DEFAULT_STATS] })

function storageKey() { return `civil7_dash_layout_${auth.user?.id ?? 'x'}` }
function loadLayout() {
  try {
    const raw = JSON.parse(localStorage.getItem(storageKey()) || 'null')
    if (raw && Array.isArray(raw.order)) {
      // keep only known keys, append any newly-added sections at the end
      const order = raw.order.filter((k) => SECTIONS[k])
      for (const k of DEFAULT_ORDER) if (!order.includes(k)) order.push(k)
      const stats = Array.isArray(raw.stats) ? raw.stats.filter((k) => DEFAULT_STATS.includes(k)) : [...DEFAULT_STATS]
      for (const k of DEFAULT_STATS) if (!stats.includes(k)) stats.push(k)
      layout.value = { order, hidden: (raw.hidden || []).filter((k) => SECTIONS[k]), stats }
    }
  } catch { /* ignore */ }
}
function persist() { localStorage.setItem(storageKey(), JSON.stringify(layout.value)) }
function move(key, dir) {
  const o = layout.value.order
  const i = o.indexOf(key)
  const j = i + dir
  if (j < 0 || j >= o.length) return
  o.splice(i, 1); o.splice(j, 0, key); persist()
}
function moveStat(key, dir) {
  const s = layout.value.stats
  const i = s.indexOf(key)
  const j = i + dir
  if (j < 0 || j >= s.length) return
  s.splice(i, 1); s.splice(j, 0, key); persist()
}
// data.stats reordered per the saved KPI arrangement.
const orderedStats = computed(() => {
  const byKey = Object.fromEntries((data.value.stats || []).map((s) => [s.key, s]))
  const ordered = layout.value.stats.map((k) => byKey[k]).filter(Boolean)
  for (const s of (data.value.stats || [])) if (!layout.value.stats.includes(s.key)) ordered.push(s)
  return ordered
})
function toggleHide(key) {
  const h = layout.value.hidden
  const i = h.indexOf(key)
  if (i === -1) h.push(key); else h.splice(i, 1)
  persist()
}
function resetLayout() { layout.value = { order: [...DEFAULT_ORDER], hidden: [], stats: [...DEFAULT_STATS] }; persist() }
const visibleSections = computed(() => layout.value.order.filter((k) => !layout.value.hidden.includes(k)))

// ── clickable KPI tiles ──────────────────────────────────────────────
const STAT_TARGETS = {
  in: { name: 'cartable', query: { tab: 'in' } },
  out: { name: 'cartable', query: { tab: 'out' } },
  unseen: { name: 'cartable', query: { tab: 'in' } },
  mine: { name: 'cartable', query: { tab: 'mine' } },
}
function goStat(s) { const t = STAT_TARGETS[s.key]; if (t) router.push(t) }

onMounted(async () => {
  loadLayout()
  try {
    data.value = await api.get('/dashboard')
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="flex flex-col gap-[22px]" style="max-width: 1280px">
    <!-- personal reminders due now (یادداشت کاربر) -->
    <router-link v-if="data.my_reminders && data.my_reminders.length" :to="{ name: 'my-notes' }"
      class="flex items-start gap-3 rounded-2xl border border-warning-200 bg-warning-50 px-4 py-3 no-underline">
      <span class="text-[20px]">🔔</span>
      <div class="min-w-0">
        <div class="text-[13px] font-bold text-warning-600">یادآوری‌های شما ({{ data.my_reminders.length }})</div>
        <div class="mt-0.5 flex flex-wrap gap-x-3 gap-y-1 text-[12.5px] text-ink-700">
          <span v-for="r in data.my_reminders" :key="r.id">
            <b>{{ r.title }}</b>
            <span class="text-ink-500"> — {{ r.is_today ? 'امروز' : (r.days_remaining + ' روز مانده') }}</span>
          </span>
        </div>
      </div>
    </router-link>

    <div class="flex flex-wrap items-end justify-between gap-4">
      <div>
        <h1 class="m-0 text-3xl font-bold text-ink-900">سلام {{ data.greeting_name || auth.firstName }} 👋</h1>
        <p class="mt-1.5 text-ink-600">امروز نامه‌های در انتظار اقدام در کارتابل شما قرار دارد.</p>
      </div>
      <div class="flex items-center gap-2.5">
        <button @click="editing = !editing"
          class="flex h-11 items-center gap-2 rounded-xl border px-4 text-[13px] font-semibold"
          :class="editing ? 'border-blue-300 bg-blue-50 text-brand-strong' : 'border-ink-300 bg-white text-ink-800'">
          <Icon name="cog" :size="16" /> {{ editing ? 'پایان چیدمان' : 'شخصی‌سازی چیدمان' }}
        </button>
        <button @click="ui.openNewLetter()"
          class="flex h-11 items-center gap-2 rounded-xl px-5 text-sm font-semibold text-white shadow-brand"
          style="background: var(--grad-brand)">
          <Icon name="plus" :size="18" /> نامه جدید
        </button>
      </div>
    </div>

    <!-- KPI tiles (clickable, reorderable) -->
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
      <div v-for="s in orderedStats" :key="s.key" class="relative">
        <button @click="goStat(s)" class="block w-full cursor-pointer text-right transition hover:-translate-y-0.5">
          <StatCard v-bind="s" />
        </button>
        <div v-if="editing" class="absolute -bottom-3 left-1/2 flex -translate-x-1/2 gap-1 rounded-lg border border-ink-200 bg-white px-1 py-0.5 shadow-card">
          <button @click.stop="moveStat(s.key, 1)" title="جابه‌جایی به راست" class="h-6 w-6 rounded text-ink-600 hover:bg-ink-100">▸</button>
          <button @click.stop="moveStat(s.key, -1)" title="جابه‌جایی به چپ" class="h-6 w-6 rounded text-ink-600 hover:bg-ink-100">◂</button>
        </div>
      </div>
    </div>

    <!-- Customize hint / hidden sections -->
    <div v-if="editing" class="flex flex-wrap items-center gap-2 rounded-2xl border border-blue-200 bg-blue-50 px-4 py-3 text-[12.5px] text-brand-strong">
      <span class="font-semibold">حالت چیدمان:</span> با دکمه‌های ⬆ ⬇ ترتیب و با 👁 نمایش/پنهان‌سازی کارت‌ها را تنظیم کنید. تنظیمات در مرورگر شما ذخیره می‌شود.
      <button @click="resetLayout" class="mr-auto rounded-lg border border-blue-300 bg-white px-3 py-1 text-[12px]">بازگردانی پیش‌فرض</button>
      <span v-for="k in layout.hidden" :key="k" class="flex items-center gap-1 rounded-full bg-white px-2.5 py-1 text-ink-600">
        {{ SECTIONS[k] }} <button @click="toggleHide(k)" class="text-brand-strong">＋ نمایش</button>
      </span>
    </div>

    <!-- Reorderable section stack -->
    <div class="flex flex-col gap-4">
      <div v-for="key in visibleSections" :key="key">
        <div v-if="editing" class="mb-1.5 flex items-center gap-2 text-[12px] text-ink-500">
          <b class="text-ink-700">{{ SECTIONS[key] }}</b>
          <button @click="move(key, -1)" class="h-7 w-7 rounded-lg border border-ink-200 bg-white">⬆</button>
          <button @click="move(key, 1)" class="h-7 w-7 rounded-lg border border-ink-200 bg-white">⬇</button>
          <button @click="toggleHide(key)" class="h-7 rounded-lg border border-ink-200 bg-white px-2">👁 پنهان</button>
        </div>

        <!-- Today's events -->
        <AppCard v-if="key === 'events'" title="رویدادهای امروز" accent="gold">
          <div v-if="data.today_events && data.today_events.length" class="flex flex-col gap-2">
            <div v-for="e in data.today_events" :key="e.id"
              @click="router.push({ name: 'events' })"
              class="flex cursor-pointer items-center justify-between gap-3 rounded-xl border border-ink-100 bg-ink-50 px-4 py-2.5 hover:bg-warning-50">
              <div class="flex items-center gap-2.5">
                <span class="flex h-8 w-8 items-center justify-center rounded-lg text-white" style="background: var(--grad-gold)"><Icon name="clock" :size="15" /></span>
                <div>
                  <div class="text-[13.5px] font-semibold text-ink-900">{{ e.title }}</div>
                  <div v-if="e.location" class="text-[11.5px] text-ink-500">📍 {{ e.location }}</div>
                </div>
              </div>
              <span class="num text-[12px] text-ink-500">{{ e.event_date }}</span>
            </div>
          </div>
          <div v-else class="py-4 text-center text-[13px] text-ink-500">رویدادی برای امروز ثبت نشده است.</div>
        </AppCard>

        <!-- This week's events -->
        <AppCard v-else-if="key === 'week'" title="رویدادهای این هفته" accent="teal">
          <div v-if="data.week_events && data.week_events.length" class="flex flex-col gap-2">
            <div v-for="e in data.week_events" :key="e.id"
              @click="router.push({ name: 'events' })"
              class="flex cursor-pointer items-center justify-between gap-3 rounded-xl border border-ink-100 bg-ink-50 px-4 py-2.5 hover:bg-blue-50"
              :class="e.is_today ? 'ring-1 ring-blue-200' : ''">
              <div class="flex items-center gap-2.5">
                <span class="flex h-8 min-w-[46px] items-center justify-center rounded-lg px-2 text-[11px] font-semibold text-white" style="background: var(--grad-teal)">{{ e.weekday }}</span>
                <div>
                  <div class="text-[13.5px] font-semibold text-ink-900">{{ e.title }}<span v-if="e.is_today" class="mr-1.5 text-[10.5px] text-brand-strong">(امروز)</span></div>
                  <div v-if="e.location" class="text-[11.5px] text-ink-500">📍 {{ e.location }}</div>
                </div>
              </div>
              <span class="num text-[12px] text-ink-500">{{ e.event_date }}</span>
            </div>
          </div>
          <div v-else class="py-4 text-center text-[13px] text-ink-500">رویدادی برای این هفته ثبت نشده است.</div>
        </AppCard>

        <!-- Recent letters -->
        <AppCard v-else-if="key === 'recent'" title="آخرین نامه‌های کارتابل" subtitle="۴ مورد اخیر" :padding="false">
          <table class="w-full border-collapse">
            <thead>
              <tr>
                <th v-for="h in ['موضوع نامه', 'فرستنده', 'وضعیت', 'تاریخ']" :key="h"
                  class="bg-ink-50 px-4 py-2.5 text-start text-[11.5px] font-semibold text-ink-500"
                  :class="h === 'تاریخ' ? 'text-end' : ''">{{ h }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="l in data.recent" :key="l.id" @click="router.push({ name: 'letter', params: { id: l.id } })"
                class="cursor-pointer border-b border-ink-200 last:border-0 hover:bg-blue-50">
                <td class="px-4 py-3 text-sm font-semibold text-ink-900">{{ l.subject }}</td>
                <td class="px-4 py-3 text-sm">
                  <span class="inline-flex items-center gap-2"><AppAvatar :name="l.from" :size="26" />{{ l.from }}</span>
                </td>
                <td class="px-4 py-3"><AppBadge :tone="l.status.tone">{{ l.status.label }}</AppBadge></td>
                <td class="num whitespace-nowrap px-4 py-3 text-end text-sm text-ink-600">{{ l.date }}</td>
              </tr>
              <tr v-if="!data.recent.length"><td colspan="4" class="px-4 py-8 text-center text-[13px] text-ink-500">نامه‌ای برای نمایش نیست.</td></tr>
            </tbody>
          </table>
        </AppCard>

        <!-- Active workflows -->
        <AppCard v-else-if="key === 'workflows'" title="گردش کارهای فعال" accent="teal">
          <div class="flex flex-col gap-[18px]">
            <div v-for="(w, i) in data.workflows" :key="i">
              <div class="mb-2 flex justify-between text-[13.5px]">
                <span class="truncate text-ink-800">{{ w.name }}</span>
                <span class="num shrink-0 text-ink-500">{{ w.pctLabel }}</span>
              </div>
              <ProgressBar :value="w.pct" :tone="w.tone" />
            </div>
            <div v-if="!data.workflows.length" class="py-2 text-center text-[13px] text-ink-500">گردش کار فعالی نیست.</div>
          </div>
        </AppCard>
      </div>
    </div>
  </div>
</template>
