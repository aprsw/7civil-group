<script setup>
import { ref, onMounted } from 'vue'
import { api } from '../services/api'
import { useToastStore } from '../stores/toast'
import { useConfirmStore } from '../stores/confirm'
import Icon from '../components/ui/Icon.vue'
import DepartmentFormModal from '../components/modals/DepartmentFormModal.vue'

const toast = useToastStore()
const confirmStore = useConfirmStore()

const departments = ref([])
const canManage = ref(false)
const showForm = ref(false)
const editing = ref(null)
const saving = ref(false)

function fa(n) {
  return String(n).replace(/[0-9]/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d])
}

async function load() {
  const res = await api.get('/departments')
  departments.value = res.items || []
  canManage.value = !!res.can_manage
}

function openCreate() { editing.value = null; showForm.value = true }
function openEdit(d) { editing.value = d; showForm.value = true }

async function save(payload, setErrors) {
  saving.value = true
  try {
    if (editing.value) await api.put(`/departments/${editing.value.id}`, payload)
    else await api.post('/departments', payload)
    toast.show(editing.value ? 'واحد به‌روزرسانی شد' : 'واحد جدید ثبت شد')
    showForm.value = false
    load()
  } catch (e) {
    if (e.errors && Object.keys(e.errors).length) setErrors(e.errors)
    else toast.show(e.message, 'danger')
  } finally {
    saving.value = false
  }
}

async function remove(d) {
  if (d.staff_count > 0) {
    toast.show('این واحد دارای کارمند است و قابل حذف نیست؛ ابتدا کارکنان را جابه‌جا کنید.', 'danger')
    return
  }
  const ok = await confirmStore.ask({ title: 'حذف واحد', message: `واحد «${d.name}» حذف شود؟`, confirmText: 'حذف', tone: 'danger' })
  if (!ok) return
  try {
    await api.del(`/departments/${d.id}`)
    toast.show('واحد حذف شد')
    load()
  } catch (e) {
    toast.show(e.message, 'danger')
  }
}

onMounted(load)
</script>

<template>
  <div class="flex flex-col gap-[18px]" style="max-width: 1280px">
    <div class="flex items-center justify-between gap-4">
      <div>
        <h1 class="m-0 text-3xl font-bold text-ink-900">واحدهای سازمانی</h1>
        <p class="mt-1.5 text-ink-600">دپارتمان‌ها و تعداد کارکنان هر واحد</p>
      </div>
      <button v-if="canManage" @click="openCreate"
        class="flex h-11 items-center gap-2 rounded-xl px-5 text-sm font-semibold text-white shadow-brand" style="background: var(--grad-brand)">
        <Icon name="plus" :size="18" /> افزودن واحد
      </button>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <div v-for="d in departments" :key="d.id" class="group relative overflow-hidden rounded-2xl border border-ink-200 bg-white shadow-card">
        <div class="h-1" style="background: var(--grad-brand)"></div>
        <div class="flex flex-col gap-3 p-5">
          <div class="flex items-start justify-between gap-3">
            <div class="flex h-11 w-11 items-center justify-center rounded-xl text-white" style="background: var(--grad-teal)">
              <Icon name="building" :size="22" />
            </div>
            <span class="num rounded-full bg-blue-50 px-3 py-1 text-[12px] font-semibold text-brand-strong">
              {{ fa(d.staff_count) }} نفر
            </span>
          </div>
          <div>
            <div class="text-[15px] font-bold text-ink-900">{{ d.name }}</div>
            <div class="num mt-0.5 text-[11.5px] font-semibold tracking-wide text-ink-400">{{ d.code }}</div>
          </div>
          <p class="m-0 text-[13px] leading-6 text-ink-600">{{ d.description }}</p>
          <div v-if="canManage" class="mt-1 flex gap-1.5">
            <button @click="openEdit(d)" class="flex h-8 items-center gap-1 rounded-lg border border-ink-200 bg-white px-3 text-[12px] text-ink-700 hover:bg-ink-100">✎ ویرایش</button>
            <button @click="remove(d)" class="flex h-8 items-center gap-1 rounded-lg border border-danger-500 bg-white px-3 text-[12px] text-danger-500 hover:bg-danger-50">× حذف</button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <DepartmentFormModal v-if="showForm" :department="editing" :saving="saving" @save="save" @close="showForm = false" />
</template>
