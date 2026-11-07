<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '../services/api'
import { useToastStore } from '../stores/toast'
import { useConfirmStore } from '../stores/confirm'
import Icon from '../components/ui/Icon.vue'
import TemplateFormModal from '../components/modals/TemplateFormModal.vue'

const router = useRouter()
const toast = useToastStore()
const confirmStore = useConfirmStore()

const templates = ref([])
const canManage = ref(false)
const showForm = ref(false)
const editing = ref(null)
const saving = ref(false)
const q = ref('')
const activeCat = ref('')
const previewing = ref(null)

const categories = computed(() => [...new Set(templates.value.map((t) => t.category).filter(Boolean))])
const filtered = computed(() => {
  const needle = q.value.trim().toLowerCase()
  return templates.value.filter((t) =>
    (!activeCat.value || t.category === activeCat.value)
    && (!needle || (t.title + ' ' + (t.category || '')).toLowerCase().includes(needle)),
  )
})

async function load() {
  const data = await api.get('/templates')
  templates.value = data.items
  canManage.value = data.can_manage
}

function openCreate() { editing.value = null; showForm.value = true }
function openEdit(t) { editing.value = t; showForm.value = true }
function useTemplate(t) { router.push({ name: 'compose', query: { template: t.id } }) }
async function duplicate(t) {
  try { await api.post(`/templates/${t.id}/duplicate`, {}); toast.show('رونوشت قالب ساخته شد'); load() }
  catch (e) { toast.show(e.message, 'danger') }
}

async function save(payload, setErrors) {
  saving.value = true
  try {
    if (editing.value) await api.put(`/templates/${editing.value.id}`, payload)
    else await api.post('/templates', payload)
    toast.show('قالب ذخیره شد')
    showForm.value = false
    load()
  } catch (e) {
    if (e.errors && Object.keys(e.errors).length) setErrors(e.errors)
    else toast.show(e.message, 'danger')
  } finally {
    saving.value = false
  }
}

async function remove(t) {
  const ok = await confirmStore.ask({ title: 'حذف قالب', message: `قالب «${t.title}» حذف شود؟`, confirmText: 'حذف', tone: 'danger' })
  if (!ok) return
  try {
    await api.del(`/templates/${t.id}`)
    toast.show('قالب حذف شد')
    load()
  } catch (e) {
    toast.show(e.message, 'danger')
  }
}

onMounted(load)
</script>

<template>
  <div class="flex flex-col gap-[18px]" style="max-width: 1280px">
    <div class="flex items-center justify-between gap-4">
      <div>
        <h1 class="m-0 text-3xl font-bold text-ink-900">قالب‌ساز نامه</h1>
        <p class="mt-1.5 text-ink-600">قالب‌های آمادهٔ نامه برای استفادهٔ سریع در نگارش</p>
      </div>
      <button v-if="canManage" @click="openCreate"
        class="flex h-11 items-center gap-2 rounded-xl px-5 text-sm font-semibold text-white shadow-brand" style="background: var(--grad-brand)">
        <Icon name="plus" :size="18" /> قالب جدید
      </button>
    </div>

    <!-- search + category filter -->
    <div class="flex flex-wrap items-center gap-2 rounded-2xl border border-ink-200 bg-white p-2.5 px-3 shadow-card">
      <input v-model="q" placeholder="جستجوی قالب…" class="h-9 w-[220px] rounded-lg border border-ink-200 bg-ink-50 px-3 text-[13px]" />
      <span class="mx-1 h-5 w-px bg-ink-200"></span>
      <button @click="activeCat = ''" class="h-8 rounded-lg px-3 text-[12.5px] font-semibold" :class="!activeCat ? 'bg-brand-strong text-white' : 'text-ink-600 hover:bg-ink-100'">همه</button>
      <button v-for="c in categories" :key="c" @click="activeCat = c" class="h-8 rounded-lg px-3 text-[12.5px] font-semibold" :class="activeCat === c ? 'bg-brand-strong text-white' : 'text-ink-600 hover:bg-ink-100'">{{ c }}</button>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <div v-for="t in filtered" :key="t.id" class="flex flex-col overflow-hidden rounded-2xl border border-ink-200 bg-white shadow-card">
        <div class="h-1" style="background: var(--grad-brand)"></div>
        <div class="flex flex-1 flex-col gap-3 p-5">
          <div class="flex items-start justify-between gap-2">
            <div class="flex h-11 w-11 items-center justify-center rounded-xl text-white" style="background: var(--grad-teal)"><Icon name="file" :size="20" /></div>
            <div class="flex items-center gap-1.5">
              <span v-if="t.category" class="rounded-full bg-teal-50 px-2.5 py-1 text-[11px] font-semibold text-teal-700">{{ t.category }}</span>
              <span class="num rounded-full bg-ink-100 px-2.5 py-1 text-[11px] font-semibold text-ink-600">{{ t.paper_size.toUpperCase() }}</span>
            </div>
          </div>
          <div class="text-[15px] font-bold text-ink-900">{{ t.title }}</div>
          <div class="line-clamp-3 flex-1 text-[12.5px] leading-6 text-ink-500" v-html="t.body"></div>
          <div class="flex flex-wrap items-center gap-2 border-t border-ink-100 pt-3">
            <button @click="useTemplate(t)" class="flex-1 rounded-[10px] bg-blue-50 py-2 text-[12.5px] font-semibold text-brand-strong">استفاده</button>
            <button @click="previewing = t" title="پیش‌نمایش" class="h-9 w-9 rounded-[10px] border border-ink-200 bg-white text-ink-600 hover:bg-ink-100">👁</button>
            <template v-if="canManage">
              <button @click="duplicate(t)" title="رونوشت" class="h-9 w-9 rounded-[10px] border border-ink-200 bg-white text-ink-600 hover:bg-ink-100">⧉</button>
              <button @click="openEdit(t)" title="ویرایش" class="h-9 w-9 rounded-[10px] border border-ink-200 bg-white text-ink-600 hover:bg-ink-100">✎</button>
              <button @click="remove(t)" title="حذف" class="h-9 w-9 rounded-[10px] border border-danger-500 bg-white text-[15px] text-danger-500 hover:bg-danger-50">×</button>
            </template>
          </div>
        </div>
      </div>
      <div v-if="!filtered.length" class="col-span-3 rounded-2xl border border-dashed border-ink-300 bg-white p-10 text-center text-ink-500">
        قالبی یافت نشد.
      </div>
    </div>
  </div>

  <TemplateFormModal v-if="showForm" :template="editing" :saving="saving" @save="save" @close="showForm = false" />

  <!-- preview modal -->
  <div v-if="previewing" class="fixed inset-0 z-[80] flex items-center justify-center p-6" style="background: rgba(13,21,38,0.5); backdrop-filter: blur(4px)" @click.self="previewing = null">
    <div class="w-[min(680px,100%)] overflow-hidden rounded-2xl bg-white shadow-pop">
      <div class="flex items-center justify-between border-b border-ink-200 px-5 py-4">
        <b class="text-[15px] text-ink-900">پیش‌نمایش: {{ previewing.title }}</b>
        <button @click="previewing = null" class="text-[20px] text-ink-500">×</button>
      </div>
      <div class="af-scroll max-h-[65vh] overflow-y-auto p-6">
        <div class="af-tpl-preview mx-auto bg-white p-8 leading-8 text-[#111] shadow-card" style="max-width:190mm" v-html="previewing.body"></div>
      </div>
      <div class="flex justify-end gap-2.5 border-t border-ink-200 px-5 py-4">
        <button @click="previewing = null" class="rounded-[10px] border border-ink-300 bg-white px-4 py-2 text-[13px] text-ink-800">بستن</button>
        <button @click="useTemplate(previewing); previewing = null" class="rounded-[10px] px-5 py-2 text-[13px] font-semibold text-white shadow-brand" style="background: var(--grad-brand)">استفاده از این قالب</button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.af-tpl-preview :deep(table) { border-collapse: collapse; width: 100%; margin: 6px 0; }
.af-tpl-preview :deep(td), .af-tpl-preview :deep(th) { border: 1px solid #8a93a6; padding: 6px; }
</style>
