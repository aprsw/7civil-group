<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '../services/api'
import AppCard from '../components/ui/AppCard.vue'
import AppBadge from '../components/ui/AppBadge.vue'
import AppTag from '../components/ui/AppTag.vue'
import AppAvatar from '../components/ui/AppAvatar.vue'

const router = useRouter()
const items = ref([])
const q = ref('')
const kind = ref('')
const status = ref('')

const KINDS = [{ v: '', t: 'همه انواع' }, { v: 'action', t: 'اقدام' }, { v: 'info', t: 'اطلاع' }, { v: 'sign', t: 'امضا' }, { v: 'reply', t: 'پاسخ' }, { v: 'followup', t: 'پیگیری' }]

function statusCat(l) {
  if (l.wf_status) {
    if (l.wf_status.tone === 'success') return 'approved'
    if (l.wf_status.tone === 'warning') return 'pending'
    if (l.wf_status.label.includes('رد')) return 'rejected'
  }
  const v = l.status.value
  if (v === 'rejected') return 'rejected'
  if (v === 'pending' || v === 'reviewing') return 'pending'
  if (v === 'draft') return 'draft'
  return 'approved'
}

const filtered = computed(() => {
  const n = q.value.trim().toLowerCase()
  return items.value.filter((l) =>
    (!kind.value || l.kind?.value === kind.value)
    && (!status.value || statusCat(l) === status.value)
    && (!n || [l.subject, l.number, l.from, l.to].some((f) => String(f || '').toLowerCase().includes(n))),
  )
})

async function load() { items.value = (await api.get('/admin/letters')).items }
onMounted(load)
</script>

<template>
  <div class="flex flex-col gap-[18px]" style="max-width: 1320px">
    <div>
      <h1 class="m-0 text-3xl font-bold text-ink-900">همهٔ نامه‌ها (ادمین)</h1>
      <p class="mt-1.5 text-ink-600">نمای کامل مکاتبات سازمان — چه کسی به چه کسانی نامه فرستاده است.</p>
    </div>

    <div class="flex flex-wrap items-center gap-2 rounded-2xl border border-ink-200 bg-white p-2.5 px-3 shadow-card">
      <input v-model="q" placeholder="جستجو: موضوع، شماره، فرستنده، گیرنده…" class="h-9 min-w-[220px] flex-1 rounded-lg border border-ink-200 bg-ink-50 px-3 text-[13px]" />
      <select v-model="kind" class="h-9 rounded-lg border border-ink-200 bg-white px-2 text-[12.5px]">
        <option v-for="k in KINDS" :key="k.v" :value="k.v">{{ k.t }}</option>
      </select>
      <select v-model="status" class="h-9 rounded-lg border border-ink-200 bg-white px-2 text-[12.5px]">
        <option value="">همه وضعیت‌ها</option>
        <option value="pending">در انتظار تأیید</option>
        <option value="approved">تأیید/ارسال شده</option>
        <option value="rejected">رد شده</option>
        <option value="draft">پیش‌نویس</option>
      </select>
      <span class="num text-[12px] text-ink-500">{{ filtered.length.toLocaleString('fa') }} نامه</span>
    </div>

    <AppCard :padding="false">
      <div class="overflow-x-auto">
        <table class="w-full border-collapse" style="min-width: 820px">
          <thead>
            <tr>
              <th v-for="h in ['شماره', 'فرستنده', 'گیرنده/گیرندگان', 'نوع', 'وضعیت', 'تاریخ']" :key="h"
                class="bg-ink-50 px-4 py-2.5 text-start text-[11.5px] font-semibold text-ink-500" :class="h === 'تاریخ' ? 'text-end' : ''">{{ h }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="l in filtered" :key="l.id" @click="router.push({ name: 'letter', params: { id: l.id } })" class="cursor-pointer border-b border-ink-200 last:border-0 hover:bg-blue-50">
              <td class="num px-4 py-3 text-[12.5px] font-semibold text-brand-strong">{{ l.number }}</td>
              <td class="px-4 py-3">
                <div class="flex items-center gap-2">
                  <AppAvatar :name="l.from" :photo="l.sender_photo_url" :size="26" />
                  <span class="text-[13px] text-ink-900">{{ l.from }}</span>
                </div>
              </td>
              <td class="px-4 py-3 text-[12.5px] text-ink-700">{{ l.to }}</td>
              <td class="px-4 py-3"><AppTag :tone="l.kind.tone">{{ l.kind.label }}</AppTag></td>
              <td class="px-4 py-3"><AppBadge :tone="l.wf_status?.tone || l.status.tone">{{ l.wf_status?.label || l.status.label }}</AppBadge></td>
              <td class="num whitespace-nowrap px-4 py-3 text-end text-[13px] text-ink-600">{{ l.date }}</td>
            </tr>
            <tr v-if="!filtered.length"><td colspan="6" class="px-4 py-10 text-center text-[13.5px] text-ink-500">نامه‌ای یافت نشد.</td></tr>
          </tbody>
        </table>
      </div>
    </AppCard>
  </div>
</template>
