<script setup>
import { ref, onMounted, computed } from 'vue'
import { api } from '../services/api'
import { useAuthStore } from '../stores/auth'
import { useToastStore } from '../stores/toast'
import { useConfirmStore } from '../stores/confirm'
import AppCard from '../components/ui/AppCard.vue'
import AppAvatar from '../components/ui/AppAvatar.vue'
import AppTag from '../components/ui/AppTag.vue'
import Icon from '../components/ui/Icon.vue'
import UserFormModal from '../components/modals/UserFormModal.vue'

const auth = useAuthStore()
const toast = useToastStore()
const confirmStore = useConfirmStore()

const users = ref([])
const roles = ref([])
const departments = ref([])
const error = ref('')
const showForm = ref(false)
const editing = ref(null)
const saving = ref(false)

const canManage = auth.can('users.manage')
const onlineUsers = computed(() => users.value.filter((u) => u.online))

async function load() {
  try {
    users.value = await api.get('/users')
  } catch (e) {
    error.value = e.message
    return
  }
  if (canManage) {
    try {
      const rs = await api.get('/roles')
      roles.value = rs.items || []
      const deps = await api.get('/departments')
      departments.value = deps.items || []
    } catch { /* ignore */ }
  }
}

function openCreate() { editing.value = null; showForm.value = true }
function openEdit(u) { editing.value = u; showForm.value = true }

async function save(payload, setErrors) {
  saving.value = true
  try {
    if (editing.value) await api.put(`/users/${editing.value.id}`, payload)
    else await api.post('/users', payload)
    toast.show(editing.value ? 'اطلاعات کاربر به‌روزرسانی شد' : 'کاربر جدید ثبت شد')
    showForm.value = false
    load()
  } catch (e) {
    if (e.errors && Object.keys(e.errors).length) setErrors(e.errors)
    else toast.show(e.message, 'danger')
  } finally {
    saving.value = false
  }
}

async function remove(u) {
  const ok = await confirmStore.ask({ title: 'حذف کاربر', message: `کاربر «${u.name}» حذف شود؟ این عملیات قابل بازگشت نیست.`, confirmText: 'حذف', tone: 'danger' })
  if (!ok) return
  try {
    await api.del(`/users/${u.id}`)
    toast.show('کاربر حذف شد')
    load()
  } catch (e) {
    toast.show(e.message, 'danger')
  }
}

async function toggleActive(u) {
  try {
    await api.put(`/users/${u.id}`, {
      name: u.name, username: u.username, email: u.email || '',
      role_id: u.role?.id, department_id: u.department?.id, active: !u.active,
    })
    toast.show(u.active ? 'کاربر غیرفعال شد' : 'کاربر فعال شد')
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
        <h1 class="m-0 text-3xl font-bold text-ink-900">کاربران، نقش و واحد</h1>
        <p class="mt-1.5 text-ink-600">ساختار سازمانی، سطوح دسترسی و وضعیت حساب‌ها</p>
      </div>
      <button v-if="canManage" @click="openCreate"
        class="flex h-11 items-center gap-2 rounded-xl px-5 text-sm font-semibold text-white shadow-brand" style="background: var(--grad-brand)">
        <Icon name="plus" :size="18" /> افزودن کاربر
      </button>
    </div>

    <div v-if="error" class="rounded-xl border border-warning-500 bg-warning-50 px-4 py-3 text-[13px] text-warning-600">
      🔒 {{ error }}
    </div>

    <template v-else>
      <!-- Online users strip -->
      <div v-if="onlineUsers.length" class="flex flex-wrap items-center gap-2 rounded-2xl border border-ink-200 bg-white px-5 py-3.5 shadow-card">
        <span class="ml-1 flex items-center gap-1.5 text-[12.5px] font-semibold text-ink-600">
          <span class="h-2 w-2 rounded-full bg-teal-500"></span> آنلاین‌ها
        </span>
        <span v-for="u in onlineUsers" :key="u.id"
          class="inline-flex items-center gap-1.5 rounded-full bg-teal-50 py-1 pr-1 pl-3 text-[12px] text-ink-800">
          <AppAvatar :name="u.name" :size="22" /> {{ u.name }}
        </span>
      </div>

      <AppCard title="فهرست کاربران" :subtitle="`${users.length} نفر`" :padding="false">
        <table class="w-full border-collapse">
          <thead>
            <tr>
              <th v-for="h in ['نام', 'نقش', 'واحد', 'سطح دسترسی', 'وضعیت', '']" :key="h"
                class="bg-ink-50 px-4 py-2.5 text-start text-[11.5px] font-semibold text-ink-500"
                :class="h === 'سطح دسترسی' ? 'text-end' : ''">{{ h }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="u in users" :key="u.id" class="border-b border-ink-200 last:border-0 hover:bg-blue-50"
              :class="{ 'opacity-55': !u.active }">
              <td class="px-4 py-3">
                <span class="inline-flex items-center gap-2">
                  <span class="relative">
                    <AppAvatar :name="u.name" :photo="u.photo_url" :size="28" />
                    <span v-if="u.online" class="absolute -bottom-0.5 -left-0.5 h-2.5 w-2.5 rounded-full border-2 border-white bg-teal-500"></span>
                  </span>
                  <b class="text-ink-900">{{ u.name }}</b>
                </span>
              </td>
              <td class="px-4 py-3 text-sm text-ink-800">{{ u.role?.name }}</td>
              <td class="px-4 py-3 text-sm text-ink-800">{{ u.department?.name }}</td>
              <td class="px-4 py-3 text-end"><AppTag tone="brand">{{ u.role?.level_label }}</AppTag></td>
              <td class="px-4 py-3">
                <AppTag :tone="u.active ? 'teal' : 'neutral'">{{ u.active ? 'فعال' : 'غیرفعال' }}</AppTag>
              </td>
              <td class="px-4 py-3 text-end">
                <div v-if="canManage" class="flex justify-end gap-1.5">
                  <button @click="toggleActive(u)" :title="u.active ? 'غیرفعال‌سازی' : 'فعال‌سازی'"
                    class="h-8 rounded-lg border border-ink-200 bg-white px-2.5 text-[11.5px] text-ink-600 hover:bg-ink-100">
                    {{ u.active ? 'غیرفعال' : 'فعال' }}
                  </button>
                  <button @click="openEdit(u)" title="ویرایش" class="h-8 w-8 rounded-lg border border-ink-200 bg-white text-ink-600 hover:bg-ink-100">✎</button>
                  <button @click="remove(u)" title="حذف" class="h-8 w-8 rounded-lg border border-danger-500 bg-white text-[15px] text-danger-500 hover:bg-danger-50">×</button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </AppCard>
    </template>
  </div>

  <UserFormModal v-if="showForm" :user="editing" :roles="roles" :departments="departments" :saving="saving" @save="save" @close="showForm = false" />
</template>
