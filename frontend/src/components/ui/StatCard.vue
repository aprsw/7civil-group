<script setup>
import Icon from './Icon.vue'

// KPI tile — mirrors DS StatCard (gradient icon chip + big number + delta).
const GRADS = {
  brand: 'var(--grad-brand-soft)',
  teal: 'var(--grad-teal)',
  gold: 'var(--grad-gold)',
  ink: 'linear-gradient(135deg,#38486a,#16233c)',
}
const props = defineProps({
  tone: { type: String, default: 'brand' },
  icon: { type: String, default: 'inbox' },
  value: { type: String, default: '' },
  label: { type: String, default: '' },
  delta: { type: String, default: '' },
  trend: { type: String, default: 'up' },
})
</script>

<template>
  <div class="flex min-w-0 flex-col gap-3.5 rounded-2xl border border-ink-200 bg-white p-5 shadow-card">
    <div class="flex items-center justify-between gap-2.5">
      <span class="text-[13px] font-medium text-ink-600">{{ label }}</span>
      <span
        class="flex h-[38px] w-[38px] shrink-0 items-center justify-center rounded-xl text-white"
        :style="{ background: GRADS[tone] || GRADS.brand, color: tone === 'gold' ? '#16233c' : '#fff' }"
      >
        <Icon :name="icon" :size="22" />
      </span>
    </div>
    <div class="flex items-baseline gap-2.5">
      <span class="num text-3xl font-extrabold leading-none text-ink-900">{{ value }}</span>
      <span
        v-if="delta"
        class="inline-flex items-center gap-0.5 text-[13px] font-semibold"
        :style="{ color: trend === 'up' ? '#12855a' : trend === 'down' ? '#c8384a' : '#7b87a5' }"
      >
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"
          stroke-linecap="round" stroke-linejoin="round"
          :style="{ transform: trend === 'up' ? 'none' : trend === 'down' ? 'rotate(180deg)' : 'rotate(90deg)' }">
          <path d="M12 19V5M5 12l7-7 7 7" />
        </svg>
        {{ delta }}
      </span>
    </div>
  </div>
</template>
