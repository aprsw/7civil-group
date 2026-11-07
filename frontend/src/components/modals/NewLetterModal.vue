<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '../../services/api'

const emit = defineEmits(['close'])
const router = useRouter()
const templates = ref([])

onMounted(async () => {
  try {
    const data = await api.get('/templates')
    templates.value = data.items
  } catch {
    templates.value = []
  }
})

function start(query) {
  emit('close')
  router.push({ name: 'compose', query })
}
</script>

<template>
  <div
    class="fixed inset-0 z-[80] flex items-center justify-center p-6"
    style="background: rgba(13,21,38,0.48); backdrop-filter: blur(3px); animation: afFade .15s ease"
    @click.self="emit('close')"
  >
    <div class="w-[min(760px,100%)] overflow-hidden rounded-[22px] bg-white shadow-pop" style="animation: afModalIn .22s ease">
      <div class="flex items-center justify-between border-b border-ink-200 px-6 py-4">
        <div>
          <b class="text-base text-ink-900">نامهٔ جدید</b>
          <div class="mt-0.5 text-[12.5px] text-ink-500">از یک قالب آماده شروع کنید یا نامهٔ خالی بسازید</div>
        </div>
        <button @click="emit('close')" class="cursor-pointer text-[22px] text-ink-500">×</button>
      </div>

      <div class="grid grid-cols-1 gap-4 p-6 sm:grid-cols-3">
        <button
          @click="start({})"
          class="flex flex-col items-center gap-3 rounded-2xl border-2 border-dashed border-blue-300 bg-blue-50 p-6 text-center transition hover:-translate-y-0.5 hover:shadow-md"
        >
          <div class="flex h-14 w-14 items-center justify-center rounded-2xl text-3xl text-white" style="background: var(--grad-brand)">＋</div>
          <div class="text-[13px] font-semibold text-brand-strong">نامهٔ خالی</div>
        </button>

        <button
          v-for="t in templates"
          :key="t.id"
          @click="start({ template: t.id })"
          class="flex flex-col items-center gap-3 rounded-2xl border border-ink-200 bg-white p-6 text-center transition hover:-translate-y-0.5 hover:border-blue-300 hover:shadow-md"
        >
          <div class="flex h-14 w-14 items-center justify-center rounded-2xl text-2xl text-white" style="background: var(--grad-teal)">📄</div>
          <div class="text-[13px] font-semibold text-ink-800">{{ t.title }}</div>
          <span class="num rounded-full bg-ink-100 px-2 py-0.5 text-[11px] font-semibold text-ink-600">{{ t.paper_size.toUpperCase() }}</span>
        </button>
      </div>
    </div>
  </div>
</template>
