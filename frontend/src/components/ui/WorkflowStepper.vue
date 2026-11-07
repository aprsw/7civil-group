<script setup>
// Horizontal approval-chain tracker — mirrors DS WorkflowStepper.
const props = defineProps({
  steps: { type: Array, default: () => [] },
  current: { type: Number, default: 0 },
})

function circleStyle(i) {
  if (i < props.current) return { background: 'var(--grad-teal)', color: '#fff', border: 'none' }
  if (i === props.current) return { background: 'var(--grad-brand-soft)', color: '#fff', border: 'none', boxShadow: 'var(--shadow-brand)' }
  return { background: '#eaeef4', color: '#7b87a5', border: '1.5px solid #c8cfdd' }
}
</script>

<template>
  <div class="flex w-full items-start">
    <template v-for="(s, i) in steps" :key="i">
      <div class="flex w-24 shrink-0 flex-col items-center gap-2">
        <span class="flex h-[34px] w-[34px] items-center justify-center rounded-full text-sm font-bold" :style="circleStyle(i)">
          <svg v-if="i < current" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
            <path d="M20 6 9 17l-5-5" />
          </svg>
          <span v-else class="num">{{ i + 1 }}</span>
        </span>
        <span
          class="text-center text-[11.5px] leading-tight"
          :class="i === current ? 'font-semibold text-ink-900' : 'text-ink-600'"
        >{{ typeof s === 'string' ? s : s.label }}</span>
      </div>
      <div
        v-if="i < steps.length - 1"
        class="mt-4 h-0.5 min-w-5 flex-1 rounded"
        :style="{ background: i < current ? '#33c1d2' : '#c8cfdd' }"
      />
    </template>
  </div>
</template>
