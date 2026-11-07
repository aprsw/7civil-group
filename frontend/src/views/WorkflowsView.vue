<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { api } from '../services/api'
import { useToastStore } from '../stores/toast'
import { useConfirmStore } from '../stores/confirm'
import { useAuthStore } from '../stores/auth'
import AppCard from '../components/ui/AppCard.vue'
import PeoplePicker from '../components/ui/PeoplePicker.vue'

const toast = useToastStore()
const confirmStore = useConfirmStore()
const auth = useAuthStore()

const canManage = ref(false)
const workflows = ref([])
const users = ref([])
const form = reactive({ id: null, name: '', description: '', approver_ids: [] })
const saving = ref(false)

function fa(n) { return String(n).replace(/[0-9]/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]) }
function userName(id) { return users.value.find((u) => String(u.id) === String(id))?.name || id }
function chainNames(w) { return (w.approver_ids || []).map(userName).join(' ← ') }

async function load() {
  const res = await api.get('/workflows')
  workflows.value = res.items
  users.value = res.users || []
  canManage.value = res.can_manage
}
function edit(w) {
  form.id = w.id; form.name = w.name; form.description = w.description || ''
  form.approver_ids = (w.approver_ids || []).map(String)
}
function reset() { form.id = null; form.name = ''; form.description = ''; form.approver_ids = [] }

function addApprover(id) { if (id && !form.approver_ids.includes(String(id))) form.approver_ids.push(String(id)) }
function removeApprover(id) { form.approver_ids = form.approver_ids.filter((x) => x !== String(id)) }
function moveApprover(idx, dir) {
  const j = idx + dir
  if (j < 0 || j >= form.approver_ids.length) return
  const a = form.approver_ids
  ;[a[idx], a[j]] = [a[j], a[idx]]
}

async function save() {
  if (!form.name.trim()) { toast.show('نام گردش کار را وارد کنید', 'danger'); return }
  saving.value = true
  try {
    const payload = { name: form.name, description: form.description, approver_ids: form.approver_ids.map(Number) }
    if (form.id) await api.put(`/workflows/${form.id}`, payload)
    else await api.post('/workflows', payload)
    toast.show('گردش کار ذخیره شد')
    reset()
    load()
  } catch (e) { toast.show(e.message, 'danger') } finally { saving.value = false }
}
async function remove(w) {
  const ok = await confirmStore.ask({ title: 'حذف گردش کار', message: `گردش کار «${w.name}» حذف شود؟`, confirmText: 'حذف', tone: 'danger' })
  if (!ok) return
  try { await api.del(`/workflows/${w.id}`); if (form.id === w.id) reset(); toast.show('حذف شد'); load() }
  catch (e) { toast.show(e.message, 'danger') }
}

watch(() => auth.user?.id, load)
onMounted(load)
</script>

<template>
  <div class="flex flex-col gap-5" style="max-width: 1080px">
    <div>
      <h1 class="m-0 text-3xl font-bold text-ink-900">گردش کارها</h1>
      <p class="mt-1.5 text-ink-600">می‌توانید یک <b>فرایند از پیش تعریف‌شده</b> بسازید: مسیر تأییدکنندگان (اشخاص مشخص، به‌ترتیب) را تعیین کنید تا هر نامه‌ای که از این گردش کار استفاده کند دقیقاً همین مسیر را طی کند — فارغ از اینکه چه کسی نامه را زده است.</p>
    </div>

    <div v-if="!canManage" class="flex items-center gap-2.5 rounded-xl border border-warning-500 bg-warning-50 px-3.5 py-3 text-[13px] text-warning-600">
      <span class="text-base">🔒</span> فقط مدیر/ادمین می‌تواند گردش کار تعریف کند.
    </div>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-[1fr_400px]">
      <!-- list -->
      <AppCard :padding="false">
        <table class="w-full border-collapse">
          <thead>
            <tr>
              <th v-for="h in ['نام گردش کار', 'مسیر تأیید (فرایند)', '']" :key="h" class="bg-ink-50 px-4 py-2.5 text-start text-[11.5px] font-semibold text-ink-500">{{ h }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="w in workflows" :key="w.id" class="border-b border-ink-200 last:border-0 align-top">
              <td class="px-4 py-3">
                <div class="text-sm font-semibold text-ink-900">{{ w.name }}</div>
                <div v-if="w.description" class="text-[11.5px] text-ink-500">{{ w.description }}</div>
              </td>
              <td class="px-4 py-3 text-[12.5px]">
                <span v-if="w.predefined" class="inline-flex items-center gap-1 rounded-full bg-blue-50 px-2 py-0.5 text-[11px] font-semibold text-brand-strong">فرایند ثابت</span>
                <span v-else class="inline-flex items-center gap-1 rounded-full bg-ink-100 px-2 py-0.5 text-[11px] text-ink-500">مسیر دستیِ فرستنده</span>
                <div v-if="w.predefined" class="mt-1 text-ink-700">{{ chainNames(w) }}</div>
              </td>
              <td class="px-4 py-3 text-end">
                <template v-if="canManage">
                  <button @click="edit(w)" class="me-1 h-8 w-8 rounded-lg border border-ink-200 bg-white text-ink-600" title="ویرایش">✎</button>
                  <button @click="remove(w)" class="h-8 w-8 rounded-lg border border-danger-500 bg-white text-[15px] text-danger-500" title="حذف">×</button>
                </template>
              </td>
            </tr>
            <tr v-if="!workflows.length"><td colspan="3" class="px-4 py-10 text-center text-[13.5px] text-ink-500">هنوز گردش کاری تعریف نشده است.</td></tr>
          </tbody>
        </table>
      </AppCard>

      <!-- form -->
      <AppCard v-if="canManage" :title="form.id ? 'ویرایش گردش کار' : 'گردش کار جدید'">
        <div class="flex flex-col gap-3">
          <label class="flex flex-col gap-1">
            <span class="text-[12px] text-ink-600">نام گردش کار</span>
            <input v-model="form.name" class="af-inp" placeholder="مثلاً فرایند تأیید خرید" />
          </label>
          <label class="flex flex-col gap-1">
            <span class="text-[12px] text-ink-600">توضیحات</span>
            <textarea v-model="form.description" rows="2" class="af-inp" style="height:auto;padding:9px 11px" placeholder="توضیح دربارهٔ کاربرد این گردش کار…"></textarea>
          </label>

          <div class="flex flex-col gap-1.5">
            <span class="text-[12px] text-ink-600">مسیر تأیید ثابت (اختیاری) — به‌ترتیب</span>
            <PeoplePicker :people="users" :exclude="form.approver_ids"
              placeholder="＋ افزودن تأییدکننده (جستجوی همهٔ کاربران)…"
              @pick="addApprover" />
            <div v-if="form.approver_ids.length" class="flex flex-col gap-1.5 pt-0.5">
              <div v-for="(id, idx) in form.approver_ids" :key="id" class="flex items-center gap-2 rounded-lg border border-ink-200 bg-ink-50 px-2.5 py-1.5">
                <span class="num flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-[11px] font-bold text-white" style="background: var(--grad-brand)">{{ fa(idx + 1) }}</span>
                <span class="min-w-0 flex-1 truncate text-[12.5px] text-ink-900">{{ userName(id) }}</span>
                <button @click="moveApprover(idx, -1)" :disabled="idx === 0" class="h-6 w-6 rounded text-ink-500 disabled:opacity-30" title="بالا">▲</button>
                <button @click="moveApprover(idx, 1)" :disabled="idx === form.approver_ids.length - 1" class="h-6 w-6 rounded text-ink-500 disabled:opacity-30" title="پایین">▼</button>
                <button @click="removeApprover(id)" class="text-[15px] text-danger-500" title="حذف">×</button>
              </div>
            </div>
            <span class="text-[11px] text-ink-400">اگر خالی بماند، این گردش کار فقط یک «برچسب» است و فرستنده خودش مسیر را در نامه تعیین می‌کند.</span>
          </div>

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
