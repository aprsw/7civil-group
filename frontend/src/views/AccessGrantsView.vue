<script setup>
import { ref, onMounted } from 'vue'
import { api } from '../services/api'
import { useToastStore } from '../stores/toast'
import AppCard from '../components/ui/AppCard.vue'

const toast = useToastStore()
const users = ref([])
const grantable = ref([])
const loading = ref(true)
const savingId = ref(null)

async function load() {
  loading.value = true
  try {
    const res = await api.get('/permission-grants')
    users.value = res.users
    grantable.value = res.grantable
  } catch (e) { toast.show(e.message, 'danger') } finally { loading.value = false }
}
function inherited(u, perm) { return (u.role_permissions || []).includes(perm) }
function has(u, perm) { return (u.grants || []).includes(perm) }

async function toggle(u, perm) {
  if (inherited(u, perm)) return // already provided by role
  const next = has(u, perm) ? u.grants.filter((p) => p !== perm) : [...(u.grants || []), perm]
  savingId.value = u.id
  try {
    const res = await api.put(`/permission-grants/user/${u.id}`, { permissions: next })
    u.grants = res.grants
    toast.show('دسترسی به‌روزرسانی شد')
  } catch (e) { toast.show(e.message, 'danger') } finally { savingId.value = null }
}
onMounted(load)
</script>

<template>
  <div class="flex flex-col gap-5" style="max-width: 1080px">
    <div>
      <h1 class="m-0 text-3xl font-bold text-ink-900">دسترسی‌های کاربران</h1>
      <p class="mt-1.5 text-ink-600">به هر کاربر می‌توانید دسترسی‌های ویژه بدهید — مثلاً دسترسی به «دبیرخانه» — بدون تغییر نقش او. دسترسی‌هایی که از نقش کاربر می‌آیند، ثابت و غیرقابل‌تغییر نمایش داده می‌شوند.</p>
    </div>

    <AppCard :padding="false">
      <div v-if="loading" class="p-8 text-center text-[13px] text-ink-400">در حال بارگذاری…</div>
      <table v-else class="w-full border-collapse">
        <thead>
          <tr>
            <th class="bg-ink-50 px-4 py-2.5 text-start text-[11.5px] font-semibold text-ink-500">کاربر</th>
            <th v-for="g in grantable" :key="g.value" class="bg-ink-50 px-3 py-2.5 text-center text-[11.5px] font-semibold text-ink-500">{{ g.label }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="u in users" :key="u.id" class="border-b border-ink-100 last:border-0" :class="savingId === u.id ? 'opacity-60' : ''">
            <td class="px-4 py-3">
              <div class="text-[13px] font-semibold text-ink-900">{{ u.name }}</div>
              <div class="text-[11px] text-ink-400">{{ u.role }}<span v-if="u.department"> · {{ u.department }}</span></div>
            </td>
            <td v-for="g in grantable" :key="g.value" class="px-3 py-3 text-center">
              <span v-if="inherited(u, g.value)" class="inline-flex items-center gap-1 rounded-full bg-ink-100 px-2 py-0.5 text-[10.5px] text-ink-500" title="از نقش کاربر">از نقش ✓</span>
              <button v-else @click="toggle(u, g.value)"
                class="inline-flex h-6 w-11 items-center rounded-full px-0.5 transition"
                :class="has(u, g.value) ? 'justify-end bg-[var(--color-brand)]' : 'justify-start bg-ink-200'">
                <span class="h-5 w-5 rounded-full bg-white shadow"></span>
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </AppCard>
  </div>
</template>
