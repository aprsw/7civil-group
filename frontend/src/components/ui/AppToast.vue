<script setup>
import { useToastStore } from '../../stores/toast'
const toast = useToastStore()
</script>

<template>
  <div class="pointer-events-none fixed bottom-6 left-1/2 z-[90] flex -translate-x-1/2 flex-col items-center gap-2">
    <div
      v-for="t in toast.items"
      :key="t.id"
      class="pointer-events-auto flex items-center gap-2.5 rounded-xl px-5 py-3 text-[13.5px] text-white shadow-pop"
      :style="{ background: t.tone === 'danger' ? '#c8384a' : '#16233c', animation: 'afToastIn .3s ease' }"
    >
      <span>{{ t.tone === 'danger' ? '⚠' : '✔' }}</span>
      <span>{{ t.message }}</span>
    </div>
  </div>
</template>
