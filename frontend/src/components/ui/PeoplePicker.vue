<script setup>
import { ref, computed, watch } from 'vue'
import AppAvatar from './AppAvatar.vue'

// A typeable search picker over a list of people. Filters by name/surname or
// role (سمت). Emits `pick` with the chosen person's id.
const props = defineProps({
  people: { type: Array, default: () => [] }, // [{id,name,role,department,photo_url?}]
  placeholder: { type: String, default: 'جستجوی نام، نام خانوادگی یا سمت…' },
  exclude: { type: Array, default: () => [] }, // ids already chosen
})
const emit = defineEmits(['pick'])

const q = ref('')
const open = ref(false)
const wrap = ref(null)
const activeIdx = ref(0)

const results = computed(() => {
  const needle = q.value.trim().toLowerCase()
  const ex = props.exclude.map(String)
  return props.people.filter((r) => {
    if (ex.includes(String(r.id))) return false
    if (!needle) return true
    return `${r.name} ${r.role || ''} ${r.department || ''}`.toLowerCase().includes(needle)
  }).slice(0, 40)
})
watch(results, () => { activeIdx.value = 0 })

function choose(r) {
  emit('pick', r.id)
  q.value = ''
  open.value = false
}
function onKey(e) {
  if (!open.value) return
  if (e.key === 'ArrowDown') { activeIdx.value = Math.min(activeIdx.value + 1, results.value.length - 1); e.preventDefault() }
  else if (e.key === 'ArrowUp') { activeIdx.value = Math.max(activeIdx.value - 1, 0); e.preventDefault() }
  else if (e.key === 'Enter') { if (results.value[activeIdx.value]) choose(results.value[activeIdx.value]); e.preventDefault() }
  else if (e.key === 'Escape') { open.value = false }
}
function onDocClick(e) { if (wrap.value && !wrap.value.contains(e.target)) open.value = false }
watch(open, (v) => {
  if (v) document.addEventListener('mousedown', onDocClick)
  else document.removeEventListener('mousedown', onDocClick)
})
</script>

<template>
  <div ref="wrap" class="relative">
    <input
      v-model="q"
      :placeholder="placeholder"
      @focus="open = true"
      @keydown="onKey"
      class="h-10 w-full rounded-[10px] border border-ink-200 bg-ink-50 px-3 text-[13px] text-ink-800 outline-none focus:border-blue-300"
    />
    <div v-if="open && results.length" class="af-scroll absolute z-40 mt-1 max-h-[260px] w-full overflow-y-auto rounded-xl border border-ink-200 bg-white shadow-pop">
      <button
        v-for="(r, idx) in results"
        :key="r.id"
        @click="choose(r)"
        @mouseenter="activeIdx = idx"
        class="flex w-full items-center gap-2.5 px-3 py-2 text-right"
        :class="idx === activeIdx ? 'bg-blue-50' : 'hover:bg-ink-50'"
      >
        <AppAvatar :name="r.name" :photo="r.photo_url" :size="30" />
        <span class="min-w-0 flex-1">
          <span class="block truncate text-[13px] font-semibold text-ink-900">{{ r.name }}</span>
          <span class="block truncate text-[11.5px] text-ink-500">{{ r.role }}<template v-if="r.department"> · {{ r.department }}</template></span>
        </span>
      </button>
    </div>
    <div v-else-if="open && q.trim()" class="absolute z-40 mt-1 w-full rounded-xl border border-ink-200 bg-white px-3 py-3 text-center text-[12.5px] text-ink-500 shadow-pop">
      موردی یافت نشد
    </div>
  </div>
</template>
