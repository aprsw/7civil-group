<script setup>
import { reactive } from 'vue'

const props = defineProps({
  role: { type: Object, default: null },
  permissions: { type: Array, default: () => [] }, // [{value,label}]
  saving: { type: Boolean, default: false },
})
const emit = defineEmits(['save', 'close'])

const isEdit = !!props.role
const form = reactive({
  name: props.role?.name || '',
  level: props.role?.level || 5,
  permissions: [...(props.role?.permissions || [])],
})
const errors = reactive({})

function toggle(v) {
  const i = form.permissions.indexOf(v)
  if (i === -1) form.permissions.push(v)
  else form.permissions.splice(i, 1)
}

function submit() {
  Object.keys(errors).forEach((k) => delete errors[k])
  emit('save', { name: form.name, level: form.level, permissions: form.permissions },
    (serverErrors) => Object.assign(errors, serverErrors || {}))
}
</script>

<template>
  <div class="fixed inset-0 z-[80] flex items-center justify-center p-6" style="background: rgba(13,21,38,0.48); backdrop-filter: blur(3px)" @click.self="emit('close')">
    <div class="w-[min(560px,100%)] overflow-hidden rounded-2xl bg-white shadow-pop" style="animation: afModalIn .22s ease">
      <div class="flex items-center justify-between border-b border-ink-200 px-5 py-4">
        <b class="text-[15px] text-ink-900">{{ isEdit ? 'ویرایش نقش' : 'افزودن نقش' }}</b>
        <button @click="emit('close')" class="text-[20px] text-ink-500">×</button>
      </div>
      <div class="af-scroll flex max-h-[70vh] flex-col gap-3.5 overflow-y-auto p-5">
        <div class="grid grid-cols-3 gap-3.5">
          <label class="col-span-2 flex flex-col gap-1">
            <span class="text-[12px] text-ink-600">نام نقش</span>
            <input v-model="form.name" class="af-inp" />
            <span v-if="errors.name" class="text-[11px] text-danger-600">{{ errors.name }}</span>
          </label>
          <label class="flex flex-col gap-1">
            <span class="text-[12px] text-ink-600">سطح (۱ بالاترین)</span>
            <input v-model.number="form.level" type="number" min="1" max="20" class="af-inp num" dir="ltr" />
            <span v-if="errors.level" class="text-[11px] text-danger-600">{{ errors.level }}</span>
          </label>
        </div>

        <div class="flex flex-col gap-1.5">
          <span class="text-[12px] font-semibold text-ink-600">دسترسی‌ها</span>
          <span v-if="errors.permissions" class="text-[11px] text-danger-600">{{ errors.permissions }}</span>
          <div class="grid grid-cols-2 gap-1.5">
            <label v-for="p in permissions" :key="p.value"
              class="flex cursor-pointer items-center gap-2 rounded-lg border border-ink-200 bg-ink-50 px-3 py-2 text-[12.5px] text-ink-800 hover:bg-blue-50"
              :class="form.permissions.includes(p.value) ? 'border-blue-300 bg-blue-50' : ''">
              <input type="checkbox" :checked="form.permissions.includes(p.value)" @change="toggle(p.value)" class="h-4 w-4 accent-[var(--color-brand)]" />
              <span class="flex-1">{{ p.label }}</span>
            </label>
          </div>
        </div>
      </div>
      <div class="flex justify-end gap-2.5 border-t border-ink-200 px-5 py-4">
        <button @click="emit('close')" class="rounded-[10px] border border-ink-300 bg-white px-4 py-2 text-[13px] text-ink-800">انصراف</button>
        <button @click="submit" :disabled="saving" class="rounded-[10px] px-5 py-2 text-[13px] font-semibold text-white shadow-brand disabled:opacity-60" style="background: var(--grad-brand)">
          {{ saving ? 'در حال ذخیره…' : 'ذخیره' }}
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.af-inp { height: 40px; border: 1px solid var(--color-ink-200); border-radius: 10px; padding: 0 11px; font-size: 13.5px; background: #fff; color: var(--color-ink-800); }
</style>
