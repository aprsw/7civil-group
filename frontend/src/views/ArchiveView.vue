<script setup>
import { ref, onMounted } from 'vue'
import { api } from '../services/api'
import AppCard from '../components/ui/AppCard.vue'
import AppBadge from '../components/ui/AppBadge.vue'

const letters = ref([])

onMounted(async () => {
  letters.value = await api.get('/letters/archive')
})
</script>

<template>
  <div class="flex flex-col gap-[18px]" style="max-width: 1280px">
    <div>
      <h1 class="m-0 text-3xl font-bold text-ink-900">بایگانی نامه‌ها</h1>
      <p class="mt-1.5 text-ink-600">نامه‌های تکمیل‌شده و بایگانی‌شده</p>
    </div>

    <AppCard title="اسناد بایگانی‌شده" :subtitle="`${letters.length} مورد`" :padding="false">
      <table class="w-full border-collapse">
        <thead>
          <tr>
            <th v-for="h in ['شماره', 'موضوع', 'فرستنده / گیرنده', 'وضعیت', 'تاریخ']" :key="h"
              class="bg-ink-50 px-4 py-2.5 text-start text-[11.5px] font-semibold text-ink-500"
              :class="h === 'تاریخ' ? 'text-end' : ''">{{ h }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="l in letters" :key="l.id" class="border-b border-ink-200 last:border-0 hover:bg-blue-50">
            <td class="num whitespace-nowrap px-4 py-3 text-sm text-brand">{{ l.number }}</td>
            <td class="px-4 py-3"><b class="text-ink-900">{{ l.subject }}</b></td>
            <td class="px-4 py-3 text-sm text-ink-800">{{ l.from }}</td>
            <td class="px-4 py-3"><AppBadge :tone="l.status.tone">{{ l.status.label }}</AppBadge></td>
            <td class="num whitespace-nowrap px-4 py-3 text-end text-sm text-ink-600">{{ l.date }}</td>
          </tr>
        </tbody>
      </table>
    </AppCard>
  </div>
</template>
