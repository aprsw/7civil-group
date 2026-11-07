<script setup>
import { ref, computed, onMounted } from 'vue'
import { api } from '../services/api'
import { useToastStore } from '../stores/toast'
import { useConfirmStore } from '../stores/confirm'
import { useBrandingStore } from '../stores/branding'
import AppCard from '../components/ui/AppCard.vue'

const toast = useToastStore()
const confirmStore = useConfirmStore()
const branding = useBrandingStore()

const data = ref({ letter_number_pattern: '', preview: '', tokens: [], logos: [] })
const pattern = ref('')
const identity = ref({ org_name: '', org_tagline: '', org_name_latin: '' })
const slots = ref({ org_logo_letterhead: '', org_logo_login: '', org_logo_sidebar: '' })
const saving = ref(false)
const uploading = ref(false)
const newLogoTitle = ref('')
const fileInput = ref(null)

// The three places a logo is shown, so the picker below is one loop.
const LOGO_SLOTS = [
  { key: 'org_logo_letterhead', label: 'سربرگ نامه‌ها', hint: 'روی نامه‌های نمایش‌داده‌شده و چاپ‌شده' },
  { key: 'org_logo_login', label: 'صفحهٔ ورود', hint: 'روی پس‌زمینهٔ تیره نمایش داده می‌شود' },
  { key: 'org_logo_sidebar', label: 'منوی کناری', hint: 'کنار نام سازمان در سایدبار' },
]

const logos = computed(() => data.value.logos || [])

function applyResponse(res) {
  data.value = res
  pattern.value = res.letter_number_pattern ?? ''
  identity.value = {
    org_name: res.org_name ?? '',
    org_tagline: res.org_tagline ?? '',
    org_name_latin: res.org_name_latin ?? '',
  }
  slots.value = {
    org_logo_letterhead: res.org_logo_letterhead ?? '',
    org_logo_login: res.org_logo_login ?? '',
    org_logo_sidebar: res.org_logo_sidebar ?? '',
  }
}

async function load() {
  applyResponse(await api.get('/settings'))
}

async function save() {
  saving.value = true
  try {
    applyResponse(await api.put('/settings', {
      letter_number_pattern: pattern.value,
      ...identity.value,
      // '' clears a slot so the bundled logo is used again.
      ...Object.fromEntries(Object.entries(slots.value).map(([k, v]) => [k, v === null ? '' : String(v)])),
    }))
    // Re-read the public branding so the header/letterhead update immediately.
    await branding.load(true)
    toast.show('تنظیمات ذخیره شد')
  } catch (e) {
    toast.show(e.message, 'danger')
  } finally {
    saving.value = false
  }
}

function pickFile() { fileInput.value?.click() }

async function uploadLogo(event) {
  const file = event.target.files?.[0]
  event.target.value = '' // allow re-picking the same file after an error
  if (!file) return
  uploading.value = true
  try {
    await api.upload('/branding/logos', file, { title: newLogoTitle.value })
    newLogoTitle.value = ''
    await load()
    toast.show('لوگو افزوده شد')
  } catch (e) {
    toast.show(e.message, 'danger')
  } finally {
    uploading.value = false
  }
}

async function renameLogo(logo) {
  const title = window.prompt('نام جدید لوگو:', logo.title)
  if (title === null || title.trim() === '' || title === logo.title) return
  try {
    await api.put(`/branding/logos/${logo.id}`, { title: title.trim() })
    await load()
  } catch (e) {
    toast.show(e.message, 'danger')
  }
}

async function deleteLogo(logo) {
  const ok = await confirmStore.ask({
    title: 'حذف لوگو',
    message: `«${logo.title}» از کتابخانه حذف شود؟ هر جایی که از آن استفاده شده به لوگوی پیش‌فرض برمی‌گردد.`,
    confirmText: 'حذف',
    tone: 'danger',
  })
  if (!ok) return
  try {
    await api.del(`/branding/logos/${logo.id}`)
    await load()
    await branding.load(true)
    toast.show('لوگو حذف شد')
  } catch (e) {
    toast.show(e.message, 'danger')
  }
}

function insert(tok) { pattern.value += tok }

onMounted(load)
</script>

<template>
  <div class="flex flex-col gap-[18px]" style="max-width: 900px">
    <div>
      <h1 class="m-0 text-3xl font-bold text-ink-900">تنظیمات سامانه</h1>
      <p class="mt-1.5 text-ink-600">هویت سازمان، لوگوها و شمارهٔ نامه‌ها</p>
    </div>

    <AppCard title="هویت سازمان" subtitle="این عنوان‌ها روی سربرگ نامه‌ها، صفحهٔ ورود و منوی کناری نمایش داده می‌شوند">
      <div class="flex flex-col gap-3.5">
        <label class="flex flex-col gap-1">
          <span class="text-[12px] text-ink-600">نام سازمان</span>
          <input v-model="identity.org_name" class="af-inp" placeholder="گروه متحد 7" />
        </label>
        <label class="flex flex-col gap-1">
          <span class="text-[12px] text-ink-600">شعار / زیرنویس <span class="text-ink-500">(خالی بگذارید تا نمایش داده نشود)</span></span>
          <input v-model="identity.org_tagline" class="af-inp" placeholder="شرکت‌های عمرانی و صنعتی عضو گروه متحد ۷" />
        </label>
        <label class="flex flex-col gap-1">
          <span class="text-[12px] text-ink-600">نام لاتین</span>
          <input v-model="identity.org_name_latin" dir="ltr" class="af-inp" style="text-align:left" placeholder="7CivilGroup" />
        </label>
      </div>
    </AppCard>

    <AppCard title="کتابخانهٔ لوگو" subtitle="لوگوی هر شرکت عضو گروه را اینجا نگه دارید و انتخاب کنید کدام‌یک کجا استفاده شود">
      <div class="flex flex-col gap-4">
        <!-- library -->
        <div v-if="logos.length" class="grid gap-3" style="grid-template-columns: repeat(auto-fill, minmax(190px, 1fr))">
          <div v-for="l in logos" :key="l.id" class="flex flex-col gap-2 rounded-xl border border-ink-200 p-3">
            <div class="flex h-[86px] items-center justify-center rounded-lg bg-ink-50 p-2">
              <img :src="l.url" :alt="l.title" class="max-h-full max-w-full object-contain" />
            </div>
            <div class="truncate text-[12.5px] font-semibold text-ink-800" :title="l.title">{{ l.title }}</div>
            <div class="flex gap-1.5">
              <button @click="renameLogo(l)" class="flex-1 rounded-lg border border-ink-200 bg-white px-2 py-1 text-[11.5px] text-ink-700">تغییر نام</button>
              <button @click="deleteLogo(l)" class="rounded-lg border border-red-200 bg-white px-2 py-1 text-[11.5px] text-red-600">حذف</button>
            </div>
          </div>
        </div>
        <p v-else class="rounded-xl bg-ink-50 px-4 py-3 text-[13px] text-ink-600">
          هنوز لوگویی ثبت نشده است. یک تصویر اضافه کنید تا بتوانید آن را روی سربرگ بنشانید.
        </p>

        <!-- upload -->
        <div class="flex flex-wrap items-end gap-2.5 rounded-xl bg-ink-50 p-3">
          <label class="flex min-w-[220px] flex-1 flex-col gap-1">
            <span class="text-[12px] text-ink-600">نام لوگوی جدید (اختیاری)</span>
            <input v-model="newLogoTitle" class="af-inp" placeholder="مثلاً: خاوران یول ایرانیان" />
          </label>
          <input ref="fileInput" type="file" accept="image/png,image/jpeg,image/webp,image/gif" class="hidden" @change="uploadLogo" />
          <button @click="pickFile" :disabled="uploading"
            class="h-[42px] rounded-[10px] px-5 text-[13px] font-semibold text-white shadow-brand disabled:opacity-60" style="background: var(--grad-brand)">
            {{ uploading ? 'در حال بارگذاری…' : '+ افزودن لوگو' }}
          </button>
          <span class="text-[11.5px] text-ink-500">PNG / JPG / WEBP / GIF — حداکثر ۴ مگابایت</span>
        </div>

        <!-- slot assignment -->
        <div class="grid gap-3" style="grid-template-columns: repeat(auto-fit, minmax(240px, 1fr))">
          <label v-for="s in LOGO_SLOTS" :key="s.key" class="flex flex-col gap-1">
            <span class="text-[12px] font-semibold text-ink-700">{{ s.label }}</span>
            <select v-model="slots[s.key]" class="af-inp">
              <option value="">— لوگوی پیش‌فرض سامانه —</option>
              <option v-for="l in logos" :key="l.id" :value="l.id">{{ l.title }}</option>
            </select>
            <span class="text-[11px] text-ink-500">{{ s.hint }}</span>
          </label>
        </div>
      </div>
    </AppCard>

    <AppCard title="الگوی شماره‌گذاری نامه" subtitle="شمارهٔ ثبت هر نامه بر اساس این الگو ساخته می‌شود">
      <div class="flex flex-col gap-3.5">
        <label class="flex flex-col gap-1">
          <span class="text-[12px] text-ink-600">الگو</span>
          <input v-model="pattern" dir="ltr" class="af-inp" style="text-align:left" placeholder="{yy}/{dept}/{seq}" />
        </label>

        <div class="flex flex-wrap gap-1.5">
          <button v-for="t in data.tokens" :key="t.token" @click="insert(t.token)"
            class="rounded-lg border border-ink-200 bg-ink-50 px-2.5 py-1.5 text-[12px] text-ink-700 hover:bg-blue-50" :title="t.desc">
            <b class="num" dir="ltr">{{ t.token }}</b> — {{ t.desc }}
          </button>
        </div>

        <div class="flex items-center gap-2 rounded-xl bg-blue-50 px-4 py-3 text-[13px] text-brand-strong">
          پیش‌نمایش: <b class="num">{{ data.preview }}</b>
        </div>
      </div>
    </AppCard>

    <div class="flex justify-end">
      <button @click="save" :disabled="saving"
        class="rounded-[10px] px-6 py-2.5 text-[13px] font-semibold text-white shadow-brand disabled:opacity-60" style="background: var(--grad-brand)">
        {{ saving ? 'در حال ذخیره…' : 'ذخیرهٔ تنظیمات' }}
      </button>
    </div>
  </div>
</template>

<style scoped>
.af-inp { height: 42px; border: 1px solid var(--color-ink-200); border-radius: 10px; padding: 0 12px; font-size: 14px; background: #fff; color: var(--color-ink-800); }
select.af-inp { padding-inline-end: 8px; }
</style>
