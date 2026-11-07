<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { api } from '../services/api'
import { useToastStore } from '../stores/toast'
import { useConfirmStore } from '../stores/confirm'
import AppBadge from '../components/ui/AppBadge.vue'
import AppTag from '../components/ui/AppTag.vue'
import WorkflowStepper from '../components/ui/WorkflowStepper.vue'
import Icon from '../components/ui/Icon.vue'
import ReferralModal from '../components/modals/ReferralModal.vue'
import NoteEditorModal from '../components/modals/NoteEditorModal.vue'

const route = useRoute()
const router = useRouter()
const toast = useToastStore()
const confirmStore = useConfirmStore()

const letter = ref(null)
const error = ref('')
const showRefer = ref(false)
const showReply = ref(false)
const viewNote = ref(null)
const referring = ref(false)

async function doRefer(payload) {
  referring.value = true
  try {
    await api.post(`/letters/${letter.value.id}/refer`, payload)
    toast.show('نامه با موفقیت ارجاع شد')
    showRefer.value = false
    load()
  } catch (e) {
    toast.show(e.message, 'danger')
  } finally {
    referring.value = false
  }
}

function printLetter() {
  // Set the page size to match the letter, print, then restore.
  const size = letter.value?.paper_size === 'a5' ? 'A5' : 'A4'
  const style = document.createElement('style')
  style.id = 'af-print-page'
  style.textContent = `@page { size: ${size} portrait; margin: 0; }`
  document.head.appendChild(style)
  window.print()
  setTimeout(() => style.remove(), 500)
}

// Save the letter to the user's device as a self-contained HTML file.
function saveToDesktop() {
  const area = document.getElementById('print-area')
  if (!area) return
  const l = letter.value
  const doc = `<!doctype html><html dir="rtl" lang="fa"><head><meta charset="utf-8">
<title>نامه ${l?.number || ''} — ${l?.subject || ''}</title>
<style>
  body{font-family:Tahoma,'IRANSans',sans-serif;background:#fff;color:#111;margin:0;padding:24px;direction:rtl}
  .paper{max-width:210mm;margin:0 auto;padding:22mm 20mm;line-height:2;font-size:12pt}
  table{border-collapse:collapse;width:100%;margin:8px 0}
  td,th{border:1px solid #8a93a6;padding:6px}
  img{max-width:100%}
</style></head><body><div class="paper">${area.innerHTML}</div></body></html>`
  const blob = new Blob([doc], { type: 'text/html;charset=utf-8' })
  const a = document.createElement('a')
  a.href = URL.createObjectURL(blob)
  a.download = `نامه-${(l?.number || l?.id || 'letter')}.html`
  document.body.appendChild(a)
  a.click()
  a.remove()
  setTimeout(() => URL.revokeObjectURL(a.href), 1000)
  toast.show('نامه برای ذخیره در دستگاه آماده شد')
}

async function recall() {
  const ok = await confirmStore.ask({
    title: 'حذف نامه',
    message: 'این نامه حذف شود؟ حذف فقط تا پیش از مشاهده توسط گیرندگان ممکن است (کاربر ارشد بدون محدودیت). این عملیات قابل بازگشت نیست.',
    confirmText: 'حذف نامه', cancelText: 'انصراف', tone: 'danger',
  })
  if (!ok) return
  try {
    await api.del(`/letters/${letter.value.id}`)
    toast.show('نامه حذف شد')
    router.push({ name: 'cartable', query: { tab: 'out' } })
  } catch (e) {
    toast.show(e.message, 'danger')
  }
}

async function load() {
  error.value = ''
  try {
    letter.value = await api.get(`/letters/${route.params.id}`)
  } catch (e) {
    letter.value = null
    error.value = e.message || 'نامه در دسترس نیست.'
  }
}

// Reply depth for the tree (0 = thread root).
const thread = computed(() => {
  const items = letter.value?.thread || []
  const byId = Object.fromEntries(items.map((l) => [l.id, l]))
  const depth = (l) => {
    let d = 0
    let cur = l
    while (cur && cur.reply_to_id && byId[cur.reply_to_id]) { d++; cur = byId[cur.reply_to_id] }
    return d
  }
  return items.map((l) => ({ ...l, depth: depth(l) }))
})

async function approve() {
  const note = prompt('توضیح (اختیاری) برای تأیید:', '') ?? ''
  try {
    await api.post(`/letters/${letter.value.id}/approve`, { note })
    toast.show('نامه تأیید و به مرحلهٔ بعد ارجاع شد')
    router.push({ name: 'cartable' })
  } catch (e) {
    toast.show(e.message, 'danger')
  }
}
async function reject() {
  const note = prompt('دلیل عدم تأیید / بازگشت نامه را بنویسید:', '')
  if (note === null) return // cancelled
  try {
    await api.post(`/letters/${letter.value.id}/reject`, { note })
    toast.show('نامه جهت اصلاح بازگردانده شد')
    load()
  } catch (e) {
    toast.show(e.message, 'danger')
  }
}
function reply() { showReply.value = true }
async function doReply(payload) {
  referring.value = true
  try {
    await api.post(`/letters/${letter.value.id}/reply`, payload)
    toast.show('پاسخ ثبت و برای فرستنده ارسال شد')
    showReply.value = false
    load()
  } catch (e) {
    toast.show(e.message, 'danger')
  } finally {
    referring.value = false
  }
}
async function downloadAttachment(a) {
  try {
    await api.download(`/letters/${letter.value.id}/attachments/${a.id}/download`, a.name)
  } catch (e) {
    toast.show(e.message, 'danger')
  }
}

// ── inline preview (PDF / images) ────────────────────────────────
const MIME_BY_EXT = {
  pdf: 'application/pdf', png: 'image/png', jpg: 'image/jpeg', jpeg: 'image/jpeg',
  gif: 'image/gif', webp: 'image/webp', bmp: 'image/bmp', svg: 'image/svg+xml',
}
const preview = ref(null) // { url, name, kind: 'image'|'pdf', a }
function extOf(name) { return (name || '').split('.').pop().toLowerCase() }
function previewable(a) { return !!MIME_BY_EXT[extOf(a.name)] }
async function viewAttachment(a) {
  const ext = extOf(a.name)
  const mime = MIME_BY_EXT[ext]
  if (!mime) return
  try {
    const buf = await api.fetchBytes(`/letters/${letter.value.id}/attachments/${a.id}/download`)
    const url = URL.createObjectURL(new Blob([buf], { type: mime }))
    preview.value = { url, name: a.name, kind: ext === 'pdf' ? 'pdf' : 'image', a }
  } catch (e) { toast.show(e.message, 'danger') }
}
function closePreview() {
  if (preview.value?.url) URL.revokeObjectURL(preview.value.url)
  preview.value = null
}
async function downloadNote(n) {
  try {
    await api.download(`/letters/${letter.value.id}/notes/${n.id}/download`, n.file_name || n.title)
  } catch (e) {
    toast.show(e.message, 'danger')
  }
}
function noteIcon(n) {
  const name = (n.file_name || n.title || '').toLowerCase()
  if (/\.(xlsx|xls|csv)$/.test(name)) return '📊'
  if (/\.(docx?|rtf)$/.test(name)) return '📄'
  return '📎'
}
function humanSize(b) {
  if (!b) return ''
  if (b < 1024) return b + ' B'
  if (b < 1048576) return (b / 1024).toFixed(1) + ' KB'
  return (b / 1048576).toFixed(1) + ' MB'
}

watch(() => route.params.id, load)
onMounted(load)
</script>

<template>
  <div v-if="error" class="mx-auto max-w-[640px]">
    <div class="flex flex-col items-center gap-4 rounded-2xl border border-ink-200 bg-white p-10 text-center shadow-card">
      <div class="text-5xl">🔒</div>
      <div class="text-[15px] font-bold text-ink-900">{{ error }}</div>
      <button @click="router.push({ name: 'cartable' })" class="h-10 rounded-xl px-5 text-[13.5px] font-semibold text-white shadow-brand" style="background: var(--grad-brand)">بازگشت به کارتابل</button>
    </div>
  </div>

  <div v-else-if="letter" class="grid grid-cols-1 gap-5 lg:grid-cols-[1fr_300px]" style="max-width: 1180px">
    <div class="flex flex-col gap-4">
      <div class="flex flex-wrap items-center gap-3">
        <button @click="router.back()" class="h-[38px] rounded-[10px] border border-ink-200 bg-white px-3.5 text-[13px] text-ink-800">→ بازگشت</button>
        <h1 class="m-0 text-2xl font-bold text-ink-900">نمایش نامه</h1>
        <div class="flex-1"></div>
        <button v-if="letter.can_approve" @click="approve"
          class="h-10 rounded-[11px] text-[13.5px] font-bold shadow-gold" style="background: var(--grad-gold); color: #3a2a02; padding-inline: 18px">
          ✔ تأیید و ارجاع
        </button>
        <button v-if="letter.can_approve" @click="reject" class="h-10 rounded-[11px] border border-ink-300 bg-white px-4 text-[13.5px] font-semibold text-ink-800">عدم تأیید</button>
        <button v-if="letter.can_refer" @click="showRefer = true" class="h-10 rounded-[11px] border border-teal-300 bg-teal-50 px-4 text-[13.5px] font-semibold text-teal-700">⇄ ارجاع</button>
        <button v-if="letter.can_reply" @click="reply" class="h-10 rounded-[11px] border border-blue-300 bg-blue-50 px-4 text-[13.5px] font-semibold text-brand-strong">↩ پاسخ</button>
        <button v-if="letter.show_delete" @click="recall" class="h-10 rounded-[11px] border border-danger-500 bg-white px-4 text-[13.5px] font-semibold text-danger-500">🗑 حذف نامه</button>
        <button @click="printLetter" class="h-10 rounded-[11px] border border-ink-300 bg-white px-4 text-[13.5px] font-semibold text-ink-800">🖶 چاپ</button>
        <button @click="saveToDesktop" class="h-10 rounded-[11px] border border-ink-300 bg-white px-4 text-[13.5px] font-semibold text-ink-800">💾 ذخیره در دسکتاپ</button>
      </div>

      <!-- meta bar -->
      <div class="flex flex-wrap items-center gap-x-6 gap-y-3 rounded-2xl border border-ink-200 bg-white px-5 py-3.5 text-[12.5px] text-ink-600 shadow-card">
        <span>شماره ثبت: <b class="num text-ink-800">{{ letter.number }}</b></span>
        <span>تاریخ: <b class="num text-ink-800">{{ letter.date }}</b></span>
        <span>فرستنده: <b class="text-ink-800">{{ letter.from }}</b></span>
        <span>گیرنده: <b class="text-ink-800">{{ letter.to }}</b></span>
        <span class="flex items-center gap-1.5">اولویت: <AppTag :tone="letter.priority.tone">{{ letter.priority.label }}</AppTag></span>
        <span class="flex items-center gap-1.5">وضعیت: <AppBadge :tone="letter.wf_status?.tone || letter.status.tone">{{ letter.wf_status?.label || letter.status.label }}</AppBadge></span>
        <span class="flex items-center gap-1.5">دامنه: <AppTag :tone="letter.scope.tone">{{ letter.scope.label }}</AppTag></span>
        <span class="flex items-center gap-1.5">نوع: <AppTag :tone="letter.is_workflow ? 'gold' : 'neutral'">{{ letter.letter_type_label }}</AppTag></span>
        <span v-if="letter.confidentiality.value !== 'normal'" class="flex items-center gap-1.5">محرمانگی: <AppTag :tone="letter.confidentiality.tone">🔒 {{ letter.confidentiality.label }}</AppTag></span>
      </div>

      <!-- approver chain (workflow letters with a per-letter chain) -->
      <div v-if="letter.approvers && letter.approvers.length" class="rounded-2xl border border-ink-200 bg-white p-5 shadow-card">
        <div class="mb-3 text-[13.5px] font-bold text-ink-900">مسیر تأیید</div>
        <div class="flex flex-col gap-2">
          <div v-for="(a, i) in letter.approvers" :key="i"
            class="flex items-center gap-3 rounded-xl border px-3 py-2.5"
            :class="a.status === 'approved' ? 'border-green-200 bg-green-50' : a.status === 'rejected' ? 'border-danger-500 bg-danger-50' : 'border-ink-200 bg-ink-50'">
            <span class="num flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-[12px] font-bold text-white"
              :style="{ background: a.status === 'approved' ? '#16a34a' : a.status === 'rejected' ? '#e0384d' : 'var(--grad-brand)' }">{{ i + 1 }}</span>
            <div class="min-w-0 flex-1">
              <div class="text-[13px] font-semibold text-ink-900">{{ a.name }}<span v-if="a.role" class="text-[11.5px] font-normal text-ink-500"> — {{ a.role }}</span></div>
              <div v-if="a.note" class="text-[11.5px] text-ink-500">یادداشت: {{ a.note }}</div>
            </div>
            <span class="shrink-0 text-[12px] font-semibold"
              :class="a.status === 'approved' ? 'text-green-600' : a.status === 'rejected' ? 'text-danger-500' : 'text-ink-400'">
              {{ a.status === 'approved' ? '✔ تأیید شد' : a.status === 'rejected' ? '✕ رد شد' : '… در انتظار' }}
            </span>
          </div>
        </div>
      </div>
      <!-- legacy role-based stepper -->
      <div v-else-if="letter.is_workflow" class="rounded-2xl border border-ink-200 bg-white p-5 shadow-card">
        <WorkflowStepper :steps="letter.stages" :current="letter.stage" />
      </div>
      <div v-else class="flex items-center gap-2 rounded-2xl border border-ink-200 bg-white px-5 py-3.5 text-[13px] text-ink-600 shadow-card">
        <span class="h-2 w-2 rounded-full bg-teal-500"></span> نامهٔ عادی — بدون گردش کار؛ مستقیم به گیرنده تحویل شده است.
      </div>

      <!-- attachments -->
      <div v-if="letter.attachments && letter.attachments.length" class="flex flex-col gap-2 rounded-2xl border border-ink-200 bg-white px-5 py-3.5 shadow-card">
        <div class="mb-1 flex items-center gap-2 text-[12.5px] font-bold text-ink-700">
          <Icon name="paperclip" :size="15" /> پیوست‌ها <span class="num text-ink-400">({{ letter.attachments.length }})</span>
        </div>
        <div v-for="a in letter.attachments" :key="a.id" class="flex items-center justify-between gap-3 rounded-xl border border-ink-100 bg-ink-50 px-3 py-2">
          <div class="flex min-w-0 items-center gap-2 text-[13px] text-ink-800">
            <Icon name="paperclip" :size="14" /> <b class="truncate text-ink-900">{{ a.name }}</b>
            <span v-if="a.size_label" class="num shrink-0 text-[11.5px] text-ink-500">({{ a.size_label }})</span>
          </div>
          <div class="flex shrink-0 items-center gap-2">
            <button v-if="previewable(a)" @click="viewAttachment(a)" class="h-8 rounded-[10px] border border-ink-300 bg-white px-3 text-[12.5px] font-semibold text-ink-700">👁 مشاهده</button>
            <button @click="downloadAttachment(a)" class="h-8 rounded-[10px] px-3.5 text-[12.5px] font-semibold text-white shadow-brand" style="background: var(--grad-brand)">⬇ دانلود</button>
          </div>
        </div>
      </div>

      <!-- editable Office files delivered with the letter -->
      <div v-if="letter.notes && letter.notes.length" class="flex flex-col gap-2 rounded-2xl border border-ink-200 bg-white px-5 py-3.5 shadow-card">
        <div class="mb-1 flex items-center gap-2 text-[12.5px] font-bold text-ink-700">
          <Icon name="paperclip" :size="15" /> فایل‌های پیوستی <span class="num text-ink-400">({{ letter.notes.length }})</span>
        </div>
        <div v-for="n in letter.notes" :key="n.id" class="flex items-center justify-between gap-3 rounded-xl border border-ink-100 bg-ink-50 px-3 py-2">
          <b class="flex min-w-0 flex-1 items-center gap-1.5 truncate text-[13px] text-ink-900">
            <span>{{ noteIcon(n) }}</span>
            <span class="min-w-0 flex-1 truncate">{{ n.file_name || n.title }}</span>
            <span v-if="n.file_size" class="num shrink-0 text-[11px] font-normal text-ink-400">{{ humanSize(n.file_size) }}</span>
          </b>
          <button v-if="n.file_path" @click="downloadNote(n)" class="h-8 shrink-0 rounded-[10px] px-3.5 text-[12.5px] font-semibold text-white shadow-brand" style="background: var(--grad-brand)">⬇ دانلود</button>
          <button v-else @click="viewNote = n" class="h-8 shrink-0 rounded-[10px] border border-ink-200 bg-white px-3.5 text-[12.5px] font-semibold text-ink-700">مشاهده</button>
        </div>
      </div>

      <!-- letterhead (A4/A5 to match the letter) -->
      <div class="flex justify-center rounded-2xl bg-ink-150 px-4 py-7">
        <div id="print-area" class="max-w-full bg-white p-[22mm_20mm] text-[12pt] leading-loose text-[#111] shadow-pop"
          :style="{ width: letter.paper_size === 'a5' ? '148mm' : '210mm' }">
          <div class="mb-4 flex items-start justify-between gap-4">
            <div class="flex items-center gap-2.5">
              <img src="/assets/logo-mark.png" class="h-11" />
              <div>
                <div class="text-[20pt] font-extrabold text-brand-strong">گروه داروسازی آرتا فارما</div>
                <div class="mt-0.5 text-[10.5pt] text-[#444]">آرتا قلب بهسان طب</div>
              </div>
            </div>
            <table class="border-collapse text-[10.5pt]" style="min-width: 190px">
              <tr><td class="border border-[#333] bg-[#f3f3f3] px-2 py-[3px] whitespace-nowrap">شماره:</td><td class="num border border-[#333] px-2 py-[3px]">{{ letter.number }}</td></tr>
              <tr><td class="border border-[#333] bg-[#f3f3f3] px-2 py-[3px]">تاریخ:</td><td class="num border border-[#333] px-2 py-[3px]">{{ letter.date }}</td></tr>
              <tr><td class="border border-[#333] bg-[#f3f3f3] px-2 py-[3px]">پیوست:</td><td class="border border-[#333] px-2 py-[3px]">{{ letter.attachment_short }}</td></tr>
            </table>
          </div>
          <p class="mt-5 mb-1">{{ letter.to }}</p>
          <p class="my-1.5"><b>موضوع:</b> {{ letter.subject }}</p>
          <p class="mt-4 mb-2.5">با سلام و احترام؛</p>
          <div class="af-letter-body my-2.5 text-justify" v-html="letter.body"></div>
          <p v-if="letter.body2" class="my-2.5 text-justify">{{ letter.body2 }}</p>
          <!-- Signatures: creator + each approver, flush-left, borderless, larger. -->
          <div class="af-signs mt-12 flex flex-wrap gap-x-12 gap-y-6" style="justify-content: flex-end">
            <div v-for="(s, i) in (letter.signatures && letter.signatures.length ? letter.signatures : [{ name: letter.sign_name, role: letter.sign_role, url: letter.sign_signature_url, label: 'تنظیم‌کننده' }])"
              :key="i" class="min-w-[150px] text-center">
              <div class="text-[9.5pt] text-[#888]">{{ s.label }}</div>
              <img v-if="s.url" :src="s.url" alt="امضا" class="mx-auto my-1" style="max-height: 66px" />
              <div v-else class="my-1" style="height: 44px"></div>
              <div class="text-[11.5pt] font-bold text-[#111]">{{ s.name }}</div>
              <div class="text-[10pt] text-[#555]">{{ s.role }}</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Conversation tree (درخت‌وارهٔ نامه) -->
    <aside class="flex flex-col gap-3">
      <!-- Recipients & referrals (گیرندگان و ارجاعات) -->
      <div v-if="letter.recipients && letter.recipients.length" class="rounded-2xl border border-ink-200 bg-white p-4 shadow-card">
        <div class="mb-3 flex items-center gap-2 text-[13.5px] font-bold text-ink-900">
          <Icon name="users" :size="16" /> گیرندگان و ارجاعات
        </div>
        <div class="flex flex-col gap-2">
          <div v-for="(r, i) in letter.recipients" :key="i" class="flex items-start justify-between gap-2 rounded-xl border border-ink-100 bg-ink-50 px-3 py-2">
            <div class="min-w-0">
              <div class="flex items-center gap-1.5 text-[12.5px] font-semibold text-ink-900">
                <span v-if="r.is_department" class="text-teal-600">⛋</span>{{ r.target }}
              </div>
              <div v-if="r.referred_by" class="text-[11px] text-ink-500">ارجاع از {{ r.referred_by }}</div>
              <div v-if="r.note" class="mt-0.5 text-[11px] text-ink-500">« {{ r.note }} »</div>
              <div v-if="!r.is_department" class="mt-1 text-[11px]" :class="r.read ? 'text-teal-600' : 'text-ink-400'">
                {{ r.read ? '✓✓ خوانده شد' : '○ خوانده‌نشده' }}<span v-if="r.read_at" class="num"> · {{ r.read_at }}</span>
              </div>
            </div>
            <div class="flex flex-col items-end gap-1">
              <AppTag v-if="r.referral_label" :tone="r.referral_tone">{{ r.referral_label }}</AppTag>
              <AppTag v-else tone="neutral">گیرنده</AppTag>
            </div>
          </div>
        </div>
      </div>

      <div class="rounded-2xl border border-ink-200 bg-white p-4 shadow-card">
        <div class="mb-3 flex items-center gap-2 text-[13.5px] font-bold text-ink-900">
          <Icon name="chart" :size="16" /> زنجیرهٔ نامه
        </div>
        <div class="flex flex-col gap-1.5">
          <button
            v-for="t in thread"
            :key="t.id"
            @click="router.push({ name: 'letter', params: { id: t.id } })"
            class="rounded-xl border px-3 py-2.5 text-right transition"
            :class="t.id === letter.id ? 'border-blue-300 bg-blue-50' : 'border-ink-200 bg-white hover:bg-ink-50'"
            :style="{ marginInlineStart: (t.depth * 16) + 'px' }"
          >
            <div class="flex items-center gap-1.5">
              <span v-if="t.is_reply" class="text-ink-400">↳</span>
              <span class="truncate text-[12.5px] font-semibold" :class="t.id === letter.id ? 'text-brand-strong' : 'text-ink-800'">{{ t.subject }}</span>
            </div>
            <div class="num mt-1 flex items-center justify-between text-[11px] text-ink-500">
              <span>{{ t.from }}</span><span>{{ t.date }}</span>
            </div>
          </button>
          <div v-if="thread.length <= 1" class="px-1 py-2 text-[12px] text-ink-500">این نامه هنوز پاسخی ندارد.</div>
        </div>
      </div>

      <!-- Audit trail / history (تاریخچهٔ گردش) -->
      <div class="rounded-2xl border border-ink-200 bg-white p-4 shadow-card">
        <div class="mb-3 flex items-center gap-2 text-[13.5px] font-bold text-ink-900">
          <Icon name="clock" :size="16" /> رویدادهای نامه
        </div>
        <div class="flex flex-col">
          <div v-for="(h, i) in (letter.history || [])" :key="i" class="flex gap-2.5">
            <div class="flex flex-col items-center">
              <span class="mt-1 h-2.5 w-2.5 shrink-0 rounded-full" :style="{ background: h.action.tone === 'success' ? '#1aa06d' : h.action.tone === 'danger' ? '#e34d5f' : '#3467de' }"></span>
              <span v-if="i < letter.history.length - 1" class="my-0.5 w-px flex-1 bg-ink-200"></span>
            </div>
            <div class="pb-3">
              <div class="text-[12.5px] font-semibold text-ink-900">{{ h.action.label }}</div>
              <div class="text-[11.5px] text-ink-600">{{ h.user_name }}</div>
              <div class="num text-[11px] text-ink-400">{{ h.date }}</div>
              <div v-if="h.note" class="mt-1 rounded-lg bg-ink-50 px-2 py-1 text-[11.5px] text-ink-700">« {{ h.note }} »</div>
            </div>
          </div>
          <div v-if="!(letter.history && letter.history.length)" class="px-1 py-2 text-[12px] text-ink-500">تاریخچه‌ای ثبت نشده است.</div>
        </div>
      </div>
    </aside>
  </div>

  <ReferralModal v-if="showRefer && letter" :letter-id="letter.id" :saving="referring" @refer="doRefer" @close="showRefer = false" />
  <ReferralModal v-if="showReply && letter" :letter-id="letter.id" :saving="referring" mode="reply" :creator="{ id: letter.sender_id, name: letter.from }" @refer="doReply" @close="showReply = false" />
  <NoteEditorModal v-if="viewNote" :note="viewNote" :readonly="true" @close="viewNote = null" />

  <!-- attachment preview (PDF / images) -->
  <div v-if="preview" class="fixed inset-0 z-[95] flex items-center justify-center p-4 sm:p-6" style="background: rgba(13,21,38,0.62); backdrop-filter: blur(4px)" @click.self="closePreview">
    <div class="flex max-h-[92vh] w-[min(960px,100%)] flex-col overflow-hidden rounded-2xl bg-white shadow-pop">
      <div class="flex items-center justify-between gap-3 border-b border-ink-200 px-5 py-3">
        <b class="min-w-0 truncate text-[14px] text-ink-900">{{ preview.name }}</b>
        <div class="flex shrink-0 items-center gap-2">
          <button @click="downloadAttachment(preview.a)" class="h-8 rounded-[10px] px-3.5 text-[12.5px] font-semibold text-white shadow-brand" style="background: var(--grad-brand)">⬇ دانلود</button>
          <button @click="closePreview" class="text-[22px] leading-none text-ink-500">×</button>
        </div>
      </div>
      <div class="min-h-0 flex-1 overflow-auto bg-ink-100 p-3">
        <img v-if="preview.kind === 'image'" :src="preview.url" :alt="preview.name" class="mx-auto max-h-[80vh] rounded-lg bg-white shadow" />
        <iframe v-else :src="preview.url" class="h-[80vh] w-full rounded-lg border-0 bg-white" :title="preview.name"></iframe>
      </div>
    </div>
  </div>
</template>

<!-- Global (unscoped) print rules: print only the letterhead, at its own size. -->
<style>
/* Tables authored in the editor render inside the letter body + print. */
.af-letter-body table { border-collapse: collapse; width: 100%; margin: 8px 0; }
.af-letter-body td, .af-letter-body th { border: 1px solid #8a93a6; padding: 6px; }
.af-letter-body p { margin: 6px 0; }
@media print {
  body * { visibility: hidden !important; }
  #print-area, #print-area * { visibility: visible !important; }
  #print-area {
    position: absolute !important;
    top: 0; right: 0; left: 0; margin: 0 auto !important;
    box-shadow: none !important;
    border: none !important;
  }
}
</style>
