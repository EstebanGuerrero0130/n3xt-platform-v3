<script setup lang="ts">
import { computed, ref, onMounted, onUnmounted } from 'vue'

// Logos locales a 80 px de alto (antes se cargaba un PNG remoto de 9785 px: ~128 MB decodificado por copia)
const brands = [
  { name: 'Bambu Lab', url: '/assets/brands/equipos/bambu-lab.webp' },
  { name: 'Creality', url: '/assets/brands/equipos/creality.webp' },
  { name: 'Flashforge', url: '/assets/brands/equipos/flashforge.svg' },
  { name: 'Anycubic', url: '/assets/brands/equipos/anycubic.svg' },
  { name: 'Elegoo', url: '/assets/brands/equipos/elegoo.webp' },
]

const props = withDefaults(defineProps<{ visible?: boolean }>(), { visible: true })

interface TickerItem { id: string; name: string; url: string; copy: boolean }

const tickerItems = computed<TickerItem[]>(() => {
  const items: TickerItem[] = []
  for (let i = 0; i < 4; i++) {
    brands.forEach((brand, idx) => {
      items.push({
        id: `${i}-${idx}`,
        name: brand.name,
        url: brand.url,
        // Solo la primera vuelta se anuncia; las demás existen para el bucle visual
        copy: i > 0
      })
    })
  }
  return items
})

// La animación solo corre mientras el carrusel está en pantalla
const root = ref<HTMLElement | null>(null)
const onScreen = ref(true)
let io: IntersectionObserver | null = null
onMounted(() => {
  if (!('IntersectionObserver' in window) || !root.value) return
  io = new IntersectionObserver(([e]) => { onScreen.value = e.isIntersecting }, { rootMargin: '100px' })
  io.observe(root.value)
})
onUnmounted(() => io?.disconnect())

const hideImg = (e: Event) => {
  (e.target as HTMLImageElement).style.display = 'none'
}
</script>

<template>
  <section ref="root" class="w-full overflow-hidden" aria-labelledby="brand-ticker-title">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mb-12 md:mb-16">
      <h2 id="brand-ticker-title" class="section-title">Equipos y plataformas <span class="section-title-accent">de confianza</span></h2>
    </div>

    <div class="relative">
      <!-- Bordes de fundido con el color de fondo de la página -->
      <div class="absolute inset-y-0 left-0 w-12 md:w-32 z-10 pointer-events-none bg-gradient-to-r from-canvas to-transparent" aria-hidden="true"></div>
      <div class="absolute inset-y-0 right-0 w-12 md:w-32 z-10 pointer-events-none bg-gradient-to-l from-canvas to-transparent" aria-hidden="true"></div>

      <div class="relative w-full overflow-hidden ticker-wrap">
        <ul class="ticker-track flex" :class="{ 'is-paused': !props.visible || !onScreen }">
          <li
            v-for="item in tickerItems"
            :key="item.id"
            class="ticker-item flex items-center justify-center shrink-0 h-10 md:h-14 mr-14 md:mr-24 text-muted"
            :aria-hidden="item.copy ? 'true' : undefined"
          >
            <span class="relative h-full inline-block">
              <img
                :src="item.url"
                :alt="item.copy ? '' : item.name"
                width="160" height="56"
                decoding="async"
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
