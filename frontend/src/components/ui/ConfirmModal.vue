<script setup>
import { useConfirmStore } from '../../stores/confirm'
const confirm = useConfirmStore()
</script>

<template>
  <transition name="af-confirm">
    <div v-if="confirm.open" class="fixed inset-0 z-[120] flex items-center justify-center p-6"
      style="background: rgba(13,21,38,0.5); backdrop-filter: blur(4px)" @click.self="confirm.cancel()">
      <div class="w-[min(420px,100%)] overflow-hidden rounded-2xl bg-white shadow-pop" style="animation: afModalIn .2s ease">
        <!-- header with × at top-left -->
        <div class="relative flex items-center gap-3 border-b border-ink-100 px-5 py-4">
          <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-[18px]"
            :class="confirm.tone === 'danger' ? 'bg-danger-50 text-danger-500' : 'bg-blue-50 text-brand-strong'">
            {{ confirm.tone === 'danger' ? '⚠' : '?' }}
          </span>
          <b class="text-[15px] text-ink-900">{{ confirm.title }}</b>
          <button @click="confirm.cancel()" aria-label="بستن"
            class="absolute top-3 left-3 flex h-8 w-8 items-center justify-center rounded-lg text-[18px] text-ink-500 hover:bg-ink-100">×</button>
        </div>
        <div class="px-5 py-5 text-[13.5px] leading-7 text-ink-700">{{ confirm.message }}</div>
        <div class="flex justify-end gap-2.5 border-t border-ink-100 px-5 py-4">
          <button @click="confirm.cancel()" class="rounded-[10px] border border-ink-300 bg-white px-5 py-2 text-[13px] font-semibold text-ink-800 hover:bg-ink-50">
            {{ confirm.cancelText }}
          </button>
          <button @click="confirm.confirm()"
            class="rounded-[10px] px-5 py-2 text-[13px] font-semibold text-white shadow-brand"
            :style="confirm.tone === 'danger' ? 'background: linear-gradient(180deg,#f0596b,#e0384d)' : 'background: var(--grad-brand)'">
            {{ confirm.confirmText }}
          </button>
        </div>
      </div>
    </div>
  </transition>
</template>

<style scoped>
.af-confirm-enter-active, .af-confirm-leave-active { transition: opacity .18s ease; }
.af-confirm-enter-from, .af-confirm-leave-to { opacity: 0; }
</style>
