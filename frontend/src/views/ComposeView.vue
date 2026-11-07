<script setup>
import { ref, reactive, computed, onMounted, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { api } from '../services/api'
import { useAuthStore } from '../stores/auth'
import { useToastStore } from '../stores/toast'
import { todayJalaali } from '../utils/jalali'
import Icon from '../components/ui/Icon.vue'
import PeoplePicker from '../components/ui/PeoplePicker.vue'
import NoteEditorModal from '../components/modals/NoteEditorModal.vue'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const toast = useToastStore()

const paper = ref(null)
const fileInput = ref(null)
const active = reactive({})
const sending = ref(false)

const meta = ref({ recipients: [], all_users: [], departments: [], workflows: [], scopes: [], confidentialities: [], priorities: [] })
function fa(n) { return String(n).replace(/[0-9]/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]) }

const form = reactive({
  subject: '',
  scope: 'internal',
  recipient_ids: [], // people (multi)
  recipient_department_ids: [], // units (multi)
  cc_ids: [], // رونوشت (copy)
  external_recipient: '',
  confidentiality: 'normal',
  priority: 'normal',
  kind: 'action',
  letter_type: 'normal', // normal | workflow
  workflow_id: '',
  approver_ids: [], // workflow approver chain (ordered)
  paper_size: route.query.size === 'a5' ? 'a5' : 'a4',
  reply_to_id: route.query.reply_to || null,
})
const draftId = ref(route.query.draft || null)
const savingDraft = ref(false)

// نوع اقدام options (drives the received/sent sub-folders)
const KINDS = [
  { v: 'action', label: 'جهت اقدام' },
  { v: 'info', label: 'جهت اطلاع' },
  { v: 'sign', label: 'جهت امضا' },
  { v: 'reply', label: 'جهت پاسخ' },
  { v: 'followup', label: 'جهت پیگیری' },
]

// selection helpers for the add-a-recipient pickers
const pickPerson = ref('')
const pickDept = ref('')
const pickCc = ref('')
function addCc() { const id = pickCc.value; if (id && !form.cc_ids.includes(id) && !form.recipient_ids.includes(id)) form.cc_ids.push(id); pickCc.value = '' }
function removeCc(id) { form.cc_ids = form.cc_ids.filter((x) => x !== id) }
function approverName(id) {
  const u = meta.value.all_users?.find((x) => String(x.id) === String(id))
  return u ? `${u.name}${u.role ? ' — ' + u.role : ''}` : id
}
function removeApprover(id) { form.approver_ids = form.approver_ids.filter((x) => x !== id) }
function moveApprover(idx, dir) {
  const j = idx + dir
  if (j < 0 || j >= form.approver_ids.length) return
  const a = [...form.approver_ids]
  const [x] = a.splice(idx, 1); a.splice(j, 0, x)
  form.approver_ids = a
}

// Workflow sub-mode: «preset» (admin-defined fixed process) vs «custom» (sender
// builds the chain). Preset workflows carry a fixed approver chain; picking one
// locks the route.
const wfMode = ref('custom') // 'preset' | 'custom'
const presetWorkflows = computed(() => (meta.value.workflows || []).filter((w) => w.predefined))
const plainWorkflows = computed(() => (meta.value.workflows || []).filter((w) => !w.predefined))
const selectedWorkflow = computed(() => (meta.value.workflows || []).find((w) => String(w.id) === String(form.workflow_id)))
function setWfMode(m) {
  wfMode.value = m
  form.workflow_id = ''
  form.approver_ids = []
}
function onPresetChange() {
  const w = selectedWorkflow.value
  form.approver_ids = w && w.predefined ? w.approver_ids.map(String) : []
}

function addPerson() {
  const id = pickPerson.value
  if (id && !form.recipient_ids.includes(id)) form.recipient_ids.push(id)
  pickPerson.value = ''
}
function addDept() {
  const id = pickDept.value
  if (id && !form.recipient_department_ids.includes(id)) form.recipient_department_ids.push(id)
  pickDept.value = ''
}
function removePerson(id) { form.recipient_ids = form.recipient_ids.filter((x) => x !== id) }
function removeDept(id) { form.recipient_department_ids = form.recipient_department_ids.filter((x) => x !== id) }
// names known outside the allowed-recipients list (e.g. a reply target higher
// in the hierarchy) so their chip shows a name, not a raw id.
const extraNames = ref({})
function personName(id) {
  return meta.value.recipients.find((r) => String(r.id) === String(id))?.name
    || extraNames.value[String(id)] || id
}
function deptName(id) { return meta.value.departments.find((d) => String(d.id) === String(id))?.name || id }

const toPreview = computed(() => {
  if (form.scope === 'external') return form.external_recipient || '…'
  const names = [...form.recipient_ids.map(personName), ...form.recipient_department_ids.map(deptName)]
  return names.length ? names.join('، ') : '…'
})
const attachments = ref([]) // up to 10 × {attachment, attachment_path, attachment_mime, attachment_size}
const MAX_FILES = 10
const notes = ref([]) // up to 10 × {title, content}
const noteModal = ref(false)
const noteIndex = ref(null)
function openNote(i) { noteIndex.value = i; noteModal.value = true }
function fileIcon(n) {
  const name = (n.file_name || n.title || '').toLowerCase()
  if (/\.(xlsx|xls|csv)$/.test(name)) return '📊'
  if (/\.(docx?|rtf)$/.test(name)) return '📄'
  return '📎'
}
function saveNote(note) {
  if (noteIndex.value === null) notes.value.push(note)
  else notes.value[noteIndex.value] = note
  noteModal.value = false
}

// ── rich-text editor ─────────────────────────────────────────────
// Native <select>/<input type=color> steal focus from the contenteditable,
// which collapses the selection. We remember the last in-editor range and
// restore it before running a command so font/size/colour actually apply.
let savedRange = null
function saveSel() {
  const s = window.getSelection()
  if (s && s.rangeCount && paper.value && paper.value.contains(s.anchorNode)) {
    savedRange = s.getRangeAt(0).cloneRange()
  }
}
function restoreSel() {
  paper.value?.focus()
  if (savedRange) {
    const s = window.getSelection()
    s.removeAllRanges()
    s.addRange(savedRange)
  }
}
function exec(cmd, val = null) {
  paper.value?.focus()
  document.execCommand(cmd, false, val)
  saveSel()
  refreshState()
}
function refreshState() {
  saveSel()
  for (const c of ['bold', 'italic', 'underline', 'justifyRight', 'justifyCenter', 'justifyLeft']) {
    try { active[c] = document.queryCommandState(c) } catch { active[c] = false }
  }
}
function createLink() { const u = prompt('نشانی پیوند:', 'https://'); if (u) exec('createLink', u) }
// Insert an editable HTML table (rows × columns) at the cursor.
function insertTable() {
  const spec = prompt('تعداد سطر و ستون (مثال: ۳x۴):', '3x3')
  if (!spec) return
  const en = spec.replace(/[۰-۹]/g, (d) => '۰۱۲۳۴۵۶۷۸۹'.indexOf(d))
  const [rows, cols] = en.split(/[x×*,]/).map((n) => parseInt(n.trim(), 10))
  if (!rows || !cols || rows > 30 || cols > 12) { toast.show('ابعاد جدول نامعتبر است', 'danger'); return }
  let html = '<table style="border-collapse:collapse;width:100%;margin:8px 0" dir="rtl"><tbody>'
  for (let i = 0; i < rows; i++) {
    html += '<tr>'
    for (let j = 0; j < cols; j++) html += '<td style="border:1px solid #8a93a6;padding:6px;min-width:36px">&nbsp;</td>'
    html += '</tr>'
  }
  html += '</tbody></table><p><br></p>'
  restoreSel()
  document.execCommand('insertHTML', false, html)
  saveSel()
}
function printDoc() { window.print() }

// font family
function setFontName(e) {
  const v = e.target.value
  if (!v) return
  restoreSel()
  document.execCommand('fontName', false, v)
  saveSel()
  e.target.selectedIndex = 0
}
// font size in pixels (execCommand's fontSize is 1–7, so we tag then restyle)
function setFontSizePx(e) {
  const px = e.target.value
  if (!px) return
  restoreSel()
  document.execCommand('fontSize', false, '7')
  paper.value.querySelectorAll('font[size="7"]').forEach((f) => {
    const sp = document.createElement('span')
    sp.style.fontSize = px + 'px'
    while (f.firstChild) sp.appendChild(f.firstChild)
    f.parentNode.replaceChild(sp, f)
  })
  saveSel()
  e.target.selectedIndex = 0
}
// text colour
function setFore(e) { restoreSel(); document.execCommand('foreColor', false, e.target.value); saveSel() }
// text background / highlight colour
function setBack(e) {
  restoreSel()
  if (!document.execCommand('hiliteColor', false, e.target.value)) document.execCommand('backColor', false, e.target.value)
  saveSel()
}

const FONTS = [
  { v: 'IRANSans', t: 'ایران‌سنس' },
  { v: 'Vazirmatn', t: 'وزیرمتن' },
  { v: 'Tahoma', t: 'Tahoma' },
  { v: 'Arial', t: 'Arial' },
  { v: "'Times New Roman'", t: 'Times' },
]
const PX_SIZES = [10, 11, 12, 14, 16, 18, 20, 24, 28, 32]

// ── attachment upload ────────────────────────────────────────────
async function onFile(e) {
  const files = Array.from(e.target.files || [])
  for (const file of files) {
    if (attachments.value.length >= MAX_FILES) { toast.show(`حداکثر ${MAX_FILES} پیوست مجاز است.`, 'danger'); break }
    try {
      const up = await api.upload('/attachments', file)
      attachments.value.push(up)
    } catch (err) {
      toast.show(err.message, 'danger')
    }
  }
  e.target.value = ''
}
function removeAttachment(i) { attachments.value.splice(i, 1) }

// ── prefill: draft / reply / template ────────────────────────────
async function prefill() {
  if (draftId.value) {
    try {
      const src = await api.get(`/letters/${draftId.value}`)
      form.subject = src.subject
      form.scope = src.scope.value
      form.confidentiality = src.confidentiality.value
      form.priority = src.priority.value
      form.kind = src.kind?.value || 'action'
      form.paper_size = src.paper_size
      if (src.workflow_id) { form.workflow_id = String(src.workflow_id); form.letter_type = 'workflow' }
      if (src.scope.value === 'external') {
        form.external_recipient = src.external_recipient || ''
      } else {
        const recs = (src.recipients || []).filter((r) => r.kind === 'recipient')
        form.recipient_ids = recs.filter((r) => r.user_id).map((r) => String(r.user_id))
        form.recipient_department_ids = recs.filter((r) => r.department_id).map((r) => String(r.department_id))
      }
      attachments.value = (src.attachments || []).map((a) => ({
        attachment: a.attachment, attachment_path: a.attachment_path,
        attachment_mime: a.attachment_mime, attachment_size: a.attachment_size,
      }))
      if (paper.value) paper.value.innerHTML = src.body || ''
    } catch { /* ignore */ }
    return
  }
  if (form.reply_to_id) {
    try {
      const src = await api.get(`/letters/${form.reply_to_id}`)
      form.subject = src.subject.startsWith('پاسخ:') ? src.subject : `پاسخ: ${src.subject}`
      form.scope = src.scope.value
      if (src.scope.value === 'external') {
        form.external_recipient = src.external_recipient || src.from
      } else if (src.sender_id) {
        form.recipient_ids = [String(src.sender_id)]
        // Remember the sender's display name so the chip isn't a bare id.
        extraNames.value[String(src.sender_id)] = (src.from || '').split(' — ')[0] || src.from || `کاربر ${src.sender_id}`
      }
      if (src.workflow_id) { form.workflow_id = String(src.workflow_id); form.letter_type = 'workflow' }
      paper.value.innerText = `\nبازگشت به نامهٔ شمارهٔ ${src.number} با موضوع «${src.subject}».\n\n`
    } catch { /* ignore */ }
  } else if (route.query.template) {
    try {
      const data = await api.get('/templates')
      const tpl = data.items.find((t) => String(t.id) === String(route.query.template))
      if (tpl) {
        form.subject = tpl.title
        form.paper_size = tpl.paper_size
        paper.value.innerHTML = substituteVars(tpl.body)
      }
    } catch { /* ignore */ }
  }
}

// Replace template placeholders with the current context when a template loads.
function substituteVars(html) {
  const fa = (s) => String(s).replace(/[0-9]/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d])
  const today = (() => { const t = todayJalaali(); return fa(`${t.jy}/${String(t.jm).padStart(2, '0')}/${String(t.jd).padStart(2, '0')}`) })()
  const recipientNames = form.recipient_ids.map(personName).join('، ')
  const map = {
    '{{تاریخ}}': today,
    '{{فرستنده}}': auth.user?.name || '',
    '{{سمت}}': auth.user?.role?.name || '',
    '{{واحد}}': auth.user?.department?.name || '',
    '{{گیرنده}}': recipientNames || '…',
    '{{موضوع}}': form.subject || '',
  }
  return html.replace(/\{\{[^}]+\}\}/g, (m) => (m in map ? map[m] : m))
}

const workflowSteps = computed(() => {
  const wf = meta.value.workflows.find((w) => String(w.id) === String(form.workflow_id))
  return wf ? wf.steps : 0
})

function validate() {
  if (!form.subject.trim()) return 'موضوع نامه را وارد کنید.'
  if (form.letter_type === 'workflow') {
    if (!form.workflow_id) return 'برای نامهٔ گردش‌کاری، یک گردش کار انتخاب کنید.'
    if (!form.approver_ids.length) return 'حداقل یک تأییدکننده به مسیر تأیید اضافه کنید.'
    return null
  }
  if (form.scope === 'external') {
    if (!form.external_recipient.trim()) return 'گیرندهٔ خارج‌سازمانی را وارد کنید.'
  } else if (!form.recipient_ids.length && !form.recipient_department_ids.length) {
    return 'حداقل یک گیرنده (شخص یا واحد) انتخاب کنید.'
  }
  return null
}

function openSend() {
  const err = validate()
  if (err) { toast.show(err, 'danger'); return }
  // Paper size was already chosen (A4/A5 toggle) — send directly, no extra modal.
  confirmSend(form.paper_size)
}

// Build the request body from the current form state.
function buildPayload(size, asDraft) {
  const payload = {
    subject: form.subject,
    // Save the rich HTML so tables, fonts and colours survive round-trips.
    body: (paper.value?.innerHTML || '').trim(),
    paper_size: size,
    scope: form.scope,
    confidentiality: form.confidentiality,
    priority: form.priority,
    kind: form.kind,
    workflow_id: form.letter_type === 'workflow' ? (form.workflow_id || null) : null,
    reply_to_id: form.reply_to_id || null,
    as_draft: asDraft,
  }
  if (form.letter_type === 'workflow') {
    // Workflow letters route via the approver chain (not a recipient list).
    payload.approver_ids = form.approver_ids.map((x) => Number(x))
  } else if (form.scope === 'external') {
    payload.external_recipient = form.external_recipient
  } else {
    payload.recipient_ids = form.recipient_ids
    payload.recipient_department_ids = form.recipient_department_ids
    payload.cc_ids = form.cc_ids
  }
  payload.attachments = attachments.value
  payload.notes = notes.value
  return payload
}

async function saveDraft() {
  if (savingDraft.value) return
  if (!form.subject.trim()) { toast.show('برای ذخیرهٔ پیش‌نویس حداقل موضوع را وارد کنید.', 'danger'); return }
  savingDraft.value = true
  const payload = buildPayload(form.paper_size, true)
  try {
    if (draftId.value) {
      await api.put(`/letters/${draftId.value}`, payload)
    } else {
      const created = await api.post('/letters', payload)
      draftId.value = created.id
    }
    toast.show('پیش‌نویس ذخیره شد')
  } catch (e) {
    toast.show(e.message, 'danger')
  } finally {
    savingDraft.value = false
  }
}

async function confirmSend(size) {
  if (sending.value) return
  sending.value = true
  form.paper_size = size
  const payload = buildPayload(size, false)

  try {
    // Finalizing an existing draft vs. sending a brand-new letter.
    if (draftId.value) await api.put(`/letters/${draftId.value}`, payload)
    else await api.post('/letters', payload)
    toast.show(`نامه با موفقیت ارسال شد (${size.toUpperCase()})`)
    router.push({ name: 'cartable', query: { tab: 'out' } })
  } catch (e) {
    toast.show(e.message, 'danger')
  } finally {
    sending.value = false
  }
}

onMounted(async () => {
  try {
    meta.value = await api.get('/meta/compose')
  } catch { /* ignore */ }
  await nextTick()
  await prefill()
  refreshState()
})
</script>

<template>
  <div class="flex h-screen flex-col" style="background: var(--color-ink-150)">
    <!-- docbar -->
    <div class="flex items-center gap-2.5 border-b border-ink-200 bg-white px-4 py-2.5">
      <button @click="router.back()" class="h-9 rounded-[10px] border border-ink-200 bg-white px-3 text-[13px] text-ink-800">→ انصراف</button>
      <div class="flex items-center gap-2.5">
        <img src="/assets/logo-mark.png" class="h-[26px]" />
        <b class="text-brand-strong">نگارش نامه — گروه متحد 7</b>
      </div>
      <div class="h-6 w-px bg-ink-200"></div>
      <input v-model="form.subject" placeholder="موضوع نامه…"
        class="h-9 max-w-[320px] flex-1 rounded-[10px] border border-ink-200 bg-ink-50 px-3 text-[13px]" />
      <div class="flex-1"></div>
      <button @click="printDoc" class="h-9 rounded-[10px] border border-ink-200 bg-white px-3 text-[12.5px] text-ink-800">🖶 چاپ</button>
      <button @click="saveDraft" :disabled="savingDraft" class="h-9 rounded-[10px] border border-ink-300 bg-white px-3 text-[12.5px] font-semibold text-ink-800 disabled:opacity-60">
        {{ savingDraft ? 'در حال ذخیره…' : '❑ ذخیره پیش‌نویس' }}
      </button>
      <button @click="openSend" class="h-9 rounded-[10px] px-4 text-[13px] font-semibold text-white shadow-brand" style="background: var(--grad-brand); padding-inline: 18px">➤ ارسال</button>
    </div>

    <div class="af-scroll flex flex-1 flex-col gap-4 overflow-auto p-4 lg:flex-row">
      <!-- metadata form -->
      <aside class="flex w-full shrink-0 flex-col gap-3.5 rounded-2xl border border-ink-200 bg-white p-4 shadow-card lg:w-[330px]">
        <div class="text-[13.5px] font-bold text-ink-900">مشخصات نامه</div>

        <div class="rounded-xl bg-ink-50 px-3 py-2.5">
          <div class="text-[11px] text-ink-500">فرستنده</div>
          <div class="mt-0.5 text-[13px] font-semibold text-ink-900">{{ auth.user?.name }} — {{ auth.user?.role?.name }}</div>
        </div>

        <label class="flex flex-col gap-1">
          <span class="text-[12px] text-ink-600">دامنهٔ نامه</span>
          <select v-model="form.scope" class="af-field">
            <option v-for="s in meta.scopes" :key="s.value" :value="s.value">{{ s.label }}</option>
          </select>
        </label>

        <!-- recipient(s) — only for normal letters; workflow letters route via
             the approver chain below -->
        <div v-if="form.letter_type === 'normal'" class="flex flex-col gap-1.5">
          <span class="text-[12px] text-ink-600">گیرندگان</span>
          <template v-if="form.scope === 'external'">
            <input v-model="form.external_recipient" placeholder="نام سازمان/شخص خارج‌سازمانی…" class="af-field" />
          </template>
          <template v-else>
            <!-- persons (typeable search by name / surname / سمت) -->
            <PeoplePicker :people="meta.recipients" :exclude="form.recipient_ids"
              placeholder="جستجوی گیرنده: نام، نام خانوادگی یا سمت…" @pick="(id) => { if (!form.recipient_ids.includes(String(id))) form.recipient_ids.push(String(id)) }" />
            <!-- units -->
            <div class="flex gap-1.5">
              <select v-model="pickDept" class="af-field flex-1">
                <option value="">— افزودن واحد —</option>
                <option v-for="d in meta.departments" :key="d.id" :value="String(d.id)" :disabled="form.recipient_department_ids.includes(String(d.id))">{{ d.name }}</option>
              </select>
              <button @click="addDept" :disabled="!pickDept" class="shrink-0 rounded-lg border border-teal-300 bg-teal-50 px-3 text-[13px] font-semibold text-teal-700 disabled:opacity-40">افزودن</button>
            </div>
            <!-- chips -->
            <div v-if="form.recipient_ids.length || form.recipient_department_ids.length" class="flex flex-wrap gap-1.5 pt-0.5">
              <span v-for="id in form.recipient_ids" :key="'p'+id" class="inline-flex items-center gap-1 rounded-full bg-blue-50 py-1 pr-2.5 pl-1 text-[12px] text-brand-strong">
                {{ personName(id) }}
                <button @click="removePerson(id)" class="flex h-4 w-4 items-center justify-center rounded-full bg-white text-[11px] text-ink-500">×</button>
              </span>
              <span v-for="id in form.recipient_department_ids" :key="'d'+id" class="inline-flex items-center gap-1 rounded-full bg-teal-50 py-1 pr-2.5 pl-1 text-[12px] text-teal-700">
                ⛋ {{ deptName(id) }}
                <button @click="removeDept(id)" class="flex h-4 w-4 items-center justify-center rounded-full bg-white text-[11px] text-ink-500">×</button>
              </span>
            </div>

            <!-- رونوشت (CC) -->
            <div class="mt-1 flex flex-col gap-1.5 rounded-lg border border-dashed border-ink-200 p-2">
              <span class="text-[11.5px] text-ink-500">رونوشت (رو‌نوشت به سایر افراد):</span>
              <PeoplePicker :people="meta.recipients" :exclude="[...form.cc_ids, ...form.recipient_ids]"
                placeholder="جستجوی رونوشت…" @pick="(id) => { if (!form.cc_ids.includes(String(id)) && !form.recipient_ids.includes(String(id))) form.cc_ids.push(String(id)) }" />
              <div v-if="form.cc_ids.length" class="flex flex-wrap gap-1.5">
                <span v-for="id in form.cc_ids" :key="'cc'+id" class="inline-flex items-center gap-1 rounded-full bg-ink-100 py-1 pr-2.5 pl-1 text-[12px] text-ink-700">
                  رونوشت: {{ personName(id) }}
                  <button @click="removeCc(id)" class="flex h-4 w-4 items-center justify-center rounded-full bg-white text-[11px] text-ink-500">×</button>
                </span>
              </div>
            </div>
          </template>
        </div>

        <div class="grid grid-cols-2 gap-2.5">
          <label class="flex flex-col gap-1">
            <span class="text-[12px] text-ink-600">محرمانگی</span>
            <select v-model="form.confidentiality" class="af-field">
              <option v-for="c in meta.confidentialities" :key="c.value" :value="c.value">{{ c.label }}</option>
            </select>
          </label>
          <label class="flex flex-col gap-1">
            <span class="text-[12px] text-ink-600">اولویت</span>
            <select v-model="form.priority" class="af-field">
              <option v-for="p in meta.priorities" :key="p.value" :value="p.value">{{ p.label }}</option>
            </select>
          </label>
          <label class="flex flex-col gap-1">
            <span class="text-[12px] text-ink-600">نوع اقدام</span>
            <select v-model="form.kind" class="af-field">
              <option v-for="k in KINDS" :key="k.v" :value="k.v">{{ k.label }}</option>
            </select>
          </label>
        </div>

        <!-- letter type -->
        <div class="flex flex-col gap-1.5">
          <span class="text-[12px] text-ink-600">نوع نامه</span>
          <div class="flex gap-1.5 rounded-lg bg-ink-100 p-1 text-[12px]">
            <button @click="form.letter_type = 'normal'" class="flex-1 rounded-md py-1.5" :class="form.letter_type === 'normal' ? 'bg-white font-semibold text-brand-strong shadow-card' : 'text-ink-600'">عادی</button>
            <button @click="form.letter_type = 'workflow'" class="flex-1 rounded-md py-1.5" :class="form.letter_type === 'workflow' ? 'bg-white font-semibold text-brand-strong shadow-card' : 'text-ink-600'">گردش‌کاری</button>
          </div>
          <span class="text-[11px] text-ink-400">{{ form.letter_type === 'normal' ? 'مستقیم به گیرنده تحویل می‌شود.' : 'در گردش کار تأیید/امضا قرار می‌گیرد.' }}</span>
        </div>

        <template v-if="form.letter_type === 'workflow'">
          <!-- sub-mode: predefined admin process vs sender-defined chain -->
          <div class="flex flex-col gap-1.5">
            <span class="text-[12px] text-ink-600">نوع گردش کار</span>
            <div class="flex gap-1.5 rounded-lg bg-ink-100 p-1 text-[12px]">
              <button type="button" @click="setWfMode('preset')" class="flex-1 rounded-md py-1.5" :class="wfMode === 'preset' ? 'bg-white font-semibold text-brand-strong shadow-card' : 'text-ink-600'">فرایند آماده (ادمین)</button>
              <button type="button" @click="setWfMode('custom')" class="flex-1 rounded-md py-1.5" :class="wfMode === 'custom' ? 'bg-white font-semibold text-brand-strong shadow-card' : 'text-ink-600'">تعیین دستی مسیر</button>
            </div>
          </div>

          <!-- PRESET: pick an admin-defined process; the chain is fixed -->
          <template v-if="wfMode === 'preset'">
            <label class="flex flex-col gap-1">
              <span class="text-[12px] text-ink-600">فرایند از پیش تعریف‌شده</span>
              <select v-model="form.workflow_id" class="af-field" @change="onPresetChange">
                <option value="">— انتخاب فرایند —</option>
                <option v-for="w in presetWorkflows" :key="w.id" :value="String(w.id)">{{ w.name }}</option>
              </select>
            </label>
            <div v-if="!presetWorkflows.length" class="rounded-lg border border-warning-500 bg-warning-50 px-3 py-2 text-[12px] text-warning-600">
              هنوز فرایند آماده‌ای توسط مدیر تعریف نشده است. از حالت «تعیین دستی مسیر» استفاده کنید.
            </div>
            <div v-if="selectedWorkflow && selectedWorkflow.predefined" class="flex flex-col gap-1.5">
              <span class="text-[12px] text-ink-600">مسیر تأیید (ثابت — تعیین‌شده توسط مدیر)</span>
              <div v-for="(name, idx) in selectedWorkflow.approver_names" :key="idx" class="flex items-center gap-2 rounded-lg border border-blue-200 bg-blue-50 px-2.5 py-1.5">
                <span class="num flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-[11px] font-bold text-white" style="background: var(--grad-brand)">{{ fa(idx + 1) }}</span>
                <span class="min-w-0 flex-1 truncate text-[12.5px] text-ink-900">{{ name }}</span>
                <span class="text-[13px] text-brand-strong">🔒</span>
              </div>
              <span class="text-[11px] text-ink-400">این مسیر ثابت است و برای همه یکسان اجرا می‌شود؛ با رد شدن در هر مرحله نامه متوقف می‌شود.</span>
            </div>
          </template>

          <!-- CUSTOM: sender builds the chain (workflow is just a label) -->
          <template v-else>
            <label class="flex flex-col gap-1">
              <span class="text-[12px] text-ink-600">گردش کار (برچسب)</span>
              <select v-model="form.workflow_id" class="af-field">
                <option value="">— انتخاب گردش کار —</option>
                <option v-for="w in plainWorkflows" :key="w.id" :value="String(w.id)">{{ w.name }}</option>
              </select>
            </label>
            <div class="flex flex-col gap-1.5">
              <span class="text-[12px] text-ink-600">مسیر تأیید (به ترتیب اولویت)</span>
              <PeoplePicker :people="meta.all_users" :exclude="form.approver_ids"
                placeholder="＋ افزودن تأییدکننده (جستجوی همهٔ کاربران)…"
                @pick="(id) => { if (!form.approver_ids.includes(String(id))) form.approver_ids.push(String(id)) }" />
              <div v-if="form.approver_ids.length" class="flex flex-col gap-1.5">
                <div v-for="(id, idx) in form.approver_ids" :key="id"
                  class="flex items-center gap-2 rounded-lg border border-ink-200 bg-ink-50 px-2.5 py-1.5">
                  <span class="num flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-[11px] font-bold text-white" style="background: var(--grad-brand)">{{ fa(idx + 1) }}</span>
                  <span class="min-w-0 flex-1 truncate text-[12.5px] text-ink-900">{{ approverName(id) }}</span>
                  <button @click="moveApprover(idx, -1)" :disabled="idx === 0" class="h-6 w-6 rounded text-ink-500 disabled:opacity-30" title="بالا">▲</button>
                  <button @click="moveApprover(idx, 1)" :disabled="idx === form.approver_ids.length - 1" class="h-6 w-6 rounded text-ink-500 disabled:opacity-30" title="پایین">▼</button>
                  <button @click="removeApprover(id)" class="text-[15px] text-danger-500" title="حذف">×</button>
                </div>
              </div>
              <span class="text-[11px] text-ink-400">هر تأییدکننده به ترتیب اقدام می‌کند؛ با رد شدن در هر مرحله، نامه متوقف می‌شود.</span>
            </div>
          </template>
        </template>

        <!-- attachments (up to 10) -->
        <div class="flex flex-col gap-1.5">
          <span class="text-[12px] text-ink-600">پیوست‌ها <span class="num text-ink-400">({{ attachments.length }}/{{ MAX_FILES }})</span></span>
          <input ref="fileInput" type="file" multiple class="hidden" @change="onFile" />
          <button v-if="attachments.length < MAX_FILES" @click="fileInput.click()" class="flex items-center justify-center gap-2 rounded-xl border border-dashed border-blue-300 bg-blue-50 py-2.5 text-[13px] font-semibold text-brand-strong">
            <Icon name="paperclip" :size="15" /> افزودن فایل
          </button>
          <div v-for="(a, i) in attachments" :key="i" class="flex items-center justify-between gap-2 rounded-xl border border-ink-200 bg-ink-50 px-3 py-2">
            <span class="min-w-0 flex-1 truncate text-[12.5px] text-ink-800"><Icon name="paperclip" :size="13" class="inline" /> {{ a.attachment }}</span>
            <button @click="removeAttachment(i)" class="text-[15px] text-danger-500">×</button>
          </div>
          <span class="text-[11px] text-ink-400">Word، Excel، PDF، PowerPoint، متن و تصاویر پشتیبانی می‌شود.</span>
        </div>

        <!-- editable Office files (Word/Excel) — open, edit, save into the letter -->
        <div class="flex flex-col gap-1.5">
          <span class="text-[12px] text-ink-600">فایل‌های پیوستی <span class="num text-ink-400">({{ notes.length }}/10)</span></span>
          <button v-if="notes.length < 10" @click="openNote(null)" class="flex items-center justify-center gap-2 rounded-xl border border-dashed border-blue-300 bg-blue-50 py-2.5 text-[13px] font-semibold text-brand-strong">
            <Icon name="paperclip" :size="15" /> افزودن فایل Word یا Excel (قابل ویرایش)
          </button>
          <div v-for="(n, i) in notes" :key="i" class="flex items-center justify-between gap-2 rounded-xl border border-ink-200 bg-ink-50 px-3 py-2">
            <button @click="openNote(i)" class="flex min-w-0 flex-1 items-center gap-1.5 truncate text-right text-[12.5px] font-semibold text-ink-800">
              <span>{{ fileIcon(n) }}</span>
              <span class="min-w-0 flex-1 truncate">{{ n.title }}</span>
            </button>
            <button @click="notes.splice(i, 1)" class="text-[15px] text-danger-500">×</button>
          </div>
          <span class="text-[11px] text-ink-400">فایل انتخاب و ویرایش می‌شود، سپس همراه نامه به گیرندگان تحویل داده می‌شود (عادی و گردش‌کاری).</span>
        </div>
      </aside>

      <!-- letter paper -->
      <div class="flex flex-1 flex-col items-center gap-2">
        <!-- toolbar -->
        <div class="flex w-full max-w-[210mm] flex-wrap items-center gap-0.5 rounded-xl border border-ink-200 bg-white px-2 py-1.5" @mousedown.prevent>
          <button class="af-tb" title="واگرد" @click="exec('undo')">↶</button>
          <button class="af-tb" title="ازنو" @click="exec('redo')">↷</button>
          <span class="mx-1 h-[22px] w-px bg-ink-200"></span>
          <button class="af-tb" :class="{ on: active.bold }" title="ضخیم" @click="exec('bold')"><b>B</b></button>
          <button class="af-tb" :class="{ on: active.italic }" title="مورب" @click="exec('italic')"><i>I</i></button>
          <button class="af-tb" :class="{ on: active.underline }" title="زیرخط" @click="exec('underline')"><u>U</u></button>
          <span class="mx-1 h-[22px] w-px bg-ink-200"></span>
          <button class="af-tb" :class="{ on: active.justifyRight }" title="راست‌چین" @click="exec('justifyRight')">▤</button>
          <button class="af-tb" :class="{ on: active.justifyCenter }" title="وسط‌چین" @click="exec('justifyCenter')">▥</button>
          <button class="af-tb" :class="{ on: active.justifyLeft }" title="چپ‌چین" @click="exec('justifyLeft')">▦</button>
          <button class="af-tb" title="تراز دوطرفه" @click="exec('justifyFull')">▧</button>
          <span class="mx-1 h-[22px] w-px bg-ink-200"></span>
          <button class="af-tb" title="فهرست نقطه‌ای" @click="exec('insertUnorderedList')">•≡</button>
          <button class="af-tb" title="فهرست شماره‌ای" @click="exec('insertOrderedList')">1≡</button>
          <button class="af-tb" title="پیوند" @click="createLink">🔗</button>
          <button class="af-tb" title="درج جدول" @mousedown.stop @click="insertTable">▦T</button>
          <button class="af-tb" title="خط جداکننده" @click="exec('insertHorizontalRule')">―</button>
          <button class="af-tb" title="پاک کردن قالب" @click="exec('removeFormat')">⌫</button>
          <span class="mx-1 h-[22px] w-px bg-ink-200"></span>
          <select class="af-sel" title="قلم" @mousedown.stop @change="setFontName"><option value="" disabled selected>قلم</option><option v-for="f in FONTS" :key="f.v" :value="f.v">{{ f.t }}</option></select>
          <select class="af-sel" title="اندازه (px)" @mousedown.stop @change="setFontSizePx"><option value="" disabled selected>px</option><option v-for="s in PX_SIZES" :key="s" :value="s">{{ s }}</option></select>
          <label class="af-tb relative" title="رنگ متن" @mousedown.stop><span style="border-bottom:3px solid #d33">A</span><input type="color" class="absolute inset-0 cursor-pointer opacity-0" @input="setFore" /></label>
          <label class="af-tb relative" title="رنگ پس‌زمینهٔ متن" @mousedown.stop><span style="background:#ffe08a;padding:0 3px;border-radius:2px">A</span><input type="color" value="#ffe08a" class="absolute inset-0 cursor-pointer opacity-0" @input="setBack" /></label>
          <div class="flex-1"></div>
          <div class="flex overflow-hidden rounded-[9px] border border-ink-200">
            <button @click="form.paper_size = 'a4'" class="px-3 py-1 text-xs" :style="form.paper_size === 'a4' ? 'background:var(--color-brand);color:#fff' : 'background:#fff'">A4</button>
            <button @click="form.paper_size = 'a5'" class="border-r border-ink-200 px-3 py-1 text-xs" :style="form.paper_size === 'a5' ? 'background:var(--color-brand);color:#fff' : 'background:#fff'">A5</button>
          </div>
        </div>

        <!-- paper -->
        <div class="w-full pb-4">
          <div class="mx-auto bg-white text-[12pt] leading-loose text-[#111] shadow-pop"
            :style="{ width: form.paper_size === 'a5' ? '148mm' : '210mm', minHeight: form.paper_size === 'a5' ? '210mm' : '297mm', padding: '22mm 20mm', border: '1px solid #ccc' }">
            <div class="mb-4 flex items-start justify-between gap-4">
              <div class="flex items-center gap-2.5">
                <img src="/assets/logo-mark.png" :style="{ height: form.paper_size === 'a5' ? '34px' : '44px' }" />
                <div>
                  <div style="font-weight:800;color:#2340a4;white-space:nowrap" :style="{ fontSize: form.paper_size === 'a5' ? '14pt' : '20pt' }">گروه متحد 7</div>
                  <div style="font-size:10.5pt;color:#444;margin-top:2px">آرتا قلب بهسان طب</div>
                </div>
              </div>
              <table style="border-collapse:collapse;font-size:10.5pt;min-width:180px">
                <tr><td style="border:1px solid #333;padding:3px 8px;background:#f3f3f3;white-space:nowrap">شماره:</td><td style="border:1px solid #333;padding:3px 8px">پس از ثبت</td></tr>
                <tr><td style="border:1px solid #333;padding:3px 8px;background:#f3f3f3">تاریخ:</td><td style="border:1px solid #333;padding:3px 8px">هنگام ارسال</td></tr>
                <tr><td style="border:1px solid #333;padding:3px 8px;background:#f3f3f3">پیوست:</td><td style="border:1px solid #333;padding:3px 8px">{{ attachments.length ? ('دارد (' + attachments.length + ')') : 'ندارد' }}</td></tr>
              </table>
            </div>
            <p style="margin:18px 0 4px">{{ toPreview }}</p>
            <p style="margin:6px 0"><b>موضوع:</b> {{ form.subject || '…' }}</p>
            <div ref="paper" id="af-paper" class="af-paper min-h-[140mm] outline-none" contenteditable="true" spellcheck="false"
              @keyup="refreshState" @mouseup="refreshState">
              <p>با سلام و احترام؛</p>
              <p>احتراماً، به استحضار می‌رساند …</p>
              <p><br /></p>
              <p>با تشکر — {{ auth.user?.name }}</p>
            </div>
            <!-- signature (borderless, larger, flush-left) -->
            <div style="margin-top:34px;display:flex;justify-content:flex-end">
              <div style="min-width:150px;text-align:center">
                <div style="color:#888;font-size:9.5pt">تنظیم‌کننده</div>
                <img v-if="auth.user?.signature_url" :src="auth.user.signature_url" alt="امضا" style="max-height:66px;margin:4px auto" />
                <div v-else style="height:44px"></div>
                <div style="font-weight:bold;font-size:11.5pt">{{ auth.user?.name }}</div>
                <div style="color:#555;font-size:10pt">{{ auth.user?.role?.name }}</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <NoteEditorModal v-if="noteModal" :note="noteIndex !== null ? notes[noteIndex] : null" @save="saveNote" @close="noteModal = false" />
</template>

<style scoped>
.af-field {
  height: 38px;
  border: 1px solid var(--color-ink-200);
  border-radius: 10px;
  padding: 0 10px;
  font-size: 13px;
  background: #fff;
  color: var(--color-ink-800);
}
.af-tb {
  display: inline-flex; align-items: center; justify-content: center;
  min-width: 30px; height: 30px; padding: 0 6px;
  border: 1px solid transparent; background: transparent; border-radius: 8px;
  cursor: pointer; color: var(--color-ink-700); font-size: 14px; line-height: 1; user-select: none;
}
.af-tb:hover { background: var(--color-ink-100); border-color: var(--color-ink-200); }
.af-tb.on { background: var(--color-blue-50); border-color: var(--color-blue-300); color: var(--color-brand-strong); }
.af-sel {
  height: 30px; border: 1px solid var(--color-ink-200); background: #fff;
  border-radius: 8px; padding: 0 6px; font-size: 12px; cursor: pointer; color: var(--color-ink-700);
}
</style>
