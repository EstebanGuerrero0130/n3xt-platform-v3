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

const props = withDefaults(defineProps<{ visible?: boolean }>(), { visible: true })

interface TickerItem { id: string; name: string; url: string }

const tickerItems = computed<TickerItem[]>(() => {
  const items: TickerItem[] = []
  for (let i = 0; i < 4; i++) {
    brands.forEach((brand, idx) => {
      items.push({
        id: `${i}-${idx}`,
        name: brand.name,
        url: brand.url
      })
    })
  }
  return items
})

const hideImg = (e: Event) => {
  (e.target as HTMLImageElement).style.display = 'none'
}
</script>

<template>
  <section class="w-full py-12 md:py-16 overflow-hidden border-y border-line" aria-labelledby="brand-ticker-title">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mb-8">
      <p id="brand-ticker-title" class="text-sm text-muted text-center">Trabajamos con equipos y plataformas de confianza</p>
    </div>

    <div class="relative">
      <!-- Bordes de fundido con el color de fondo de la página -->
      <div class="absolute inset-y-0 left-0 w-12 md:w-32 z-10 pointer-events-none bg-gradient-to-r from-canvas to-transparent" aria-hidden="true"></div>
      <div class="absolute inset-y-0 right-0 w-12 md:w-32 z-10 pointer-events-none bg-gradient-to-l from-canvas to-transparent" aria-hidden="true"></div>

      <div class="relative w-full overflow-hidden ticker-wrap">
        <ul class="ticker-track flex" :class="{ 'is-paused': !props.visible }">
          <li
            v-for="item in tickerItems"
            :key="item.id"
            class="ticker-item flex items-center justify-center shrink-0 h-8 md:h-10 mr-12 md:mr-16 text-muted"
          >
            <span class="relative h-full inline-block">
              <img
                :src="item.url"
                :alt="item.name"
                class="h-full w-auto opacity-0 pointer-events-none"
                loading="lazy"
                @error="hideImg"
              />
              <span
                class="absolute inset-0 w-full h-full"
                aria-hidden="true"
                :style="{
                  backgroundColor: 'currentColor',
                  WebkitMaskImage: `url('${item.url}')`,
                  maskImage: `url('${item.url}')`,
                  WebkitMaskSize: 'contain',
                  maskSize: 'contain',
                  WebkitMaskRepeat: 'no-repeat',
                  maskRepeat: 'no-repeat',
                  WebkitMaskPosition: 'center',
                  maskPosition: 'center'
                }"
              ></span>
            </span>
          </li>
        </ul>
      </div>
    </div>
  </section>
</template>

<style scoped>
@keyframes scroll {
  from { transform: translateX(0); }
  to { transform: translateX(-50%); }
}

.ticker-track {
  width: max-content;
  margin: 0;
  padding: 0;
  list-style: none;
  animation: scroll 80s linear infinite;
  will-change: transform;
}

.ticker-track.is-paused {
  animation-play-state: paused;
}

.ticker-item {
  transition: color 0.2s ease;
}

@media (hover: hover) {
  .ticker-wrap:hover .ticker-track {
    animation-play-state: paused;
  }

  .ticker-item:hover {
    color: var(--text-main);
  }
}

@media (prefers-reduced-motion: reduce) {
  .ticker-track {
    animation: none;
  }
}
</style>
