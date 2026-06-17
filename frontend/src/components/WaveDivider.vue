<script setup lang="ts">
defineProps({
 color: { type: String, default: '#10b981' },
 height: { type: String, default: '40px' }
})
</script>

<template>
 <div class="glow-divider relative w-full overflow-hidden" :style="{ height }">
 <!-- Glow aura behind the line -->
 <div
 class="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 w-3/4 h-full pointer-events-none"
 :style="{
 background: `radial-gradient(ellipse at center, ${color}22 0%, ${color}08 50%, transparent 80%)`
 }"
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
