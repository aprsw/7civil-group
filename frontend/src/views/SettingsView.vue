<script setup>
import { ref, onMounted } from 'vue'
import { api } from '../services/api'
import { useToastStore } from '../stores/toast'
import AppCard from '../components/ui/AppCard.vue'

const toast = useToastStore()
const data = ref({ letter_number_pattern: '', preview: '', tokens: [] })
const pattern = ref('')
const saving = ref(false)

async function load() {
  data.value = await api.get('/settings')
  pattern.value = data.value.letter_number_pattern
}

async function save() {
  saving.value = true
  try {
    data.value = await api.put('/settings', { letter_number_pattern: pattern.value })
    pattern.value = data.value.letter_number_pattern
    toast.show('تنظیمات ذخیره شد')
  } catch (e) {
    toast.show(e.message, 'danger')
  } finally {
    saving.value = false
  }
}

function insert(tok) { pattern.value += tok }

onMounted(load)
</script>

<template>
  <div class="flex flex-col gap-[18px]" style="max-width: 820px">
    <div>
      <h1 class="m-0 text-3xl font-bold text-ink-900">تنظیمات سامانه</h1>
      <p class="mt-1.5 text-ink-600">پیکربندی شمارهٔ نامه‌ها و پارامترهای سازمانی</p>
    </div>

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

        <div class="flex justify-end">
          <button @click="save" :disabled="saving"
            class="rounded-[10px] px-6 py-2.5 text-[13px] font-semibold text-white shadow-brand disabled:opacity-60" style="background: var(--grad-brand)">
            {{ saving ? 'در حال ذخیره…' : 'ذخیرهٔ تنظیمات' }}
          </button>
        </div>
      </div>
    </AppCard>
  </div>
</template>

<style scoped>
.af-inp { height: 42px; border: 1px solid var(--color-ink-200); border-radius: 10px; padding: 0 12px; font-size: 14px; background: #fff; color: var(--color-ink-800); }
</style>
