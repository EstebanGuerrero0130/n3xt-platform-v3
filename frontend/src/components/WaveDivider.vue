<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps({
  color: { type: String, default: '#10b981' },
  height: { type: String, default: '40px' },
  // Intensidad del aura (0-1). El default reproduce el aura original.
  opacity: { type: [String, Number], default: 0.045 },
  // Refleja el divisor verticalmente
  flip: { type: Boolean, default: false }
})

const auraBackground = computed(() => {
  const o = Math.min(1, Math.max(0, Number(props.opacity) || 0))
  const center = Math.min(100, o * 300)
  const mid = Math.min(100, o * 100)
  return `radial-gradient(ellipse at center, color-mix(in srgb, ${props.color} ${center}%, transparent) 0%, color-mix(in srgb, ${props.color} ${mid}%, transparent) 50%, transparent 80%)`
})
</script>

<template>
  <div class="glow-divider relative w-full overflow-hidden" :style="{ height, transform: flip ? 'scaleY(-1)' : undefined }">
    <!-- Glow aura behind the line -->
    <div
      class="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 w-3/4 h-full pointer-events-none"
      :style="{ background: auraBackground }"
    ></div>

    <!-- Thin gradient line -->
    <div
      class="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 w-full h-px pointer-events-none"
      :style="{
        background: `linear-gradient(to right, transparent 0%, ${color}44 15%, ${color}88 40%, ${color}99 50%, ${color}88 60%, ${color}44 85%, transparent 100%)`
      }"
    ></div>
  </div>
</template>

<style scoped>
.glow-divider {
  pointer-events: none;
  line-height: 0;
}
</style>
