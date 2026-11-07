<script setup>
import { ref, onMounted } from 'vue'
import { api } from '../../services/api'

const props = defineProps({
  note: { type: Object, default: null }, // existing note (may carry a file)
  readonly: { type: Boolean, default: false },
})
const emit = defineEmits(['save', 'close'])

const library = ref([])
const loadingLib = ref(true)
const busy = ref(false)
const errorMsg = ref('')
const downloadedName = ref('')
const picker = ref(null)

// A previously-saved file note keeps its stored token unless the user replaces it.
const existing = props.note && props.note.file_path ? { ...props.note } : null

onMounted(async () => {
  try { library.value = (await api.get('/office-files')).items }
  catch (e) { errorMsg.value = 'دریافت فهرست فایل‌ها ناموفق بود: ' + (e?.message || e) }
  finally { loadingLib.value = false }
})

function libIcon(f) { return f.kind === 'excel' ? '📊' : f.kind === 'word' ? '📄' : '📎' }

// Step 1 — download an admin file so the user can edit it locally (in Word/Excel).
async function downloadLib(f) {
  errorMsg.value = ''
  try {
    await api.download(`/office-files/${f.id}/download`, f.name)
    downloadedName.value = f.name
  } catch (e) { errorMsg.value = 'دانلود فایل ناموفق بود: ' + (e?.message || e) }
}
// Step 2 — upload the edited file; it becomes this letter's attached file.
function pickUpload() { picker.value?.click() }
async function onUpload(e) {
  const file = e.target.files?.[0]
  e.target.value = ''
  if (!file) return
  busy.value = true
  errorMsg.value = ''
  try {
    const up = await api.upload('/attachments', file)
    emit('save', {
      title: up.attachment || file.name,
      content: '',
      file_name: up.attachment || file.name,
      file_path: up.attachment_path,
      file_mime: up.attachment_mime || file.type,
      file_size: up.attachment_size,
    })
  } catch (err) {
    errorMsg.value = 'بارگذاری فایل ناموفق بود: ' + (err?.message || err)
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <div class="fixed inset-0 z-[90] flex items-center justify-center p-4 sm:p-6"
    style="background: rgba(13,21,38,0.5); backdrop-filter: blur(4px)" @click.self="emit('close')">
    <div class="flex max-h-[90vh] w-[min(680px,100%)] flex-col overflow-hidden rounded-2xl bg-white shadow-pop">
      <div class="flex items-center justify-between border-b border-ink-200 px-5 py-4">
        <b class="text-[15px] text-ink-900">افزودن فایل پیوستی (Word / Excel)</b>
        <button @click="emit('close')" class="text-[20px] text-ink-500">×</button>
      </div>

      <div class="min-h-0 flex-1 overflow-auto p-5">
        <div v-if="existing" class="mb-3 flex items-center justify-between gap-2 rounded-xl border border-blue-200 bg-blue-50 px-4 py-2.5 text-[12.5px] text-brand-strong">
          <span>فایل فعلی: <b>{{ existing.file_name || existing.title }}</b></span>
        </div>

        <!-- Step 1: download a template to edit -->
        <div class="rounded-xl border border-ink-200 p-4">
          <div class="mb-2 text-[13px] font-bold text-ink-800">۱) فایل موردنظر را دانلود کنید</div>
          <p class="mb-2.5 text-[12px] text-ink-500">یکی از فایل‌های بارگذاری‌شدهٔ سازمان را دانلود کنید، روی رایانهٔ خود در Word یا Excel <b>ویرایش</b> کنید و سپس در گام ۲ نسخهٔ نهایی را بارگذاری نمایید.</p>
          <div v-if="loadingLib" class="py-3 text-center text-[12.5px] text-ink-400">در حال بارگذاری فهرست…</div>
          <div v-else-if="!library.length" class="rounded-lg border border-dashed border-ink-300 bg-ink-50 p-4 text-center text-[12px] text-ink-500">
            هنوز فایلی توسط مدیر بارگذاری نشده است. مدیر می‌تواند از بخش «فایل‌های Word/Excel» فایل اضافه کند.
          </div>
          <div v-else class="grid grid-cols-1 gap-2 sm:grid-cols-2">
            <div v-for="f in library" :key="f.id" class="flex items-center justify-between gap-2 rounded-lg border border-ink-200 bg-white px-3 py-2">
              <span class="flex min-w-0 items-center gap-2">
                <span class="text-[18px]">{{ libIcon(f) }}</span>
                <span class="min-w-0 truncate text-[12.5px] font-semibold text-ink-900">{{ f.name }}</span>
              </span>
              <button @click="downloadLib(f)" class="shrink-0 rounded-lg border border-ink-300 bg-white px-2.5 py-1 text-[11.5px] font-semibold text-ink-700">⬇ دانلود</button>
            </div>
          </div>
        </div>

        <!-- Step 2: upload the edited file -->
        <div class="mt-3 rounded-xl border border-ink-200 p-4">
          <div class="mb-2 text-[13px] font-bold text-ink-800">۲) فایل ویرایش‌شده را بارگذاری کنید</div>
          <p v-if="downloadedName" class="mb-2 text-[11.5px] text-teal-600">✓ «{{ downloadedName }}» دانلود شد. پس از ویرایش، نسخهٔ نهایی را بارگذاری کنید.</p>
          <input ref="picker" type="file" accept=".docx,.doc,.xlsx,.xls,.csv,.rtf,.pdf" class="hidden" @change="onUpload" />
          <button @click="pickUpload" :disabled="busy"
            class="flex w-full items-center justify-center gap-2 rounded-xl border border-dashed border-blue-300 bg-blue-50 py-3 text-[13px] font-semibold text-brand-strong disabled:opacity-50">
            <span class="text-[16px]">⬆</span> {{ busy ? 'در حال بارگذاری…' : 'انتخاب و بارگذاری فایل ویرایش‌شده' }}
          </button>
        </div>

        <p v-if="errorMsg" class="mt-3 text-center text-[12px] text-danger-600">{{ errorMsg }}</p>
      </div>

      <div class="flex items-center justify-between gap-2.5 border-t border-ink-200 px-5 py-4">
        <span class="text-[11px] text-ink-400">فایل نهایی همراه نامه به گیرندگان تحویل داده می‌شود.</span>
        <button @click="emit('close')" class="rounded-[10px] border border-ink-300 bg-white px-4 py-2 text-[13px] text-ink-800">بستن</button>
      </div>
    </div>
  </div>
</template>
