<script setup>
import { ref, onMounted } from 'vue'
import { api } from '../services/api'
import { useToastStore } from '../stores/toast'
import { useConfirmStore } from '../stores/confirm'
import AppCard from '../components/ui/AppCard.vue'
import AppTag from '../components/ui/AppTag.vue'
import Icon from '../components/ui/Icon.vue'
import RoleFormModal from '../components/modals/RoleFormModal.vue'

const toast = useToastStore()
const confirmStore = useConfirmStore()
const roles = ref([])
const permissions = ref([])
const showForm = ref(false)
const editing = ref(null)
const saving = ref(false)

function fa(n) { return String(n).replace(/[0-9]/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]) }
function labelFor(v) { return permissions.value.find((p) => p.value === v)?.label || v }

async function load() {
  const res = await api.get('/roles')
  roles.value = res.items || []
  permissions.value = await api.get('/roles/permissions').catch(() => [])
}

function openCreate() { editing.value = null; showForm.value = true }
function openEdit(r) { editing.value = r; showForm.value = true }

async function save(payload, setErrors) {
  saving.value = true
  try {
    if (editing.value) await api.put(`/roles/${editing.value.id}`, payload)
    else await api.post('/roles', payload)
    toast.show(editing.value ? 'نقش به‌روزرسانی شد' : 'نقش جدید ثبت شد')
    showForm.value = false
    load()
  } catch (e) {
    if (e.errors && Object.keys(e.errors).length) setErrors(e.errors)
    else toast.show(e.message, 'danger')
  } finally {
    saving.value = false
  }
}

async function remove(r) {
  if (r.user_count > 0) { toast.show('این نقش به کاربران تخصیص یافته و قابل حذف نیست.', 'danger'); return }
  const ok = await confirmStore.ask({ title: 'حذف نقش', message: `نقش «${r.name}» حذف شود؟`, confirmText: 'حذف', tone: 'danger' })
  if (!ok) return
  try {
    await api.del(`/roles/${r.id}`)
    toast.show('نقش حذف شد')
    load()
  } catch (e) {
    toast.show(e.message, 'danger')
  }
}

onMounted(load)
</script>

<template>
  <div class="flex flex-col gap-[18px]" style="max-width: 1180px">
    <div class="flex items-center justify-between gap-4">
      <div>
        <h1 class="m-0 text-3xl font-bold text-ink-900">نقش‌ها و دسترسی‌ها</h1>
        <p class="mt-1.5 text-ink-600">تعریف سطوح دسترسی و اختصاص مجوزها به هر نقش</p>
      </div>
      <button @click="openCreate"
        class="flex h-11 items-center gap-2 rounded-xl px-5 text-sm font-semibold text-white shadow-brand" style="background: var(--grad-brand)">
        <Icon name="plus" :size="18" /> افزودن نقش
      </button>
    </div>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
      <AppCard v-for="r in roles" :key="r.id" :padding="true">
        <div class="flex items-start justify-between gap-3">
          <div>
            <div class="text-[15px] font-bold text-ink-900">{{ r.name }}</div>
            <div class="mt-1 flex items-center gap-2">
              <AppTag tone="brand">{{ r.level_label }}</AppTag>
              <span class="num text-[12px] text-ink-500">{{ fa(r.user_count) }} کاربر</span>
            </div>
          </div>
          <div class="flex gap-1.5">
            <button @click="openEdit(r)" title="ویرایش" class="h-8 w-8 rounded-lg border border-ink-200 bg-white text-ink-600 hover:bg-ink-100">✎</button>
            <button @click="remove(r)" title="حذف" class="h-8 w-8 rounded-lg border border-danger-500 bg-white text-[15px] text-danger-500 hover:bg-danger-50">×</button>
          </div>
        </div>
        <div class="mt-3 flex flex-wrap gap-1.5">
          <span v-for="p in (r.permissions || [])" :key="p" class="rounded-md bg-ink-100 px-2 py-1 text-[11px] text-ink-700">{{ labelFor(p) }}</span>
          <span v-if="!(r.permissions && r.permissions.length)" class="text-[12px] text-ink-400">بدون دسترسی</span>
        </div>
      </AppCard>
    </div>
  </div>

  <RoleFormModal v-if="showForm" :role="editing" :permissions="permissions" :saving="saving" @save="save" @close="showForm = false" />
</template>
