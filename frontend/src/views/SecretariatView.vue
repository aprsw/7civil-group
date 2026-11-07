<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '../services/api'
import { useToastStore } from '../stores/toast'
import { useAuthStore } from '../stores/auth'
import AppCard from '../components/ui/AppCard.vue'
import AppTag from '../components/ui/AppTag.vue'
import Icon from '../components/ui/Icon.vue'

const router = useRouter()
const toast = useToastStore()
const auth = useAuthStore()

const box = ref('in')
const items = ref([])
const meta = ref({ recipients: [], priorities: [], referral_types: [] })
const showForm = ref(false)
const saving = ref(false)
const pendingFiles = ref([]) // up to 10 scanned attachments
const MAX_FILES = 10
const fileInput = ref(null)

const KINDS = [
  { v: 'action', label: 'جهت اقدام' }, { v: 'info', label: 'جهت اطلاع' },
  { v: 'sign', label: 'جهت امضا' }, { v: 'reply', label: 'جهت پاسخ' }, { v: 'followup', label: 'جهت پیگیری' },
]

const form = reactive({ direction: 'in', number: '', external_party: '', subject: '', body: '', kind: 'action', priority: 'normal', recipient_ids: [], folder_id: '' })

// Registration number is typed by the clerk; the system only guarantees it is
// unique and shows the last number that was registered.
const lastNumber = ref({ in: null, out: null, any: null })
const numberState = ref('') // '' | checking | free | taken
let numberCheckTimer = null
const lastForForm = computed(() => lastNumber.value[form.direction] || null)
const FA_DIGITS = '۰۱۲۳۴۵۶۷۸۹'
function toLatin(s) { return String(s ?? '').replace(/[۰-۹]/g, (d) => String(FA_DIGITS.indexOf(d))).replace(/[٠-٩]/g, (d) => String('٠١٢٣٤٥٦٧٨٩'.indexOf(d))) }
// Suggest the next number by incrementing the trailing digit run of the last one
// (e.g. ۵/۱/۷۶۷۲۰ → ۵/۱/۷۶۷۲۱). Returns '' when there is nothing to increment.
function suggestNext(last) {
  const latin = toLatin(last || '')
  const m = latin.match(/^(.*?)(\d+)(\D*)$/)
  if (!m) return ''
  const next = String(BigInt(m[2]) + 1n).padStart(m[2].length, '0')
  return fa(m[1] + next + m[3])
}
const suggestedNumber = computed(() => suggestNext(lastForForm.value || lastNumber.value.any))
function useSuggested() { if (suggestedNumber.value) { form.number = suggestedNumber.value; checkNumber() } }
async function checkNumber() {
  clearTimeout(numberCheckTimer)
  const n = form.number.trim()
  if (!n) { numberState.value = ''; return }
  numberState.value = 'checking'
  numberCheckTimer = setTimeout(async () => {
    try {
      const res = await api.get(`/secretariat/check-number?number=${encodeURIComponent(n)}`)
      if (form.number.trim() === n) numberState.value = res.available ? 'free' : 'taken'
    } catch { numberState.value = '' }
  }, 350)
}

// Folders (بخش‌بندی): defined by the admin only; clerks may file letters under them.
const canManageFolders = ref(auth.can('secretariat.manage') || auth.can('roles.manage'))
const folders = ref([])
const activeFolder = ref(null) // null = همه
const visibleItems = computed(() => activeFolder.value === null
  ? items.value
  : items.value.filter((l) => Number(l.folder_id) === Number(activeFolder.value)))
function folderName(id) { return folders.value.find((f) => Number(f.id) === Number(id))?.name || '' }

function fa(n) { return String(n).replace(/[0-9]/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]) }

async function load() {
  const data = await api.get(`/secretariat?box=${box.value}`)
  items.value = data.items
  folders.value = data.folders || []
  lastNumber.value = { ...lastNumber.value, [box.value]: data.last_number || null, any: data.last_number_any || null }
  if (typeof data.can_manage_folders === 'boolean') canManageFolders.value = data.can_manage_folders
}
async function refreshLastNumber(dir) {
  try {
    const res = await api.get(`/secretariat/last-number?box=${dir}`)
    lastNumber.value = { ...lastNumber.value, [dir]: res.last_number || null, any: res.last_number_any || null }
  } catch { /* ignore */ }
}
async function createFolder() {
  const name = prompt('نام بخش جدید:')?.trim()
  if (!name) return
  try { await api.post('/secretariat/folders', { name }); toast.show('پوشه ساخته شد'); await load() }
  catch (e) { toast.show(e.message, 'danger') }
}
async function renameFolder(f) {
  const name = prompt('نام جدید بخش:', f.name)?.trim()
  if (!name || name === f.name) return
  try { await api.put(`/secretariat/folders/${f.id}`, { name }); toast.show('ویرایش شد'); await load() }
  catch (e) { toast.show(e.message, 'danger') }
}
async function deleteFolder(f) {
  if (!confirm(`بخش «${f.name}» حذف شود؟ نامه‌های داخلش حذف نمی‌شوند و بدون بخش می‌مانند.`)) return
  try {
    await api.del(`/secretariat/folders/${f.id}`)
    if (Number(activeFolder.value) === Number(f.id)) activeFolder.value = null
    toast.show('حذف شد'); await load()
  } catch (e) { toast.show(e.message, 'danger') }
}
async function moveToFolder(letter, folderId) {
  try { await api.put(`/secretariat/${letter.id}/folder`, { folder_id: folderId || null }); await load() }
  catch (e) { toast.show(e.message, 'danger') }
}
async function loadMeta() {
  try { meta.value = await api.get('/meta/compose') } catch { /* ignore */ }
}
function openForm(dir) {
  form.direction = dir
  form.number = ''; numberState.value = ''
  form.external_party = ''; form.subject = ''; form.body = ''; form.kind = 'action'; form.priority = 'normal'; form.recipient_ids = []
  form.folder_id = activeFolder.value || ''
  pendingFiles.value = []
  showForm.value = true
  refreshLastNumber(dir)
}
async function pickScan(e) {
  const files = Array.from(e.target.files || [])
  for (const file of files) {
    if (pendingFiles.value.length >= MAX_FILES) { toast.show(`حداکثر ${MAX_FILES} پیوست مجاز است`, 'danger'); break }
    try { pendingFiles.value.push(await api.upload('/attachments', file)) }
    catch (err) { toast.show(err.message, 'danger') }
  }
  if (files.length) toast.show('اسکن پیوست شد')
  e.target.value = ''
}
function removeScan(i) { pendingFiles.value.splice(i, 1) }
async function submit() {
  if (!form.number.trim()) { toast.show('شمارهٔ نامه را وارد کنید', 'danger'); return }
  if (numberState.value === 'taken') { toast.show('این شماره قبلاً ثبت شده است؛ شمارهٔ دیگری وارد کنید', 'danger'); return }
  if (!form.external_party.trim()) { toast.show('نام طرف خارج از سازمان را وارد کنید', 'danger'); return }
  if (form.direction === 'in' && !form.recipient_ids.length) { toast.show('گیرندهٔ داخلی را انتخاب کنید', 'danger'); return }
  saving.value = true
  try {
    const payload = {
      number: form.number.trim(),
      external_party: form.external_party, subject: form.subject, body: form.body,
      kind: form.kind, priority: form.priority,
      attachments: pendingFiles.value,
      folder_id: form.folder_id || null,
    }
    if (form.direction === 'in') payload.recipient_ids = form.recipient_ids
    const url = form.direction === 'in' ? '/secretariat/incoming' : '/secretariat/outgoing'
    const res = await api.post(url, payload)
    toast.show(`ثبت شد — شمارهٔ ${res.number || form.number}`)
    showForm.value = false
    box.value = form.direction
    await load()
  } catch (e) {
    toast.show(e.message, 'danger')
    if (/شماره/.test(e.message || '')) { numberState.value = 'taken'; refreshLastNumber(form.direction) }
  }
  finally { saving.value = false }
}
function switchBox(b) { box.value = b; load() }

onMounted(() => { load(); loadMeta() })
</script>

<template>
  <div class="flex flex-col gap-5" style="max-width: 1200px">
    <div class="flex items-center justify-between gap-4">
      <div>
        <h1 class="m-0 text-3xl font-bold text-ink-900">دبیرخانه</h1>
        <p class="mt-1.5 text-ink-600">ثبت نامه‌های وارده/صادرهٔ رسمی با شمارهٔ دستی (کنترل تکراری‌نبودن توسط سامانه) و ارجاع به کارکنان</p>
      </div>
      <div class="flex gap-2">
        <button @click="openForm('in')" class="flex h-11 items-center gap-2 rounded-xl px-4 text-sm font-semibold text-white shadow-brand" style="background: var(--grad-brand)">
          <Icon name="plus" :size="17" /> ثبت نامهٔ وارده
        </button>
        <button @click="openForm('out')" class="flex h-11 items-center gap-2 rounded-xl border border-ink-300 bg-white px-4 text-sm font-semibold text-ink-800">
          <Icon name="send" :size="16" /> ثبت نامهٔ صادره
        </button>
      </div>
    </div>

    <div class="flex gap-2 rounded-2xl border border-ink-200 bg-white p-2.5 shadow-card">
      <button @click="switchBox('in')" class="h-[34px] rounded-[9px] px-4 text-[13px] font-semibold" :class="box === 'in' ? 'bg-blue-50 text-brand-strong' : 'text-ink-600'">نامه‌های وارده</button>
      <button @click="switchBox('out')" class="h-[34px] rounded-[9px] px-4 text-[13px] font-semibold" :class="box === 'out' ? 'bg-blue-50 text-brand-strong' : 'text-ink-600'">نامه‌های صادره</button>
      <div class="flex-1"></div>
      <div class="flex items-center gap-1.5 self-center px-2 text-[12px] text-ink-500">
        <span>آخرین شمارهٔ ثبت‌شده ({{ box === 'in' ? 'وارده' : 'صادره' }}):</span>
        <b class="num text-ink-800">{{ lastNumber[box] || '—' }}</b>
      </div>
    </div>

    <!-- folders (تفکیک) -->
    <div class="flex flex-wrap items-center gap-2 rounded-2xl border border-ink-200 bg-white p-2.5 shadow-card">
      <span class="px-1 text-[12px] font-semibold text-ink-500">بخش‌ها:</span>
      <button @click="activeFolder = null" class="h-[30px] rounded-full px-3 text-[12px] font-semibold" :class="activeFolder === null ? 'bg-blue-50 text-brand-strong' : 'text-ink-600 hover:bg-ink-50'">همه ({{ fa(items.length) }})</button>
      <div v-for="f in folders" :key="f.id" class="group flex items-center rounded-full" :class="Number(activeFolder) === Number(f.id) ? 'bg-blue-50' : 'hover:bg-ink-50'">
        <button @click="activeFolder = f.id" class="h-[30px] rounded-full ps-3 text-[12px] font-semibold" :class="[Number(activeFolder) === Number(f.id) ? 'text-brand-strong' : 'text-ink-700', canManageFolders ? 'pe-1' : 'pe-3']">📁 {{ f.name }}</button>
        <template v-if="canManageFolders">
          <button @click="renameFolder(f)" class="px-1 text-[11px] text-ink-400 hover:text-brand-strong" title="ویرایش">✎</button>
          <button @click="deleteFolder(f)" class="px-1.5 text-[13px] text-ink-400 hover:text-danger-500" title="حذف">×</button>
        </template>
      </div>
      <button v-if="canManageFolders" @click="createFolder" class="h-[30px] rounded-full border border-dashed border-blue-300 bg-blue-50 px-3 text-[12px] font-semibold text-brand-strong">＋ بخش جدید</button>
      <span v-else-if="!folders.length" class="px-1 text-[11.5px] text-ink-400">بخش‌بندی توسط مدیر سامانه تعریف می‌شود.</span>
      <span v-else class="px-1 text-[11px] text-ink-400" title="فقط مدیر سامانه می‌تواند بخش‌ها را تعریف یا ویرایش کند">🔒</span>
    </div>

    <AppCard :padding="false">
      <table class="w-full border-collapse">
        <thead>
          <tr>
            <th v-for="h in ['شماره', 'موضوع', box === 'in' ? 'فرستندهٔ بیرونی' : 'گیرندهٔ بیرونی', 'بخش', 'نوع', 'تاریخ']" :key="h"
              class="bg-ink-50 px-4 py-2.5 text-start text-[11.5px] font-semibold text-ink-500" :class="h === 'تاریخ' ? 'text-end' : ''">{{ h }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="l in visibleItems" :key="l.id" class="border-b border-ink-200 last:border-0 hover:bg-blue-50">
            <td class="num px-4 py-3 text-[13px] font-semibold text-brand-strong cursor-pointer" @click="router.push({ name: 'letter', params: { id: l.id } })">{{ l.number }}</td>
            <td class="px-4 py-3 text-sm text-ink-900 cursor-pointer" @click="router.push({ name: 'letter', params: { id: l.id } })">{{ l.subject }}</td>
            <td class="px-4 py-3 text-[13px] text-ink-700">{{ l.external_recipient || '—' }}</td>
            <td class="px-4 py-3">
              <select :value="l.folder_id || ''" @change="moveToFolder(l, $event.target.value)" @click.stop
                class="max-w-[150px] rounded-lg border border-ink-200 bg-white px-2 py-1 text-[12px] text-ink-700">
                <option value="">— بدون بخش —</option>
                <option v-for="f in folders" :key="f.id" :value="f.id">{{ f.name }}</option>
              </select>
            </td>
            <td class="px-4 py-3"><AppTag :tone="l.kind.tone">{{ l.kind.label }}</AppTag></td>
            <td class="num whitespace-nowrap px-4 py-3 text-end text-sm text-ink-600">{{ l.date }}</td>
          </tr>
          <tr v-if="!visibleItems.length"><td colspan="6" class="px-4 py-10 text-center text-[13.5px] text-ink-500">نامه‌ای ثبت نشده است.</td></tr>
        </tbody>
      </table>
    </AppCard>
  </div>

  <!-- register modal -->
  <div v-if="showForm" class="fixed inset-0 z-[80] flex items-center justify-center p-6" style="background: rgba(13,21,38,0.48); backdrop-filter: blur(3px)" @click.self="showForm = false">
    <div class="w-[min(560px,100%)] overflow-hidden rounded-2xl bg-white shadow-pop" style="animation: afModalIn .22s ease">
      <div class="flex items-center justify-between border-b border-ink-200 px-5 py-4">
        <b class="text-[15px] text-ink-900">{{ form.direction === 'in' ? 'ثبت نامهٔ وارده' : 'ثبت نامهٔ صادره' }}</b>
        <button @click="showForm = false" class="text-[20px] text-ink-500">×</button>
      </div>
      <div class="af-scroll flex max-h-[70vh] flex-col gap-3.5 overflow-y-auto p-5">
        <div class="flex flex-col gap-1.5 rounded-xl border border-blue-100 bg-blue-50/60 p-3">
          <div class="flex items-center justify-between gap-2">
            <span class="text-[12px] font-semibold text-ink-700">شمارهٔ نامه <span class="text-danger-500">*</span></span>
            <span class="text-[11.5px] text-ink-500">آخرین شمارهٔ ثبت‌شده: <b class="num text-ink-800">{{ lastForForm || lastNumber.any || '—' }}</b></span>
          </div>
          <div class="flex items-center gap-2">
            <input v-model="form.number" @input="checkNumber" class="af-inp num flex-1" dir="ltr" style="text-align:right" :placeholder="suggestedNumber ? `مثلاً ${suggestedNumber}` : 'شمارهٔ ثبت را وارد کنید'" />
            <button v-if="suggestedNumber" type="button" @click="useSuggested" class="h-10 whitespace-nowrap rounded-[10px] border border-ink-300 bg-white px-3 text-[12px] font-semibold text-ink-700" title="شمارهٔ بعد از آخرین شماره">شمارهٔ بعدی</button>
          </div>
          <span v-if="numberState === 'taken'" class="text-[11.5px] font-semibold text-danger-500">✕ این شماره قبلاً ثبت شده است.</span>
          <span v-else-if="numberState === 'free'" class="text-[11.5px] font-semibold text-green-600">✓ این شماره آزاد است.</span>
          <span v-else-if="numberState === 'checking'" class="text-[11.5px] text-ink-400">در حال بررسی…</span>
          <span v-else class="text-[11.5px] text-ink-400">شماره را خودتان وارد کنید؛ سامانه فقط تکراری‌نبودن آن را کنترل می‌کند.</span>
        </div>
        <label class="flex flex-col gap-1">
          <span class="text-[12px] text-ink-600">{{ form.direction === 'in' ? 'فرستنده (خارج از سازمان)' : 'گیرنده (خارج از سازمان)' }}</span>
          <input v-model="form.external_party" class="af-inp" placeholder="مثلاً سازمان غذا و دارو" />
        </label>
        <label class="flex flex-col gap-1">
          <span class="text-[12px] text-ink-600">موضوع</span>
          <input v-model="form.subject" class="af-inp" />
        </label>
        <div class="grid grid-cols-2 gap-3">
          <label class="flex flex-col gap-1">
            <span class="text-[12px] text-ink-600">نوع اقدام</span>
            <select v-model="form.kind" class="af-inp"><option v-for="k in KINDS" :key="k.v" :value="k.v">{{ k.label }}</option></select>
          </label>
          <label class="flex flex-col gap-1">
            <span class="text-[12px] text-ink-600">اولویت</span>
            <select v-model="form.priority" class="af-inp"><option v-for="p in meta.priorities" :key="p.value" :value="p.value">{{ p.label }}</option></select>
          </label>
        </div>
        <label class="flex flex-col gap-1">
          <span class="text-[12px] text-ink-600">بخش (اختیاری)</span>
          <select v-model="form.folder_id" class="af-inp">
            <option value="">— بدون بخش —</option>
            <option v-for="f in folders" :key="f.id" :value="f.id">{{ f.name }}</option>
          </select>
        </label>
        <label v-if="form.direction === 'in'" class="flex flex-col gap-1">
          <span class="text-[12px] text-ink-600">ارجاع به (گیرندهٔ داخلی)</span>
          <select :value="form.recipient_ids[0] || ''" @change="form.recipient_ids = $event.target.value ? [Number($event.target.value)] : []" class="af-inp">
            <option value="">— انتخاب شخص —</option>
            <option v-for="r in meta.recipients" :key="r.id" :value="r.id">{{ r.name }} — {{ r.role }}</option>
          </select>
        </label>
        <label class="flex flex-col gap-1">
          <span class="text-[12px] text-ink-600">توضیحات / متن</span>
          <textarea v-model="form.body" rows="3" class="af-inp" style="height:auto;padding:8px 11px"></textarea>
        </label>
        <div class="flex flex-col gap-2">
          <div class="flex items-center gap-2">
            <input ref="fileInput" type="file" multiple class="hidden" @change="pickScan" />
            <button v-if="pendingFiles.length < MAX_FILES" @click="fileInput.click()" class="rounded-lg border border-ink-300 bg-ink-50 px-3 py-2 text-[12.5px] font-semibold text-ink-700">📎 پیوست اسکن</button>
            <span class="num text-[11.5px] text-ink-400">({{ fa(pendingFiles.length) }}/{{ fa(MAX_FILES) }})</span>
          </div>
          <div v-for="(a, i) in pendingFiles" :key="i" class="flex items-center justify-between gap-2 rounded-lg border border-ink-200 bg-ink-50 px-3 py-1.5">
            <span class="min-w-0 flex-1 truncate text-[12px] text-ink-800">📎 {{ a.attachment }}</span>
            <button @click="removeScan(i)" class="text-[15px] text-danger-500">×</button>
          </div>
        </div>
      </div>
      <div class="flex justify-end gap-2.5 border-t border-ink-200 px-5 py-4">
        <button @click="showForm = false" class="rounded-[10px] border border-ink-300 bg-white px-4 py-2 text-[13px] text-ink-800">انصراف</button>
        <button @click="submit" :disabled="saving" class="rounded-[10px] px-5 py-2 text-[13px] font-semibold text-white shadow-brand disabled:opacity-60" style="background: var(--grad-brand)">
          {{ saving ? 'در حال ثبت…' : 'ثبت نامه' }}
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.af-inp { height: 40px; border: 1px solid var(--color-ink-200); border-radius: 10px; padding: 0 11px; font-size: 13.5px; background: #fff; color: var(--color-ink-800); }
</style>
