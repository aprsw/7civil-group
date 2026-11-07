<script setup>
import { ref, computed, onMounted } from 'vue'
import { api } from '../services/api'
import { useToastStore } from '../stores/toast'
import AppCard from '../components/ui/AppCard.vue'
import Icon from '../components/ui/Icon.vue'

const toast = useToastStore()
const roles = ref([])
const matrix = ref({})           // fromRoleId -> [toRoleIds]
const selectedFrom = ref(null)
const saving = ref(false)

async function load() {
  const res = await api.get('/messaging-rules')
  roles.value = res.roles || []
  matrix.value = res.role_matrix || {}
  if (roles.value.length && selectedFrom.value === null) selectedFrom.value = roles.value[0].id
}

const configured = (id) => Object.prototype.hasOwnProperty.call(matrix.value, id)
const allowedFor = computed(() => matrix.value[selectedFrom.value] || [])

function isAllowed(toId) {
  if (!configured(selectedFrom.value)) return true // default hierarchy
  return allowedFor.value.includes(toId)
}
function toggle(toId) {
  const current = configured(selectedFrom.value) ? [...allowedFor.value] : roles.value.map((r) => r.id)
  const i = current.indexOf(toId)
  if (i === -1) current.push(toId); else current.splice(i, 1)
  matrix.value = { ...matrix.value, [selectedFrom.value]: current }
}

async function save() {
  saving.value = true
  try {
    await api.put(`/messaging-rules/role/${selectedFrom.value}`, { to_role_ids: matrix.value[selectedFrom.value] || [] })
    toast.show('مجوزهای مکاتبه ذخیره شد')
    load()
  } catch (e) { toast.show(e.message, 'danger') } finally { saving.value = false }
}
async function reset() {
  saving.value = true
  try {
    await api.put(`/messaging-rules/role/${selectedFrom.value}`, { to_role_ids: [] })
    toast.show('به حالت پیش‌فرض (سلسله‌مراتبی) بازگشت')
    load()
  } catch (e) { toast.show(e.message, 'danger') } finally { saving.value = false }
}

onMounted(load)
</script>

<template>
  <div class="flex flex-col gap-[18px]" style="max-width: 1080px">
    <div>
      <h1 class="m-0 text-3xl font-bold text-ink-900">مجوزهای مکاتبه (سمت‌ها)</h1>
      <p class="mt-1.5 text-ink-600">تعیین اینکه هر سمت در نامهٔ عادی به کدام سمت‌ها می‌تواند نامه بزند؛ فهرست گیرندگان بر همین اساس نمایش داده می‌شود.</p>
    </div>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-[260px_1fr]">
      <!-- source role list -->
      <AppCard title="سمت مبدأ" :padding="false">
        <div class="af-scroll flex max-h-[560px] flex-col overflow-y-auto">
          <button v-for="r in roles" :key="r.id" @click="selectedFrom = r.id"
            class="flex items-center justify-between border-b border-ink-100 px-4 py-3 text-right text-[13px] last:border-0"
            :class="selectedFrom === r.id ? 'bg-blue-50 font-semibold text-brand-strong' : 'text-ink-800 hover:bg-ink-50'">
            <span>{{ r.name }}</span>
            <span v-if="configured(r.id)" class="rounded-full bg-warning-50 px-2 py-0.5 text-[10.5px] text-warning-600">سفارشی</span>
            <span v-else class="text-[10.5px] text-ink-400">پیش‌فرض</span>
          </button>
        </div>
      </AppCard>

      <!-- target roles -->
      <AppCard>
        <div class="mb-3 flex items-center justify-between gap-3">
          <div class="text-[14px] font-bold text-ink-900">
            سمت‌های مجاز برای مکاتبه
            <span v-if="!configured(selectedFrom)" class="mr-2 text-[12px] font-normal text-ink-500">(اکنون: سلسله‌مراتبی پیش‌فرض)</span>
          </div>
        </div>
        <div class="grid grid-cols-1 gap-1.5 sm:grid-cols-2">
          <label v-for="r in roles" :key="r.id"
            class="flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-2 text-[12.5px]"
            :class="[isAllowed(r.id) ? 'border-blue-300 bg-blue-50 text-brand-strong' : 'border-ink-200 bg-ink-50 text-ink-800',
                     r.id === selectedFrom ? 'opacity-60' : '']">
            <input type="checkbox" :checked="isAllowed(r.id)" :disabled="r.id === selectedFrom" @change="toggle(r.id)" class="h-4 w-4 accent-[var(--color-brand)]" />
            <span class="flex-1">{{ r.name }}</span>
            <span v-if="r.id === selectedFrom" class="text-[10.5px] text-ink-400">خودِ سمت</span>
          </label>
        </div>
        <div class="mt-4 flex flex-wrap items-center gap-2">
          <button @click="save" :disabled="saving"
            class="rounded-[10px] px-6 py-2.5 text-[13px] font-semibold text-white shadow-brand disabled:opacity-60" style="background: var(--grad-brand)">
            {{ saving ? 'در حال ذخیره…' : 'ذخیرهٔ مجوزها' }}
          </button>
          <button @click="reset" :disabled="saving" class="rounded-[10px] border border-ink-300 bg-white px-4 py-2.5 text-[13px] text-ink-800">
            بازگشت به پیش‌فرض
          </button>
          <div class="mr-auto flex items-center gap-1.5 text-[11.5px] text-ink-500">
            <Icon name="cog" :size="14" /> سمت بدون تنظیم سفارشی از قاعدهٔ سلسله‌مراتبی پیروی می‌کند.
          </div>
        </div>
      </AppCard>
    </div>
  </div>
</template>
