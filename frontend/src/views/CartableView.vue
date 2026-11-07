<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { api } from '../services/api'
import { useUiStore } from '../stores/ui'
import { useToastStore } from '../stores/toast'
import { useConfirmStore } from '../stores/confirm'
import AppCard from '../components/ui/AppCard.vue'
import AppBadge from '../components/ui/AppBadge.vue'
import AppTag from '../components/ui/AppTag.vue'
import AppAvatar from '../components/ui/AppAvatar.vue'
import Icon from '../components/ui/Icon.vue'

const route = useRoute()
const router = useRouter()
const ui = useUiStore()
const toast = useToastStore()
const confirmStore = useConfirmStore()

const VALID_TABS = ['in', 'out', 'mine', 'draft']
const tab = ref(VALID_TABS.includes(route.query.tab) ? route.query.tab : 'in')
// «خوانده‌نشده» section — available under both دریافتی and ارسالی.
const readFilter = ref(route.query.read === 'unread' ? 'unread' : '')
const items = ref([])
const counts = ref({ mine: 0, in: 0, in_unread: 0, out: 0, out_unread: 0, draft: 0 })
const folders = ref([])
const activeFolder = ref(route.query.folder ? Number(route.query.folder) : null)
const selectedId = ref(null)
const q = ref((route.query.q || '').trim())

// kind sub-filter (همه/اقدام/اطلاع/امضا/پاسخ/پیگیری)
const KINDS = [
  { v: '', label: 'همه' },
  { v: 'action', label: 'اقدام' },
  { v: 'info', label: 'اطلاع' },
  { v: 'sign', label: 'امضا' },
  { v: 'reply', label: 'پاسخ' },
  { v: 'followup', label: 'پیگیری' },
]
const kind = ref(route.query.kind || '')

// multi-select for moving to folders
const selected = ref(new Set())
const selectMode = ref(false)

const tabs = computed(() => [
  { v: 'in', label: 'دریافتی', count: counts.value.in, unread: counts.value.in_unread || 0 },
  { v: 'out', label: 'ارسالی', count: counts.value.out, unread: counts.value.out_unread || 0 },
  { v: 'mine', label: 'منتظر اقدام', count: counts.value.mine },
  { v: 'draft', label: 'پیش‌نویس', count: counts.value.draft },
])
const showKindFilter = computed(() => (tab.value === 'in' || tab.value === 'out') && !activeFolder.value && !q.value)
const hasUnreadSection = computed(() => tab.value === 'in' || tab.value === 'out')
const isUnreadBox = computed(() => hasUnreadSection.value && readFilter.value === 'unread' && !activeFolder.value && !q.value)

const title = computed(() => {
  if (q.value) return `جستجو: «${q.value}»`
  if (activeFolder.value) return folders.value.find((f) => f.id === activeFolder.value)?.name || 'فولدر'
  if (isUnreadBox.value) return tab.value === 'in' ? 'نامه‌های دریافتی خوانده‌نشده' : 'نامه‌های ارسالی خوانده‌نشده (هنوز توسط گیرنده باز نشده)'
  return { in: 'نامه‌های دریافتی', out: 'نامه‌های ارسالی', mine: 'منتظر اقدام من', draft: 'پیش‌نویس‌ها' }[tab.value]
})

// Unread marker per box: in «دریافتی» it is whether *I* opened it; in «ارسالی»
// whether *any recipient* has opened it yet.
function isUnread(l) { return tab.value === 'out' && !activeFolder.value && !q.value ? !!l.is_unread_by_recipients : !!l.is_unread }
function unreadTitle(l) {
  if (tab.value === 'out' && !activeFolder.value && !q.value) return l.is_unread_by_recipients ? 'هنوز توسط گیرنده خوانده نشده' : 'توسط گیرنده خوانده شده'
  return l.is_unread ? 'خوانده‌نشده' : 'خوانده‌شده'
}

const statusFilter = ref('') // '' | pending | approved | rejected
function statusCat(l) {
  if (l.wf_status) {
    if (l.wf_status.tone === 'success') return 'approved'
    if (l.wf_status.tone === 'warning') return 'pending'
    if (l.wf_status.tone === 'neutral' && l.wf_status.label.includes('رد')) return 'rejected'
  }
  const v = l.status.value
  if (v === 'rejected') return 'rejected'
  if (v === 'pending' || v === 'reviewing') return 'pending'
  if (v === 'approved' || v === 'sent') return 'approved'
  return v
}
const filtered = computed(() => {
  let list = items.value
  if (showKindFilter.value && kind.value) list = list.filter((l) => l.kind?.value === kind.value)
  if (statusFilter.value) list = list.filter((l) => statusCat(l) === statusFilter.value)
  return list
})
const active = computed(() => filtered.value.find((l) => l.id === selectedId.value) || filtered.value[0] || null)

function fa(n) { return String(n).replace(/[0-9]/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]) }
// The body is stored as HTML; strip tags for the small preview card.
function plain(html) { const d = document.createElement('div'); d.innerHTML = html || ''; return d.textContent || '' }
// Workflow letters show their chain status (در انتظار تأیید X / تأیید شده / رد
// شده توسط X); normal letters their plain status.
function statusLabel(l) { return l.wf_status?.label || l.status.label }
const TONE_HEX = { success: '#16a34a', warning: '#d49c0f', neutral: '#64748b', brand: '#3467de' }
function statusTone(l) { return l.wf_status?.tone || l.status.tone }

async function load() {
  let url = '/letters'
  if (q.value) {
    const data = await api.get('/letters')
    counts.value = data.counts; folders.value = data.folders || []
    const needle = q.value.toLowerCase()
    items.value = data.items.filter((l) => [l.subject, l.number, l.from, l.to].some((f) => String(f || '').toLowerCase().includes(needle)))
  } else {
    if (activeFolder.value) url += `?folder=${activeFolder.value}`
    else url += `?box=${tab.value}` + (isUnreadBox.value ? '&read=unread' : '')
    const data = await api.get(url)
    items.value = data.items
    counts.value = data.counts
    folders.value = data.folders || []
  }
  selected.value = new Set()
  selectMode.value = false
  selectedId.value = filtered.value[0]?.id ?? null
}

function switchTab(v, read = '') {
  activeFolder.value = null; kind.value = ''; tab.value = v
  readFilter.value = read === 'unread' && (v === 'in' || v === 'out') ? 'unread' : ''
  const query = { tab: v }
  if (readFilter.value) query.read = 'unread'
  router.replace({ name: 'cartable', query })
  load()
}
function openFolder(id) { activeFolder.value = id; load() }

async function reject() {
  if (!active.value) return
  try { await api.post(`/letters/${active.value.id}/reject`); toast.show('نامه جهت اصلاح بازگردانده شد'); load() }
  catch (e) { toast.show(e.message, 'danger') }
}
function continueDraft(l) { router.push({ name: 'compose', query: { draft: l.id } }) }

async function removeLetter(l) {
  const isDraft = l.status.value === 'draft'
  const ok = await confirmStore.ask({
    title: isDraft ? 'حذف پیش‌نویس' : 'حذف نامه',
    message: isDraft ? 'این پیش‌نویس برای همیشه حذف شود؟' : 'این نامه حذف شود؟ حذف فقط تا پیش از مشاهده توسط گیرندگان ممکن است (کاربر ارشد بدون محدودیت).',
    confirmText: 'حذف', cancelText: 'انصراف', tone: 'danger',
  })
  if (!ok) return
  try { await api.del(`/letters/${l.id}`); toast.show('حذف شد'); load() }
  catch (e) { toast.show(e.message, 'danger') }
}

// ── folders ──────────────────────────────────────────────
function toggleSelect(id) {
  const s = new Set(selected.value)
  s.has(id) ? s.delete(id) : s.add(id)
  selected.value = s
}
async function moveTo(folderId) {
  const ids = [...selected.value]
  if (!ids.length) { toast.show('ابتدا نامه‌ها را انتخاب کنید', 'danger'); return }
  try {
    await api.post(`/folders/${folderId}/letters`, { letter_ids: ids })
    toast.show(`${fa(ids.length)} نامه منتقل شد`)
    load()
  } catch (e) { toast.show(e.message, 'danger') }
}
async function restoreToInbox() {
  const ids = [...selected.value]
  if (!ids.length) return
  try { await api.post('/folders/unfile', { letter_ids: ids }); toast.show('به صندوق بازگردانده شد'); load() }
  catch (e) { toast.show(e.message, 'danger') }
}
async function newFolder() {
  const name = prompt('نام فولدر جدید:')
  if (!name) return
  try { await api.post('/folders', { name }); load() }
  catch (e) { toast.show(e.message, 'danger') }
}
async function renameFolder(f) {
  const name = prompt('نام جدید فولدر:', f.name)
  if (!name || name === f.name) return
  try { await api.put(`/folders/${f.id}`, { name }); load() }
  catch (e) { toast.show(e.message, 'danger') }
}
async function deleteFolder(f) {
  const ok = await confirmStore.ask({ title: 'حذف فولدر', message: `فولدر «${f.name}» حذف شود؟ نامه‌ها به صندوق بازمی‌گردند.`, confirmText: 'حذف', tone: 'danger' })
  if (!ok) return
  try { await api.del(`/folders/${f.id}`); if (activeFolder.value === f.id) { activeFolder.value = null } load() }
  catch (e) { toast.show(e.message, 'danger') }
}

watch(() => [route.query.tab, route.query.read], ([t, r]) => {
  const nextRead = r === 'unread' ? 'unread' : ''
  const tabChanged = VALID_TABS.includes(t) && t !== tab.value
  const readChanged = nextRead !== readFilter.value
  if (!tabChanged && !readChanged) return
  activeFolder.value = null
  if (VALID_TABS.includes(t)) tab.value = t
  readFilter.value = (tab.value === 'in' || tab.value === 'out') ? nextRead : ''
  load()
})
watch(() => route.query.kind, (k) => { kind.value = k || '' })
watch(() => route.query.q, (v) => { q.value = (v || '').trim(); load() })
onMounted(load)
</script>

<template>
  <div class="flex flex-col gap-[18px]" style="max-width: 1320px">
    <div class="flex items-center justify-between gap-4">
      <div>
        <h1 class="m-0 text-3xl font-bold text-ink-900">صندوق نامه</h1>
        <p class="mt-1.5 text-ink-600">{{ title }}</p>
      </div>
      <button @click="ui.openNewLetter()" class="flex h-11 items-center gap-2 rounded-xl px-5 text-sm font-semibold text-white shadow-brand" style="background: var(--grad-brand)">
        <Icon name="plus" :size="18" /> نامه جدید
      </button>
    </div>

    <div class="grid grid-cols-1 items-start gap-[18px] md:grid-cols-[210px_1fr]">
      <!-- sidebar: boxes + folders -->
      <div class="flex flex-col gap-3">
        <div class="rounded-2xl border border-ink-200 bg-white p-2 shadow-card">
          <template v-for="t in tabs" :key="t.v">
            <button @click="switchTab(t.v)"
              class="mb-1 flex w-full items-center justify-between rounded-[10px] px-3 py-2 text-[13px] font-semibold"
              :class="tab === t.v && !activeFolder && !isUnreadBox ? 'bg-blue-50 text-brand-strong' : 'text-ink-700 hover:bg-ink-50'">
              <span>{{ t.label }}</span><span class="num text-[11px] opacity-70">{{ fa(t.count) }}</span>
            </button>
            <!-- «خوانده‌نشده» sub-section for دریافتی and ارسالی -->
            <button v-if="t.unread !== undefined" @click="switchTab(t.v, 'unread')"
              class="mb-1 flex w-full items-center justify-between rounded-[10px] py-1.5 pe-3 ps-6 text-[12px] font-semibold"
              :class="tab === t.v && isUnreadBox ? 'bg-blue-50 text-brand-strong' : 'text-ink-600 hover:bg-ink-50'"
              :title="t.v === 'in' ? 'نامه‌هایی که هنوز باز نکرده‌اید' : 'نامه‌هایی که گیرنده هنوز باز نکرده است'">
              <span class="flex items-center gap-1.5"><span class="text-[11px]">✉️</span> خوانده‌نشده</span>
              <span class="num rounded-full px-1.5 text-[10.5px]" :class="t.unread ? 'bg-danger-50 text-danger-500' : 'opacity-60'">{{ fa(t.unread) }}</span>
            </button>
          </template>
        </div>
        <div class="rounded-2xl border border-ink-200 bg-white p-2 shadow-card">
          <div class="flex items-center justify-between px-2 py-1">
            <span class="text-[11.5px] font-bold text-ink-500">فولدرها</span>
            <button @click="newFolder" class="text-[16px] text-brand-strong" title="فولدر جدید">＋</button>
          </div>
          <div v-for="f in folders" :key="f.id" class="group flex items-center gap-1">
            <button @click="openFolder(f.id)"
              class="flex flex-1 items-center justify-between rounded-[9px] px-3 py-2 text-[12.5px]"
              :class="activeFolder === f.id ? 'bg-blue-50 text-brand-strong' : 'text-ink-700 hover:bg-ink-50'">
              <span class="flex items-center gap-1.5">{{ f.is_system ? '🗄' : '📁' }} {{ f.name }}</span>
              <span class="num text-[10.5px] opacity-60">{{ fa(f.count) }}</span>
            </button>
            <div v-if="!f.is_system" class="flex opacity-0 group-hover:opacity-100">
              <button @click="renameFolder(f)" class="px-1 text-[11px] text-ink-400" title="تغییر نام">✎</button>
              <button @click="deleteFolder(f)" class="px-1 text-[11px] text-danger-500" title="حذف">×</button>
            </div>
          </div>
        </div>
      </div>

      <div class="flex flex-col gap-3">
        <!-- kind sub-filter -->
        <div v-if="showKindFilter" class="flex flex-wrap items-center gap-1.5 rounded-2xl border border-ink-200 bg-white px-3 py-2 shadow-card">
          <button v-for="k in KINDS" :key="k.v" @click="kind = k.v"
            class="h-8 rounded-lg px-3 text-[12.5px] font-semibold"
            :class="kind === k.v ? 'bg-brand-strong text-white' : 'text-ink-600 hover:bg-ink-100'">{{ k.label }}</button>
        </div>

        <!-- select / move toolbar -->
        <div class="flex flex-wrap items-center gap-2 rounded-2xl border border-ink-200 bg-white px-3 py-2 shadow-card">
          <button @click="selectMode = !selectMode; selected = new Set()"
            class="h-8 rounded-lg border px-3 text-[12.5px] font-semibold"
            :class="selectMode ? 'border-brand-strong bg-blue-50 text-brand-strong' : 'border-ink-200 text-ink-600'">
            ☑ انتخاب چندتایی
          </button>
          <span class="mx-1 h-5 w-px bg-ink-200"></span>
          <span class="text-[12px] text-ink-500">وضعیت:</span>
          <select v-model="statusFilter" class="h-8 rounded-lg border border-ink-200 bg-white px-2 text-[12.5px]">
            <option value="">همه</option>
            <option value="pending">در انتظار تأیید</option>
            <option value="approved">تأیید شده</option>
            <option value="rejected">رد شده</option>
          </select>
          <template v-if="selectMode">
            <span class="num text-[12px] text-ink-500">{{ fa(selected.size) }} انتخاب‌شده</span>
            <div class="flex-1"></div>
            <span class="text-[12px] text-ink-500">انتقال به:</span>
            <select @change="(e) => { if (e.target.value) moveTo(e.target.value); e.target.value = '' }" class="h-8 rounded-lg border border-ink-200 bg-white px-2 text-[12.5px]">
              <option value="">— فولدر —</option>
              <option v-for="f in folders" :key="f.id" :value="f.id">{{ f.name }}</option>
            </select>
            <button v-if="activeFolder" @click="restoreToInbox" class="h-8 rounded-lg border border-ink-200 px-3 text-[12px] text-ink-700">بازگرداندن به صندوق</button>
          </template>
          <div v-if="q" class="flex-1 text-end"><button @click="router.push({ name: 'cartable' })" class="h-8 rounded-lg border border-ink-200 px-3 text-[12px] text-ink-600">✕ پاک کردن جستجو</button></div>
        </div>

        <div class="grid grid-cols-1 items-start gap-[18px] lg:grid-cols-[1.5fr_1fr]">
          <AppCard :padding="false">
            <div class="overflow-x-auto">
            <table class="w-full border-collapse" style="min-width: 440px">
              <thead>
                <tr>
                  <th v-if="selectMode" class="bg-ink-50 px-2 py-2.5"></th>
                  <th v-for="h in ['موضوع', 'نوع', 'وضعیت', 'تاریخ']" :key="h"
                    class="bg-ink-50 px-4 py-2.5 text-start text-[11.5px] font-semibold text-ink-500" :class="h === 'تاریخ' ? 'text-end' : ''">{{ h }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="l in filtered" :key="l.row_key || l.id" @click="selectedId = l.id"
                  class="cursor-pointer border-b border-ink-200 last:border-0 transition-colors"
                  :class="[
                    l.id === selectedId ? 'bg-blue-100/70' : (isUnread(l) ? 'bg-blue-50 hover:bg-blue-100/60' : 'bg-white hover:bg-ink-50'),
                    isUnread(l) ? 'border-s-[3px] border-s-[var(--color-brand)]' : 'border-s-[3px] border-s-transparent opacity-[0.82]',
                  ]">
                  <td v-if="selectMode" class="px-2 py-3" @click.stop>
                    <input type="checkbox" :checked="selected.has(l.id)" @change="toggleSelect(l.id)" class="h-4 w-4 accent-[var(--color-brand)]" />
                  </td>
                  <td class="px-4 py-3">
                    <div class="flex items-center gap-2">
                      <span v-if="isUnread(l)" class="h-2 w-2 shrink-0 rounded-full" style="background: var(--color-brand)" :title="unreadTitle(l)"></span>
                      <span v-else class="h-2 w-2 shrink-0" :title="unreadTitle(l)"></span>
                      <span :title="unreadTitle(l)">{{ isUnread(l) ? '✉️' : '📭' }}</span>
                      <AppAvatar :name="l.from" :photo="l.sender_photo_url" :size="26" />
                      <span class="text-sm" :class="isUnread(l) ? 'font-extrabold text-ink-900' : 'font-medium text-ink-600'">{{ l.subject }}</span>
                    </div>
                    <div class="mt-0.5 pr-6 text-[12px]" :class="isUnread(l) ? 'text-ink-600' : 'text-ink-400'">از: {{ l.from }} <span class="text-ink-300">•</span> به: {{ l.to }}</div>
                    <div v-if="l.referral_label" class="mt-0.5 pr-6 text-[11px] font-semibold text-teal-600">
                      ↩ {{ l.referral_label }}<template v-if="l.referral_by"> از {{ l.referral_by }}</template><span v-if="l.referral_at" class="num font-normal text-ink-400"> • {{ l.referral_at }}</span>
                    </div>
                  </td>
                  <td class="px-4 py-3"><AppTag :tone="l.kind.tone">{{ l.kind.label }}</AppTag></td>
                  <td class="px-4 py-3"><AppBadge :tone="statusTone(l)">{{ statusLabel(l) }}</AppBadge></td>
                  <td class="num whitespace-nowrap px-4 py-3 text-end text-sm" :class="isUnread(l) ? 'font-semibold text-ink-700' : 'text-ink-400'">{{ l.date }}</td>
                </tr>
                <tr v-if="!filtered.length">
                  <td :colspan="selectMode ? 5 : 4" class="px-4 py-10 text-center text-[13.5px] text-ink-500">{{ isUnreadBox ? 'نامهٔ خوانده‌نشده‌ای وجود ندارد.' : 'موردی برای نمایش نیست.' }}</td>
                </tr>
              </tbody>
            </table>
            </div>
          </AppCard>

          <AppCard v-if="active" accent="brand">
            <div class="mb-3.5 flex items-start gap-2.5">
              <AppAvatar :name="active.from" :photo="active.sender_photo_url" :size="40" />
              <div class="min-w-0">
                <div class="text-[15px] font-bold text-ink-900">{{ active.subject }}</div>
                <div class="mt-1 flex flex-col gap-0.5 text-[12.5px] text-ink-600">
                  <span><b class="text-ink-500">از:</b> {{ active.from }}</span>
                  <span><b class="text-ink-500">به:</b> {{ active.to }}</span>
                  <span class="num text-ink-400">{{ active.date }}</span>
                </div>
              </div>
            </div>
            <div class="mb-3.5 flex flex-wrap gap-2">
              <AppTag :tone="active.kind.tone">{{ active.kind.label }}</AppTag>
              <AppBadge :tone="statusTone(active)">{{ statusLabel(active) }}</AppBadge>
              <AppTag :tone="active.scope.tone">{{ active.scope.label }}</AppTag>
              <AppTag v-if="active.confidentiality.value !== 'normal'" :tone="active.confidentiality.tone">🔒 {{ active.confidentiality.label }}</AppTag>
            </div>
            <p class="m-0 max-h-[220px] overflow-hidden text-sm leading-7 text-ink-800">{{ plain(active.body) }}</p>
            <div class="mt-4 flex items-center gap-2 rounded-xl bg-ink-50 px-3 py-2.5 text-[13px] text-ink-600">
              <Icon name="paperclip" :size="15" /> {{ active.attachment }}
            </div>
            <!-- workflow: the per-letter approver chain the sender defined -->
            <div v-if="active.approvers && active.approvers.length" class="mt-4 flex flex-col gap-1.5">
              <div class="text-[12px] font-bold text-ink-600">مسیر تأیید این نامه:</div>
              <div v-for="(a, i) in active.approvers" :key="i" class="flex items-center gap-2 rounded-lg border px-2.5 py-1.5 text-[12.5px]"
                :class="a.status === 'approved' ? 'border-green-200 bg-green-50' : a.status === 'rejected' ? 'border-danger-500 bg-danger-50' : 'border-ink-200 bg-ink-50'">
                <span class="num flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-[10px] font-bold text-white" :style="{ background: a.status === 'approved' ? '#16a34a' : a.status === 'rejected' ? '#e0384d' : 'var(--grad-brand)' }">{{ fa(i + 1) }}</span>
                <span class="min-w-0 flex-1 truncate text-ink-900">{{ a.name }}<span v-if="a.role" class="text-ink-500"> — {{ a.role }}</span></span>
                <span :class="a.status === 'approved' ? 'text-green-600' : a.status === 'rejected' ? 'text-danger-500' : 'text-ink-400'">{{ a.status === 'approved' ? '✔' : a.status === 'rejected' ? '✕' : '…' }}</span>
              </div>
            </div>
            <div v-if="active.is_my_turn" class="mt-4 mb-1 rounded-lg bg-teal-50 px-3 py-2 text-[12px] font-semibold text-teal-700">● این نامه در انتظار اقدام شماست</div>

            <div v-if="active.status.value === 'draft'" class="mt-3 flex gap-2.5">
              <button @click="continueDraft(active)" class="h-[42px] flex-1 rounded-xl text-[13.5px] font-bold text-white shadow-brand" style="background: var(--grad-brand)">✎ ادامهٔ ویرایش</button>
              <button @click="removeLetter(active)" class="h-[42px] rounded-xl border border-danger-500 bg-white px-4 text-[13.5px] font-semibold text-danger-500">حذف پیش‌نویس</button>
            </div>
            <div v-else class="mt-3 flex gap-2.5">
              <button @click="router.push({ name: 'letter', params: { id: active.id } })" class="h-[42px] flex-1 rounded-xl text-[13.5px] font-bold shadow-gold" style="background: var(--grad-gold); color: #3a2a02">
                {{ active.can_approve ? 'مشاهده و اقدام' : 'مشاهدهٔ نامه' }}
              </button>
              <button v-if="active.can_approve" @click="reject" class="h-[42px] rounded-xl border border-ink-300 bg-white px-4 text-[13.5px] font-semibold text-ink-800">عدم تأیید</button>
              <button v-if="active.show_delete" @click="removeLetter(active)" title="حذف نامه" class="h-[42px] rounded-xl border border-danger-500 bg-white px-3 text-[13.5px] font-semibold text-danger-500">🗑</button>
            </div>
          </AppCard>
        </div>
      </div>
    </div>
  </div>
</template>
