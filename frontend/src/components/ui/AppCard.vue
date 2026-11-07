<script setup>
// Surface container with optional title/subtitle header and accent bar
// (mirrors how the design composes DS Card + header).
const props = defineProps({
  title: { type: String, default: '' },
  subtitle: { type: String, default: '' },
  accent: { type: String, default: '' },
  padding: { type: Boolean, default: true },
})
const ACCENTS = { brand: 'var(--color-brand)', teal: '#00aec3', gold: '#ebb011', danger: '#e34d5f' }
</script>

<template>
  <div class="overflow-hidden rounded-2xl border border-ink-200 bg-white shadow-card">
    <div v-if="accent" class="h-1" :style="{ background: ACCENTS[accent] || ACCENTS.brand }" />
    <div v-if="title" class="flex items-center justify-between gap-3 border-b border-ink-200 px-5 py-4">
      <div>
        <div class="text-[15px] font-bold text-ink-900">{{ title }}</div>
        <div v-if="subtitle" class="mt-0.5 text-[12.5px] text-ink-500">{{ subtitle }}</div>
      </div>
      <slot name="actions" />
    </div>
    <div :class="padding ? 'p-5' : ''">
      <slot />
    </div>
  </div>
</template>
