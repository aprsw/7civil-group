<script setup>
import { ref, onMounted } from 'vue'
import { api } from '../services/api'
import { useToastStore } from '../stores/toast'
import Icon from '../components/ui/Icon.vue'

const toast = useToastStore()
const items = ref([])
const loading = ref(true)
const uploading = ref(false)
const fileInput = ref(null)

function fa(n) { return String(n).replace(/[0-9]/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]) }
function humanSize(b) {
  if (!b) return '—'
  if (b < 1024) return fa(b) + ' B'
  if (b < 1048576) return fa((b / 1024).toFixed(1)) + ' KB'
  return fa((b / 1048576).toFixed(1)) + ' MB'
}
function icon(f) { return f.kind === 'excel' ? '📊' : f.kind === 'word' ? '📄' : '📎' }

async function load() {
  loading.value = true
  try { items.value = (await api.get('/office-files')).items } catch (e) { toast.show(e.message, 'danger') }
  finally { loading.value = false }
}
async function onFile(e) {
  const files = Array.from(e.target.files || [])
  uploading.value = true
  for (const file of files) {
    try { await api.upload('/office-files', file) }
    catch (err) { toast.show(err.message, 'danger') }
  }
  uploading.value = false
  e.target.value = ''
  if (files.length) { toast.show('فایل‌ها افزوده شدند'); await load() }
}
async function remove(f) {
  if (!confirm(`فایل «${f.name}» حذف شود؟`)) return
  try { await api.del(`/office-files/${f.id}`); toast.show('حذف شد'); await load() }
  catch (e) { toast.show(e.message, 'danger') }
}
async function downloadFile(f) {
  try { await api.download(`/office-files/${f.id}/download`, f.name) }
  catch (e) { toast.show(e.message, 'danger') }
}

onMounted(load)
</script>

<template>
  <div class="mx-auto flex max-w-[900px] flex-col gap-4 p-4 sm:p-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h1 class="text-[18px] font-bold text-ink-900">کتابخانهٔ فایل‌های Word / Excel</h1>
        <p class="text-[12.5px] text-ink-500">این فایل‌ها را کاربران هنگام ساخت نامه در بخش «فایل‌های پیوستی» انتخاب و ویرایش می‌کنند.</p>
      </div>
      <input ref="fileInput" type="file" multiple accept=".docx,.doc,.xlsx,.xls,.csv,.rtf" class="hidden" @change="onFile" />
      <button @click="fileInput.click()" :disabled="uploading"
        class="flex items-center gap-2 rounded-xl px-4 py-2.5 text-[13px] font-semibold text-white shadow-brand disabled:opacity-50" style="background: var(--grad-brand)">
        <Icon name="paperclip" :size="15" /> {{ uploading ? 'در حال بارگذاری…' : 'افزودن فایل Word یا Excel' }}
      </button>
    </div>

    <div class="rounded-2xl border border-ink-200 bg-white shadow-card">
      <div v-if="loading" class="p-8 text-center text-[13px] text-ink-400">در حال بارگذاری…</div>
      <div v-else-if="!items.length" class="flex flex-col items-center gap-2 p-10 text-center">
        <span class="text-[34px]">🗂</span>
        <p class="text-[13.5px] font-semibold text-ink-700">هنوز فایلی اضافه نشده است</p>
        <p class="text-[12px] text-ink-400">با دکمهٔ بالا یک فایل Word یا Excel بارگذاری کنید.</p>
      </div>
      <table v-else class="w-full text-[12.5px]">
        <thead>
          <tr class="border-b border-ink-200 text-ink-500">
            <th class="px-4 py-3 text-right font-semibold">فایل</th>
            <th class="px-4 py-3 text-right font-semibold">نوع</th>
            <th class="px-4 py-3 text-right font-semibold">حجم</th>
            <th class="px-4 py-3 text-center font-semibold">عملیات</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="f in items" :key="f.id" class="border-b border-ink-100 last:border-0">
            <td class="px-4 py-2.5">
              <span class="flex items-center gap-2">
                <span class="text-[16px]">{{ icon(f) }}</span>
                <span class="min-w-0 truncate font-semibold text-ink-900">{{ f.name }}</span>
              </span>
            </td>
            <td class="px-4 py-2.5 text-ink-600">{{ f.kind === 'excel' ? 'Excel' : f.kind === 'word' ? 'Word' : '—' }}</td>
            <td class="num px-4 py-2.5 text-ink-600">{{ humanSize(f.size) }}</td>
            <td class="px-4 py-2.5">
              <div class="flex items-center justify-center gap-2">
                <button @click="downloadFile(f)" class="rounded-lg border border-ink-200 bg-white px-3 py-1.5 text-[12px] font-semibold text-ink-700">⬇ دانلود</button>
                <button @click="remove(f)" class="rounded-lg border border-danger-500 bg-white px-3 py-1.5 text-[12px] font-semibold text-danger-500">حذف</button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
