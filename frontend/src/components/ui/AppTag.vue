<script setup>
// Chip/label — mirrors the design-system Tag.
const TONES = {
  neutral: ['#f1f4f9', '#38486a', '#c8cfdd'],
  brand: ['#eaf3fe', '#2b55bd', '#9ecbf6'],
  teal: ['#e8f9fb', '#017a89', '#7bd7e2'],
  gold: ['#fdf8ea', '#b8860a', '#f7d477'],
}
const props = defineProps({ tone: { type: String, default: 'neutral' } })
</script>

<template>
  <span
    class="inline-flex items-center gap-1.5 rounded-[9px] border px-3 py-[5px] text-[13px] font-medium whitespace-nowrap"
    :style="{
      background: (TONES[tone] || TONES.neutral)[0],
      color: (TONES[tone] || TONES.neutral)[1],
      borderColor: (TONES[tone] || TONES.neutral)[2],
    }"
  >
    <slot />
  </span>
</template>
