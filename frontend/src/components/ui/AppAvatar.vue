<script setup>
import { computed, ref, watch } from 'vue'

// Initials avatar with a deterministic brand-tinted fill (mirrors DS Avatar).
const PALETTES = [
  ['#cfe3fb', '#2b55bd'],
  ['#cdf0f5', '#017a89'],
  ['#fdf1cf', '#b8860a'],
  ['#e0e5ee', '#38486a'],
]
const props = defineProps({
  name: { type: String, default: '' },
  size: { type: Number, default: 40 },
  photo: { type: String, default: null },
})

// If the photo URL fails to load (e.g. the file was deleted on the server) we
// fall back to the initials chip instead of the browser showing the oversized
// `alt` text in place of the broken image.
const failed = ref(false)
watch(() => props.photo, () => { failed.value = false })
const showPhoto = computed(() => props.photo && !failed.value)

const initials = computed(() =>
  props.name.trim().split(/\s+/).slice(0, 2).map((w) => w[0]).join(''),
)
const palette = computed(() => {
  let h = 0
  for (const c of props.name) h = (h + c.charCodeAt(0)) % PALETTES.length
  return PALETTES[h]
})
</script>

<template>
  <img
    v-if="showPhoto"
    :src="photo"
    alt=""
    class="inline-block shrink-0 rounded-full object-cover"
    :style="{ width: size + 'px', height: size + 'px' }"
    @error="failed = true"
  />
  <span
    v-else
    class="inline-flex shrink-0 items-center justify-center rounded-full font-semibold leading-none"
    :title="name"
    :style="{
      width: size + 'px',
      height: size + 'px',
      fontSize: Math.max(9, size * 0.36) + 'px',
      background: palette[0],
      color: palette[1],
    }"
  >
    {{ initials }}
  </span>
</template>
