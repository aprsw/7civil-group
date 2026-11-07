<script setup>
import { ref, computed, watch } from 'vue'
import {
  toJalaali, monthLength, weekday, todayJalaali, formatJalaali, parseJalaali,
  toFa, toEn, MONTH_NAMES, WEEK_DAYS,
} from '../../utils/jalali'

const props = defineProps({
  modelValue: { type: String, default: '' },
  placeholder: { type: String, default: '۱۴۰۵/۰۱/۰۱' },
})
const emit = defineEmits(['update:modelValue'])

const open = ref(false)
const wrap = ref(null)
const text = ref(props.modelValue || '')
watch(() => props.modelValue, (v) => { text.value = v || '' })

// view = the month currently shown in the popup
const t0 = todayJalaali()
const view = ref({ jy: t0.jy, jm: t0.jm })

function onType(e) {
  // Normalise typed Latin digits to Persian so the field always reads in فارسی.
  const val = toFa(toEn(e.target.value))
  text.value = val
  e.target.value = val
  emit('update:modelValue', val)
}

function openPicker() {
  const parsed = parseJalaali(text.value)
  if (parsed) view.value = { jy: parsed.jy, jm: parsed.jm }
  else { const t = todayJalaali(); view.value = { jy: t.jy, jm: t.jm } }
  open.value = true
}

const selected = computed(() => parseJalaali(text.value))

const grid = computed(() => {
  const { jy, jm } = view.value
  const len = monthLength(jy, jm)
  const lead = weekday(jy, jm, 1) // 0=شنبه
  const cells = []
  for (let i = 0; i < lead; i += 1) cells.push(null)
  for (let d = 1; d <= len; d += 1) cells.push(d)
  return cells
})

function prevMonth() {
  let { jy, jm } = view.value
  jm -= 1; if (jm < 1) { jm = 12; jy -= 1 }
  view.value = { jy, jm }
}
function nextMonth() {
  let { jy, jm } = view.value
  jm += 1; if (jm > 12) { jm = 1; jy += 1 }
  view.value = { jy, jm }
}
function pick(d) {
  if (!d) return
  const val = formatJalaali(view.value.jy, view.value.jm, d)
  text.value = val
  emit('update:modelValue', val)
  open.value = false
}
function pickToday() {
  const t = todayJalaali()
  view.value = { jy: t.jy, jm: t.jm }
  pick(t.jd)
}
function isSelected(d) {
  const s = selected.value
  return s && s.jy === view.value.jy && s.jm === view.value.jm && s.jd === d
}
function isToday(d) {
  return t0.jy === view.value.jy && t0.jm === view.value.jm && t0.jd === d
}

function onDocClick(e) { if (wrap.value && !wrap.value.contains(e.target)) open.value = false }
watch(open, (v) => {
  if (v) document.addEventListener('mousedown', onDocClick)
  else document.removeEventListener('mousedown', onDocClick)
})
</script>

<template>
  <div ref="wrap" class="relative">
    <div class="flex items-stretch">
      <input :value="text" @input="onType" @focus="openPicker" :placeholder="placeholder"
        dir="ltr" style="text-align: right" class="af-dp-inp num flex-1" />
      <button type="button" @click="open ? (open = false) : openPicker()"
        class="flex w-10 shrink-0 items-center justify-center rounded-l-[10px] border border-r-0 border-ink-200 bg-ink-50 text-ink-500 hover:bg-ink-100">📅</button>
    </div>

    <div v-if="open" class="absolute z-50 mt-1 w-[248px] overflow-hidden rounded-xl border border-ink-200 bg-white shadow-pop" style="right:0">
      <div class="flex items-center justify-between border-b border-ink-100 px-2 py-2">
        <button type="button" @click="nextMonth" class="h-7 w-7 rounded-lg text-ink-600 hover:bg-ink-100">‹</button>
        <b class="num text-[13px] text-ink-900">{{ MONTH_NAMES[view.jm - 1] }} {{ toFa(view.jy) }}</b>
        <button type="button" @click="prevMonth" class="h-7 w-7 rounded-lg text-ink-600 hover:bg-ink-100">›</button>
      </div>
      <div class="grid grid-cols-7 gap-0.5 px-2 pt-2 text-center text-[11px] text-ink-400">
        <span v-for="w in WEEK_DAYS" :key="w">{{ w }}</span>
      </div>
      <div class="grid grid-cols-7 gap-0.5 p-2">
        <template v-for="(d, i) in grid" :key="i">
          <span v-if="d === null"></span>
          <button v-else type="button" @click="pick(d)"
            class="num flex h-8 items-center justify-center rounded-lg text-[12.5px]"
            :class="isSelected(d) ? 'bg-[color:var(--color-brand)] font-bold text-white'
              : isToday(d) ? 'border border-blue-300 text-brand-strong' : 'text-ink-700 hover:bg-blue-50'">
            {{ toFa(d) }}
          </button>
        </template>
      </div>
      <div class="flex justify-between border-t border-ink-100 px-3 py-2">
        <button type="button" @click="pickToday" class="text-[12px] font-semibold text-brand-strong">امروز</button>
        <button type="button" @click="open = false" class="text-[12px] text-ink-500">بستن</button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.af-dp-inp { height: 40px; border: 1px solid var(--color-ink-200); border-radius: 10px; border-left: 0; border-top-left-radius: 0; border-bottom-left-radius: 0; padding: 0 11px; font-size: 13.5px; background: #fff; color: var(--color-ink-800); width: 100%; }
</style>
