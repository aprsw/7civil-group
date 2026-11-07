<script setup>
import { ref, reactive, onMounted } from 'vue'
import { api } from '../../services/api'
import Icon from '../ui/Icon.vue'
import PeoplePicker from '../ui/PeoplePicker.vue'

const props = defineProps({
  letterId: { type: [Number, String], required: true },
  saving: { type: Boolean, default: false },
  mode: { type: String, default: 'refer' }, // 'refer' | 'reply'
  creator: { type: Object, default: null }, // { id, name } — reply target
})
const emit = defineEmits(['refer', 'close'])
const isReply = props.mode === 'reply'

const meta = ref({ recipients: [], departments: [], referral_types: [] })
const form = reactive({ referral_type: 'action', recipient_ids: [], recipient_department_ids: [], note: '' })
const pickPerson = ref('')
const pickDept = ref('')
const error = ref('')

onMounted(async () => {
  try { meta.value = await api.get('/meta/compose') } catch { /* ignore */ }
})

function addPerson() { const id = pickPerson.value; if (id && !form.recipient_ids.includes(id)) form.recipient_ids.push(id); pickPerson.value = '' }
function addDept() { const id = pickDept.value; if (id && !form.recipient_department_ids.includes(id)) form.recipient_department_ids.push(id); pickDept.value = '' }
function removePerson(id) { form.recipient_ids = form.recipient_ids.filter((x) => x !== id) }
function removeDept(id) { form.recipient_department_ids = form.recipient_department_ids.filter((x) => x !== id) }
function personName(id) {
  if (isReply && props.creator && String(props.creator.id) === String(id)) return props.creator.name
  return meta.value.recipients.find((r) => String(r.id) === String(id))?.name || id
}
function deptName(id) { return meta.value.departments.find((d) => String(d.id) === String(id))?.name || id }

function submit() {
  error.value = ''
  // In reply mode the creator is always included server-side, so no target is
  // required here; the sender is reached even if not in the messaging list.
  if (!isReply && !form.recipient_ids.length && !form.recipient_department_ids.length) {
    error.value = 'حداقل یک گیرنده برای ارجاع انتخاب کنید.'
    return
  }
  emit('refer', { ...form })
}
</script>

<template>
  <div class="fixed inset-0 z-[80] flex items-center justify-center p-6" style="background: rgba(13,21,38,0.48); backdrop-filter: blur(3px)" @click.self="emit('close')">
    <div class="w-[min(520px,100%)] overflow-hidden rounded-2xl bg-white shadow-pop" style="animation: afModalIn .22s ease">
      <div class="flex items-center justify-between border-b border-ink-200 px-5 py-4">
        <b class="text-[15px] text-ink-900">{{ isReply ? 'پاسخ نامه' : 'ارجاع نامه' }}</b>
        <button @click="emit('close')" class="text-[20px] text-ink-500">×</button>
      </div>
      <div class="af-scroll flex max-h-[70vh] flex-col gap-3.5 overflow-y-auto p-5">
        <!-- reply target banner -->
        <div v-if="isReply" class="rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-[12.5px] text-brand-strong">
          این پاسخ به فرستندهٔ نامه <b v-if="creator">«{{ creator.name }}»</b> ارسال می‌شود. نامه سرِ جای خود می‌ماند و پاسخ مانند یک ارجاع ثبت می‌گردد. در صورت نیاز می‌توانید افراد دیگری هم اضافه کنید.
        </div>

        <!-- referral type (not for reply) -->
        <div v-if="!isReply" class="flex flex-col gap-1.5">
          <span class="text-[12px] text-ink-600">نوع ارجاع</span>
          <div class="flex gap-1.5 rounded-lg bg-ink-100 p-1 text-[12.5px]">
            <button v-for="t in meta.referral_types" :key="t.value" @click="form.referral_type = t.value"
              class="flex-1 rounded-md py-1.5" :class="form.referral_type === t.value ? 'bg-white font-semibold text-brand-strong shadow-card' : 'text-ink-600'">
              {{ t.label }}
            </button>
          </div>
        </div>

        <!-- persons (typeable search) -->
        <div class="flex flex-col gap-1.5">
          <span class="text-[12px] text-ink-600">{{ isReply ? 'افراد دیگر (اختیاری)' : 'افراد' }}</span>
          <PeoplePicker :people="meta.recipients" :exclude="form.recipient_ids"
            placeholder="جستجوی نام، نام خانوادگی یا سمت…"
            @pick="(id) => { if (!form.recipient_ids.includes(String(id))) form.recipient_ids.push(String(id)) }" />
        </div>
        <!-- units -->
        <div class="flex flex-col gap-1.5">
          <span class="text-[12px] text-ink-600">واحدها</span>
          <div class="flex gap-1.5">
            <select v-model="pickDept" class="af-inp flex-1">
              <option value="">— افزودن واحد —</option>
              <option v-for="d in meta.departments" :key="d.id" :value="String(d.id)" :disabled="form.recipient_department_ids.includes(String(d.id))">{{ d.name }}</option>
            </select>
            <button @click="addDept" :disabled="!pickDept" class="shrink-0 rounded-lg border border-teal-300 bg-teal-50 px-3 text-[13px] font-semibold text-teal-700 disabled:opacity-40">افزودن</button>
          </div>
        </div>
        <!-- chips -->
        <div v-if="form.recipient_ids.length || form.recipient_department_ids.length" class="flex flex-wrap gap-1.5">
          <span v-for="id in form.recipient_ids" :key="'p'+id" class="inline-flex items-center gap-1 rounded-full bg-blue-50 py-1 pr-2.5 pl-1 text-[12px] text-brand-strong">
            {{ personName(id) }}<button @click="removePerson(id)" class="flex h-4 w-4 items-center justify-center rounded-full bg-white text-[11px] text-ink-500">×</button>
          </span>
          <span v-for="id in form.recipient_department_ids" :key="'d'+id" class="inline-flex items-center gap-1 rounded-full bg-teal-50 py-1 pr-2.5 pl-1 text-[12px] text-teal-700">
            ⛋ {{ deptName(id) }}<button @click="removeDept(id)" class="flex h-4 w-4 items-center justify-center rounded-full bg-white text-[11px] text-ink-500">×</button>
          </span>
        </div>

        <label class="flex flex-col gap-1">
          <span class="text-[12px] text-ink-600">یادداشت (اختیاری)</span>
          <textarea v-model="form.note" rows="2" class="af-inp" style="height:auto; padding:9px 11px" placeholder="مثلاً: لطفاً پیگیری و اقدام شود"></textarea>
        </label>

        <div v-if="error" class="rounded-lg bg-danger-50 px-3 py-2 text-[12px] text-danger-600">{{ error }}</div>
      </div>
      <div class="flex justify-end gap-2.5 border-t border-ink-200 px-5 py-4">
        <button @click="emit('close')" class="rounded-[10px] border border-ink-300 bg-white px-4 py-2 text-[13px] text-ink-800">انصراف</button>
        <button @click="submit" :disabled="saving" class="flex items-center gap-1.5 rounded-[10px] px-5 py-2 text-[13px] font-semibold text-white shadow-brand disabled:opacity-60" style="background: var(--grad-brand)">
          <Icon name="send" :size="15" /> {{ saving ? 'در حال ارسال…' : (isReply ? 'ارسال پاسخ' : 'ارجاع نامه') }}
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.af-inp { height: 40px; border: 1px solid var(--color-ink-200); border-radius: 10px; padding: 0 11px; font-size: 13.5px; background: #fff; color: var(--color-ink-800); }
</style>
