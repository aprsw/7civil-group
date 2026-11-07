<script setup>
const FILLS = {
  brand: 'var(--grad-brand-soft)',
  teal: 'var(--grad-teal)',
  gold: 'var(--grad-gold)',
  success: '#1aa06d',
}
const props = defineProps({
  value: { type: Number, default: 0 },
  tone: { type: String, default: 'brand' },
})
</script>

<template>
  <div
    class="h-[9px] flex-1 overflow-hidden rounded-full bg-ink-150"
    style="box-shadow: var(--shadow-inset)"
  >
    <div
      class="h-full rounded-full transition-[width] duration-500"
      :style="{ width: Math.max(0, Math.min(100, value)) + '%', background: FILLS[tone] || FILLS.brand }"
    />
  </div>
</template>
