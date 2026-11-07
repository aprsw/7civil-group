<script setup>
import { ref, reactive, onMounted, nextTick } from 'vue'

const props = defineProps({
  template: { type: Object, default: null },
  saving: { type: Boolean, default: false },
})
const emit = defineEmits(['save', 'close'])

const isEdit = !!props.template
const body = ref(null)
const form = reactive({
  title: props.template?.title || '',
  category: props.template?.category || '',
  paper_size: props.template?.paper_size || 'a4',
})
const errors = reactive({})

// Placeholders substituted automatically when the template is used in compose.
const VARIABLES = [
  { k: '{{تاریخ}}', d: 'تاریخ امروز' },
  { k: '{{فرستنده}}', d: 'نام فرستنده' },
  { k: '{{سمت}}', d: 'سمت فرستنده' },
  { k: '{{واحد}}', d: 'واحد سازمانی' },
  { k: '{{گیرنده}}', d: 'نام گیرنده' },
  { k: '{{موضوع}}', d: 'موضوع نامه' },
]
const CATEGORIES = ['اداری', 'مالی', 'منابع انسانی', 'بازرگانی', 'فنی', 'عمومی']

function exec(cmd) { body.value?.focus(); document.execCommand(cmd, false, null) }
function insertVar(v) { body.value?.focus(); document.execCommand('insertText', false, v) }
function insertTable() {
  const spec = prompt('سطر × ستون:', '3x3'); if (!spec) return
  const [r, c] = spec.replace(/[۰-۹]/g, (d) => '۰۱۲۳۴۵۶۷۸۹'.indexOf(d)).split(/[x×*]/).map((n) => parseInt(n, 10))
  if (!r || !c) return
  let h = '<table><tbody>'
  for (let i = 0; i < r; i++) { h += '<tr>'; for (let j = 0; j < c; j++) h += '<td>&nbsp;</td>'; h += '</tr>' }
  h += '</tbody></table><p><br></p>'
  body.value?.focus(); document.execCommand('insertHTML', false, h)
}

function submit() {
  Object.keys(errors).forEach((k) => delete errors[k])
  emit('save', { title: form.title, category: form.category, paper_size: form.paper_size, body: body.value?.innerHTML || '' }, (e) => Object.assign(errors, e || {}))
}

onMounted(async () => {
  await nextTick()
  if (body.value) body.value.innerHTML = props.template?.body || '<p>با سلام و احترام؛</p><p>…</p>'
})
</script>

<template>
  <div class="fixed inset-0 z-[80] flex items-center justify-center p-6" style="background: rgba(13,21,38,0.48); backdrop-filter: blur(3px)" @click.self="emit('close')">
    <div class="w-[min(640px,100%)] overflow-hidden rounded-2xl bg-white shadow-pop" style="animation: afModalIn .22s ease">
      <div class="flex items-center justify-between border-b border-ink-200 px-5 py-4">
        <b class="text-[15px] text-ink-900">{{ isEdit ? 'ویرایش قالب' : 'قالب جدید' }}</b>
        <button @click="emit('close')" class="text-[20px] text-ink-500">×</button>
      </div>
      <div class="flex flex-col gap-3.5 p-5">
        <div class="grid grid-cols-4 gap-3.5">
          <label class="col-span-2 flex flex-col gap-1">
            <span class="text-[12px] text-ink-600">عنوان قالب</span>
            <input v-model="form.title" class="af-inp" />
            <span v-if="errors.title" class="text-[11px] text-danger-600">{{ errors.title }}</span>
          </label>
          <label class="flex flex-col gap-1">
            <span class="text-[12px] text-ink-600">دسته‌بندی</span>
            <input v-model="form.category" class="af-inp" list="tpl-cats" placeholder="اداری…" />
            <datalist id="tpl-cats"><option v-for="c in CATEGORIES" :key="c" :value="c" /></datalist>
          </label>
          <label class="flex flex-col gap-1">
            <span class="text-[12px] text-ink-600">اندازه</span>
            <select v-model="form.paper_size" class="af-inp"><option value="a4">A4</option><option value="a5">A5</option></select>
          </label>
        </div>
        <div class="flex flex-col gap-1">
          <span class="text-[12px] text-ink-600">متن قالب</span>
          <div class="flex flex-wrap items-center gap-0.5 rounded-t-xl border border-b-0 border-ink-200 bg-ink-50 px-2 py-1" @mousedown.prevent>
            <button class="af-tb" @click="exec('bold')"><b>B</b></button>
            <button class="af-tb" @click="exec('italic')"><i>I</i></button>
            <button class="af-tb" @click="exec('underline')"><u>U</u></button>
            <button class="af-tb" @click="exec('insertUnorderedList')">•≡</button>
            <button class="af-tb" @click="exec('insertOrderedList')">1≡</button>
            <button class="af-tb" @click="exec('justifyRight')">▤</button>
            <button class="af-tb" @click="exec('justifyCenter')">▥</button>
            <button class="af-tb" @click="exec('justifyFull')">▧</button>
            <button class="af-tb" title="درج جدول" @click="insertTable">▦</button>
            <button class="af-tb" title="پاک کردن قالب" @click="exec('removeFormat')">⌫</button>
          </div>
          <div ref="body" contenteditable="true" spellcheck="false"
            class="af-tpl-body min-h-[170px] rounded-b-xl border border-ink-200 bg-white p-3 text-[13.5px] leading-8 text-ink-800 outline-none"></div>
          <span v-if="errors.body" class="text-[11px] text-danger-600">{{ errors.body }}</span>
        </div>
        <!-- dynamic variables -->
        <div class="flex flex-col gap-1.5 rounded-xl border border-dashed border-ink-200 bg-ink-50 p-3">
          <span class="text-[11.5px] font-semibold text-ink-600">متغیرهای پویا (هنگام استفاده از قالب جایگزین می‌شوند):</span>
          <div class="flex flex-wrap gap-1.5">
            <button v-for="v in VARIABLES" :key="v.k" @click="insertVar(v.k)"
              class="rounded-lg border border-ink-200 bg-white px-2.5 py-1 text-[11.5px] text-brand-strong" :title="v.d">{{ v.k }}</button>
          </div>
        </div>
      </div>
      <div class="flex justify-end gap-2.5 border-t border-ink-200 px-5 py-4">
        <button @click="emit('close')" class="rounded-[10px] border border-ink-300 bg-white px-4 py-2 text-[13px] text-ink-800">انصراف</button>
        <button @click="submit" :disabled="saving" class="rounded-[10px] px-5 py-2 text-[13px] font-semibold text-white shadow-brand disabled:opacity-60" style="background: var(--grad-brand)">
          {{ saving ? 'در حال ذخیره…' : 'ذخیره قالب' }}
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.af-inp { height: 40px; border: 1px solid var(--color-ink-200); border-radius: 10px; padding: 0 11px; font-size: 13.5px; background: #fff; color: var(--color-ink-800); }
.af-tb { display: inline-flex; align-items: center; justify-content: center; min-width: 28px; height: 28px; border: 0; background: transparent; border-radius: 6px; cursor: pointer; color: var(--color-ink-700); font-size: 13px; }
.af-tb:hover { background: var(--color-ink-100); }
.af-tpl-body :deep(table) { border-collapse: collapse; width: 100%; margin: 6px 0; }
.af-tpl-body :deep(td), .af-tpl-body :deep(th) { border: 1px solid #8a93a6; padding: 5px; }
</style>
