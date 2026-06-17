<script setup lang="ts">
import { computed } from 'vue'

const brands = [
 {
 name: 'Bambu Lab',
 url: 'https://upload.wikimedia.org/wikipedia/commons/thumb/f/f4/BambuLab_logo.svg/1280px-BambuLab_logo.svg.png'
 },
 {
 name: 'Creality',
 url: 'https://upload.wikimedia.org/wikipedia/commons/6/6d/CREALITY_LOGO.png'
 },
 {
 name: 'Flashforge',
 url: 'https://simpleicons.org/icons/flashforge.svg'
 },
 {
 name: 'Anycubic',
 url: 'https://cdn.jsdelivr.net/npm/simple-icons@v14/icons/anycubic.svg'
 },
 {
 name: 'Shopify',
 url: 'https://cdn.shopify.com/s/files/1/0296/9026/5648/files/20210607104540_b3ea9d7e-d0d2-4e24-b42b-e5fe43c837ed.png?v=1623035698'
 }
]

const tickerItems = computed(() => {
 const items = []
 for (let i = 0; i < 4; i++) {
 brands.forEach((brand, idx) => {
 items.push({
 id: `${i}-${idx}`,
 name: brand.name,
 url: brand.url,
 bgColor: '#ffffff'
 })
 })
 }
 return items
})

const darkBg = '#05080b'

const gradientLeft = computed(() => {
 return `linear-gradient(to right, ${darkBg} 0%, transparent 100%)`
})

const gradientRight = computed(() => {
 return `linear-gradient(to left, ${darkBg} 0%, transparent 100%)`
})
</script>

<template>
 <div
 class="w-full py-16 md:py-20 overflow-hidden relative"
 style="background-color: transparent;"
 >
 <!-- Subtle top/bottom borders -->
 <div class="absolute top-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-emerald-500/20 to-transparent"></div>
 <div class="absolute bottom-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-emerald-500/20 to-transparent"></div>

 <!-- Section Label -->
 <div class="max-w-7xl mx-auto px-6 mb-10 md:mb-12 text-center relative">
 <p class="text-xs md:text-sm uppercase tracking-[0.4em] font-black text-slate-300 dark:text-white/50">
 TRABAJAMOS CON <span class="text-emerald-500">LAS MEJORES MARCAS</span>
 </p>
 </div>

 <div class="relative">
 <!-- Gradient Edges Overlay -->
 <div
 class="absolute inset-y-0 left-0 w-16 md:w-32 z-10 pointer-events-none"
 :style="{ background: gradientLeft }"
 ></div>
 <div
 class="absolute inset-y-0 right-0 w-16 md:w-32 z-10 pointer-events-none"
 :style="{ background: gradientRight }"
 ></div>

 <!-- Ticker Track -->
 <div class="relative w-full overflow-hidden ticker-wrap">
 <div class="ticker-track flex">
 <div
 v-for="item in tickerItems"
 :key="item.id"
 class="ticker-item flex items-center justify-center shrink-0 px-4 md:px-6"
 style="margin-right: 24px; height: 48px; width: auto;"
 >
 <div class="relative h-full inline-block" style="width: auto;">
 <img
 :src="item.url"
 :alt="item.name"
 class="h-full w-auto opacity-0 pointer-events-none"
 loading="lazy"
 @error="(e) => e.target.style.display='none'"
 />
 <div
 class="absolute inset-0 w-full h-full"
 :style="{
 backgroundColor: item.bgColor,
 WebkitMaskImage: `url('${item.url}')`,
 maskImage: `url('${item.url}')`,
 WebkitMaskSize: 'contain',
 maskSize: 'contain',
 WebkitMaskRepeat: 'no-repeat',
 maskRepeat: 'no-repeat',
 WebkitMaskPosition: 'center',
 maskPosition: 'center'
 }"
 ></div>
 </div>
 </div>
 </div>
 </div>
 </div>
 </div>
</template>

<style scoped>
@keyframes scroll {
 0% {
 transform: translateX(0);
 }
 100% {
 transform: translateX(-50%);
 }
}

.ticker-track {
 display: flex;
 width: max-content;
 animation: scroll 60s linear infinite;
 will-change: transform;
}

@media (hover: hover) {
 .ticker-wrap:hover .ticker-track {
 animation-play-state: paused;
 }

 .ticker-item {
 transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
 transform-origin: center;
 filter: grayscale(0.6);
 opacity: 0.7;
 }

 .ticker-wrap:hover .ticker-item {
 filter: grayscale(0);
 opacity: 1;
 }

 .ticker-wrap .ticker-item:hover {
 transform: scale(1.15);
 opacity: 1;
 filter: grayscale(0) drop-(0 0 12px rgba(16, 185, 129, 0.4));
 }
}
</style>
