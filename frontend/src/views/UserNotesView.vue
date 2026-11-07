<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { api } from '../services/api'
import { useToastStore } from '../stores/toast'
import { useConfirmStore } from '../stores/confirm'
import AppCard from '../components/ui/AppCard.vue'
import Icon from '../components/ui/Icon.vue'
import JalaliDatePicker from '../components/ui/JalaliDatePicker.vue'
import { todayJalaali, formatJalaali } from '../utils/jalali'

const toast = useToastStore()
const confirmStore = useConfirmStore()

const notes = ref([])
const saving = ref(false)
const form = reactive({ id: null, title: '', note_date: '', description: '', remind_days_before: 3 })

function fa(n) { return String(n).replace(/[0-9]/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]) }
const dueCount = computed(() => notes.value.filter((n) => n.is_due).length)

function remainingLabel(n) {
  if (n.days_remaining === null) return ''
  if (n.is_today) return 'امروز'
  if (n.is_past) return `${fa(Math.abs(n.days_remaining))} روز گذشته`
  return `${fa(n.days_remaining)} روز مانده`
}
function tone(n) {
  if (n.is_today) return 'bg-danger-50 text-danger-600 border-danger-200'
  if (n.is_due) return 'bg-warning-50 text-warning-600 border-warning-200'
  if (n.is_past) return 'bg-ink-100 text-ink-400 border-ink-200'
  return 'bg-blue-50 text-brand-strong border-blue-200'
}

async function load() {
  try { notes.value = (await api.get('/user-notes')).items || [] }
  catch (e) { toast.show(e.message, 'danger') }
}
function reset() {
  const t = todayJalaali()
  form.id = null; form.title = ''; form.note_date = formatJalaali(t.jy, t.jm, t.jd); form.description = ''; form.remind_days_before = 3
}
function edit(n) {
  form.id = n.id; form.title = n.title; form.note_date = n.note_date
  form.description = n.description || ''; form.remind_days_before = n.remind_days_before
}
async function save() {
  if (!form.title.trim()) { toast.show('عنوان یادداشت را وارد کنید', 'danger'); return }
  if (!form.note_date) { toast.show('تاریخ را انتخاب کنید', 'danger'); return }
  saving.value = true
  try {
    const payload = { title: form.title, note_date: form.note_date, description: form.description, remind_days_before: Number(form.remind_days_before) || 0 }
    if (form.id) await api.put(`/user-notes/${form.id}`, payload)
    else await api.post('/user-notes', payload)
    toast.show('ذخیره شد')
    reset(); await load()
  } catch (e) { toast.show(e.message, 'danger') } finally { saving.value = false }
}
async function remove(n) {
  const ok = await confirmStore.ask({ title: 'حذف یادداشت', message: `یادداشت «${n.title}» حذف شود؟`, confirmText: 'حذف', tone: 'danger' })
  if (!ok) return
  try { await api.del(`/user-notes/${n.id}`); toast.show('حذف شد'); await load() }
  catch (e) { toast.show(e.message, 'danger') }
}

onMounted(() => { reset(); load() })
</script>

<template>
  <div class="flex flex-col gap-5" style="max-width: 1080px">
    <div>
      <h1 class="m-0 text-3xl font-bold text-ink-900">یادداشت‌های من</h1>
      <p class="mt-1.5 text-ink-600">یادداشت‌ها و یادآوری‌های شخصیِ شما — فقط برای خودتان قابل مشاهده است. برای هر یادداشت می‌توانید یک تاریخ و یادآوری «چند روز مانده» تعیین کنید.</p>
    </div>

    <div v-if="dueCount" class="flex items-center gap-2.5 rounded-xl border border-warning-200 bg-warning-50 px-4 py-3 text-[13px] text-warning-600">
      <span class="text-base">🔔</span> شما {{ fa(dueCount) }} یادآوری فعال دارید.
    </div>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-[1fr_360px]">
      <!-- list -->
      <AppCard :padding="false">
        <div v-if="!notes.length" class="flex flex-col items-center gap-2 p-10 text-center">
          <span class="text-[32px]">🗒️</span>
          <p class="text-[13.5px] font-semibold text-ink-700">هنوز یادداشتی ندارید</p>
          <p class="text-[12px] text-ink-400">از فرم کنار، اولین یادداشت خود را بسازید.</p>
        </div>
        <div v-else class="flex flex-col">
          <div v-for="n in notes" :key="n.id" class="flex items-start justify-between gap-3 border-b border-ink-100 px-4 py-3 last:border-0"
            :class="n.is_today ? 'bg-danger-50/40' : (n.is_due ? 'bg-warning-50/40' : '')">
            <div class="min-w-0">
              <div class="flex flex-wrap items-center gap-2">
                <b class="text-[13.5px] text-ink-900">{{ n.title }}</b>
                <span class="num rounded-full border px-2 py-0.5 text-[11px] font-semibold" :class="tone(n)">{{ n.note_date }} · {{ remainingLabel(n) }}</span>
                <span v-if="n.remind_days_before" class="text-[10.5px] text-ink-400">یادآوری {{ fa(n.remind_days_before) }} روز قبل</span>
              </div>
              <p v-if="n.description" class="mt-1 whitespace-pre-wrap text-[12.5px] text-ink-600">{{ n.description }}</p>
            </div>
            <div class="flex shrink-0 items-center gap-1.5">
              <button @click="edit(n)" class="h-8 w-8 rounded-lg border border-ink-200 bg-white text-ink-600" title="ویرایش">✎</button>
              <button @click="remove(n)" class="h-8 w-8 rounded-lg border border-danger-500 bg-white text-[15px] text-danger-500" title="حذف">×</button>
            </div>
          </div>
        </div>
      </AppCard>

      <!-- form -->
      <AppCard :title="form.id ? 'ویرایش یادداشت' : 'یادداشت جدید'">
        <div class="flex flex-col gap-3">
          <label class="flex flex-col gap-1">
            <span class="text-[12px] text-ink-600">عنوان</span>
            <input v-model="form.title" class="af-inp" placeholder="مثلاً پیگیری قرارداد بیمه" />
          </label>
          <label class="flex flex-col gap-1">
            <span class="text-[12px] text-ink-600">تاریخ (شمسی)</span>
            <JalaliDatePicker v-model="form.note_date" />
          </label>
          <label class="flex flex-col gap-1">
            <span class="text-[12px] text-ink-600">یادآوری چند روز قبل؟</span>
            <input v-model.number="form.remind_days_before" type="number" min="0" max="365" class="af-inp num" />
            <span class="text-[11px] text-ink-400">۰ یعنی فقط همان روز یادآوری شود.</span>
          </label>
          <label class="flex flex-col gap-1">
            <span class="text-[12px] text-ink-600">توضیحات</span>
            <textarea v-model="form.description" rows="3" class="af-inp" style="height:auto;padding:9px 11px" placeholder="جزئیات کار…"></textarea>
          </label>
          <div class="flex gap-2">
            <button @click="save" :disabled="saving" class="flex-1 rounded-[10px] px-4 py-2.5 text-[13px] font-semibold text-white shadow-brand disabled:opacity-60" style="background: var(--grad-brand)">
              {{ saving ? 'در حال ذخیره…' : (form.id ? 'به‌روزرسانی' : 'افزودن') }}
            </button>
            <button v-if="form.id" @click="reset" class="rounded-[10px] border border-ink-300 bg-white px-4 py-2.5 text-[13px] text-ink-800">جدید</button>
          </div>
        </div>
      </AppCard>
    </div>
  </div>
</template>

<style scoped>
.af-inp { height: 40px; border: 1px solid var(--color-ink-200); border-radius: 10px; padding: 0 11px; font-size: 13.5px; background: #fff; color: var(--color-ink-800); }
</style>
