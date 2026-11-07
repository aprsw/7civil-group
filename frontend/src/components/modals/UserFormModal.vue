<script setup>
import { reactive } from 'vue'
import JalaliDatePicker from '../ui/JalaliDatePicker.vue'

const props = defineProps({
  user: { type: Object, default: null },
  roles: { type: Array, default: () => [] },
  departments: { type: Array, default: () => [] },
  saving: { type: Boolean, default: false },
})
const emit = defineEmits(['save', 'close'])

const isEdit = !!props.user
const p = props.user?.profile || {}
const form = reactive({
  name: props.user?.name || '',
  username: props.user?.username || '',
  password: '',
  email: props.user?.email || '',
  role_id: props.user?.role?.id || '',
  department_id: props.user?.department?.id || '',
  active: props.user ? !!props.user.active : true,
  mobile: p.mobile || '',
  phone: p.phone || '',
  birth_date: p.birth_date || '',
  address: p.address || '',
  description: p.description || '',
})
const errors = reactive({})

function submit() {
  Object.keys(errors).forEach((k) => delete errors[k])
  emit('save', { ...form }, (serverErrors) => {
    Object.assign(errors, serverErrors || {})
  })
}
</script>

<template>
  <div class="fixed inset-0 z-[80] flex items-center justify-center p-6" style="background: rgba(13,21,38,0.48); backdrop-filter: blur(3px)" @click.self="emit('close')">
    <div class="w-[min(520px,100%)] overflow-hidden rounded-2xl bg-white shadow-pop" style="animation: afModalIn .22s ease">
      <div class="flex items-center justify-between border-b border-ink-200 px-5 py-4">
        <b class="text-[15px] text-ink-900">{{ isEdit ? 'ویرایش کارمند' : 'افزودن کارمند' }}</b>
        <button @click="emit('close')" class="text-[20px] text-ink-500">×</button>
      </div>
      <div class="af-scroll grid max-h-[70vh] grid-cols-2 gap-3.5 overflow-y-auto p-5">
        <label class="col-span-2 flex flex-col gap-1">
          <span class="text-[12px] text-ink-600">نام و نام خانوادگی</span>
          <input v-model="form.name" class="af-inp" />
          <span v-if="errors.name" class="text-[11px] text-danger-600">{{ errors.name }}</span>
        </label>
        <label class="flex flex-col gap-1">
          <span class="text-[12px] text-ink-600">نام کاربری</span>
          <input v-model="form.username" class="af-inp" dir="ltr" />
          <span v-if="errors.username" class="text-[11px] text-danger-600">{{ errors.username }}</span>
        </label>
        <label class="flex flex-col gap-1">
          <span class="text-[12px] text-ink-600">{{ isEdit ? 'رمز جدید (اختیاری)' : 'رمز عبور' }}</span>
          <input v-model="form.password" type="password" class="af-inp" dir="ltr" />
          <span v-if="errors.password" class="text-[11px] text-danger-600">{{ errors.password }}</span>
        </label>
        <label class="flex flex-col gap-1">
          <span class="text-[12px] text-ink-600">نقش</span>
          <select v-model="form.role_id" class="af-inp">
            <option value="">— انتخاب —</option>
            <option v-for="r in roles" :key="r.id" :value="r.id">{{ r.name }} (سطح {{ r.level }})</option>
          </select>
          <span v-if="errors.role_id" class="text-[11px] text-danger-600">{{ errors.role_id }}</span>
        </label>
        <label class="flex flex-col gap-1">
          <span class="text-[12px] text-ink-600">واحد</span>
          <select v-model="form.department_id" class="af-inp">
            <option value="">— انتخاب —</option>
            <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
          </select>
          <span v-if="errors.department_id" class="text-[11px] text-danger-600">{{ errors.department_id }}</span>
        </label>
        <label class="col-span-2 flex flex-col gap-1">
          <span class="text-[12px] text-ink-600">ایمیل (اختیاری)</span>
          <input v-model="form.email" class="af-inp" dir="ltr" />
        </label>

        <div class="col-span-2 mt-1 border-t border-ink-100 pt-3 text-[12px] font-semibold text-ink-500">اطلاعات تماس و پروفایل (اختیاری)</div>
        <label class="flex flex-col gap-1">
          <span class="text-[12px] text-ink-600">موبایل</span>
          <input v-model="form.mobile" class="af-inp" dir="ltr" />
        </label>
        <label class="flex flex-col gap-1">
          <span class="text-[12px] text-ink-600">تلفن</span>
          <input v-model="form.phone" class="af-inp" dir="ltr" />
        </label>
        <label class="flex flex-col gap-1">
          <span class="text-[12px] text-ink-600">تاریخ تولد</span>
          <JalaliDatePicker v-model="form.birth_date" placeholder="۱۳۷۰/۰۱/۰۱" />
        </label>
        <label class="flex flex-col gap-1">
          <span class="text-[12px] text-ink-600">نشانی</span>
          <input v-model="form.address" class="af-inp" />
        </label>
        <label class="col-span-2 flex flex-col gap-1">
          <span class="text-[12px] text-ink-600">توضیحات</span>
          <textarea v-model="form.description" rows="2" class="af-inp" style="height:auto; padding:9px 11px"></textarea>
        </label>

        <label v-if="isEdit" class="col-span-2 flex items-center gap-2.5 rounded-xl border border-ink-200 bg-ink-50 px-3.5 py-2.5">
          <input type="checkbox" v-model="form.active" class="h-4 w-4 accent-[var(--color-brand)]" />
          <span class="text-[13px] text-ink-800">حساب کاربری فعال است</span>
          <span class="mr-auto text-[11px] text-ink-500">کاربر غیرفعال نمی‌تواند وارد شود</span>
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
