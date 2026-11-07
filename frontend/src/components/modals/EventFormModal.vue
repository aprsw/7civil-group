<script setup>
import { reactive } from 'vue'
import JalaliDatePicker from '../ui/JalaliDatePicker.vue'

const props = defineProps({
  event: { type: Object, default: null },
  saving: { type: Boolean, default: false },
})
const emit = defineEmits(['save', 'close'])

const isEdit = !!props.event
const form = reactive({
  title: props.event?.title || '',
  event_date: props.event?.event_date || '',
  location: props.event?.location || '',
  description: props.event?.description || '',
})
const errors = reactive({})

function submit() {
  Object.keys(errors).forEach((k) => delete errors[k])
  emit('save', { ...form }, (serverErrors) => Object.assign(errors, serverErrors || {}))
}
</script>

<template>
  <div class="fixed inset-0 z-[80] flex items-center justify-center p-6" style="background: rgba(13,21,38,0.48); backdrop-filter: blur(3px)" @click.self="emit('close')">
    <div class="w-[min(500px,100%)] overflow-hidden rounded-2xl bg-white shadow-pop" style="animation: afModalIn .22s ease">
      <div class="flex items-center justify-between border-b border-ink-200 px-5 py-4">
        <b class="text-[15px] text-ink-900">{{ isEdit ? 'ویرایش رویداد' : 'افزودن رویداد' }}</b>
        <button @click="emit('close')" class="text-[20px] text-ink-500">×</button>
      </div>
      <div class="flex flex-col gap-3.5 p-5">
        <label class="flex flex-col gap-1">
          <span class="text-[12px] text-ink-600">عنوان</span>
          <input v-model="form.title" class="af-inp" />
          <span v-if="errors.title" class="text-[11px] text-danger-600">{{ errors.title }}</span>
        </label>
        <div class="grid grid-cols-2 gap-3.5">
          <label class="flex flex-col gap-1">
            <span class="text-[12px] text-ink-600">تاریخ (جلالی)</span>
            <JalaliDatePicker v-model="form.event_date" placeholder="۱۴۰۵/۰۵/۱۵" />
            <span v-if="errors.event_date" class="text-[11px] text-danger-600">{{ errors.event_date }}</span>
          </label>
          <label class="flex flex-col gap-1">
            <span class="text-[12px] text-ink-600">مکان (اختیاری)</span>
            <input v-model="form.location" class="af-inp" />
          </label>
        </div>
        <label class="flex flex-col gap-1">
          <span class="text-[12px] text-ink-600">توضیحات (اختیاری)</span>
          <textarea v-model="form.description" rows="3" class="af-inp" style="height:auto; padding:9px 11px"></textarea>
        </label>
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
