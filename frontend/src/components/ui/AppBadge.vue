<script setup>
// Status pill — mirrors the design-system Badge (soft variant).
const TONES = {
  neutral: ['#f1f4f9', '#38486a'],
  brand: ['#eaf3fe', '#2b55bd'],
  gold: ['#fdf8ea', '#b8860a'],
  teal: ['#e8f9fb', '#017a89'],
  success: ['#e6f6ee', '#12855a'],
  warning: ['#fdf4e0', '#c8860a'],
  danger: ['#fdeaec', '#c8384a'],
}
const props = defineProps({
  tone: { type: String, default: 'neutral' },
  dot: { type: Boolean, default: true },
})
</script>

<template>
  <span
    class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11.5px] font-semibold whitespace-nowrap leading-tight"
    :style="{ background: (TONES[tone] || TONES.neutral)[0], color: (TONES[tone] || TONES.neutral)[1] }"
  >
    <span v-if="dot" class="h-1.5 w-1.5 rounded-full opacity-90" :style="{ background: (TONES[tone] || TONES.neutral)[1] }" />
    <slot />
  </span>
</template>
