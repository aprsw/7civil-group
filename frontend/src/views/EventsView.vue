<script setup>
import { ref, onMounted } from 'vue'
import { api } from '../services/api'
import { useToastStore } from '../stores/toast'
import { useConfirmStore } from '../stores/confirm'
import AppCard from '../components/ui/AppCard.vue'
import Icon from '../components/ui/Icon.vue'
import EventFormModal from '../components/modals/EventFormModal.vue'

const toast = useToastStore()
const confirmStore = useConfirmStore()
const events = ref([])
const canManage = ref(false)
const showForm = ref(false)
const editing = ref(null)
const saving = ref(false)

async function load() {
  const res = await api.get('/events')
  events.value = res.items || []
  canManage.value = !!res.can_manage
}

function openCreate() { editing.value = null; showForm.value = true }
function openEdit(e) { editing.value = e; showForm.value = true }

async function save(payload, setErrors) {
  saving.value = true
  try {
    if (editing.value) await api.put(`/events/${editing.value.id}`, payload)
    else await api.post('/events', payload)
    toast.show(editing.value ? 'رویداد به‌روزرسانی شد' : 'رویداد ثبت شد')
    showForm.value = false
    load()
  } catch (e) {
    if (e.errors && Object.keys(e.errors).length) setErrors(e.errors)
    else toast.show(e.message, 'danger')
  } finally {
    saving.value = false
  }
}

async function remove(e) {
  const ok = await confirmStore.ask({ title: 'حذف رویداد', message: `رویداد «${e.title}» حذف شود؟`, confirmText: 'حذف', tone: 'danger' })
  if (!ok) return
  try {
    await api.del(`/events/${e.id}`)
    toast.show('رویداد حذف شد')
    load()
  } catch (err) {
    toast.show(err.message, 'danger')
  }
}

onMounted(load)
</script>

<template>
  <div class="flex flex-col gap-[18px]" style="max-width: 1080px">
    <div class="flex items-center justify-between gap-4">
      <div>
        <h1 class="m-0 text-3xl font-bold text-ink-900">رویدادهای سازمان</h1>
        <p class="mt-1.5 text-ink-600">تقویم رویدادها، همایش‌ها و مناسبت‌های داخلی</p>
      </div>
      <button v-if="canManage" @click="openCreate"
        class="flex h-11 items-center gap-2 rounded-xl px-5 text-sm font-semibold text-white shadow-brand" style="background: var(--grad-brand)">
        <Icon name="plus" :size="18" /> افزودن رویداد
      </button>
    </div>

    <AppCard v-if="!events.length" :padding="true">
      <div class="py-10 text-center text-[13.5px] text-ink-500">رویدادی ثبت نشده است.</div>
    </AppCard>

    <div v-else class="grid grid-cols-1 gap-4 md:grid-cols-2">
      <div v-for="e in events" :key="e.id" class="overflow-hidden rounded-2xl border border-ink-200 bg-white shadow-card">
        <div class="h-1" style="background: var(--grad-gold)"></div>
        <div class="flex flex-col gap-2.5 p-5">
          <div class="flex items-start justify-between gap-3">
            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl text-white" style="background: var(--grad-gold)">
              <Icon name="clock" :size="20" />
            </div>
            <span class="num rounded-full bg-warning-50 px-3 py-1 text-[12px] font-semibold text-warning-600">{{ e.event_date }}</span>
          </div>
          <div class="text-[15px] font-bold text-ink-900">{{ e.title }}</div>
          <div v-if="e.location" class="text-[12px] text-ink-500">📍 {{ e.location }}</div>
          <p v-if="e.description" class="m-0 text-[13px] leading-6 text-ink-600">{{ e.description }}</p>
          <div v-if="e.created_by" class="text-[11px] text-ink-400">ثبت‌کننده: {{ e.created_by }}</div>
          <div v-if="canManage" class="mt-1 flex gap-1.5">
            <button @click="openEdit(e)" class="flex h-8 items-center gap-1 rounded-lg border border-ink-200 bg-white px-3 text-[12px] text-ink-700 hover:bg-ink-100">✎ ویرایش</button>
            <button @click="remove(e)" class="flex h-8 items-center gap-1 rounded-lg border border-danger-500 bg-white px-3 text-[12px] text-danger-500 hover:bg-danger-50">× حذف</button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <EventFormModal v-if="showForm" :event="editing" :saving="saving" @save="save" @close="showForm = false" />
</template>
