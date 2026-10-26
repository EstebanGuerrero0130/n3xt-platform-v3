<script setup lang="ts">
/**
 * Reseñas aprobadas de clientes. Sin reseñas no se muestra nada.
 * Con `product` solo las de pedidos que incluyeron ese producto del catálogo.
 */
import { ref, onMounted, watch } from 'vue'
import { api } from '../services/api'

interface PublicReview { id: number; name: string; rating: number; comment: string; photo_url: string | null; date: string }

const props = defineProps<{ product?: string | number; compact?: boolean }>()
const emit = defineEmits<{ (e: 'summary', value: { count: number; average: number | null }): void }>()

const reviews = ref<PublicReview[]>([])
const average = ref<number | null>(null)

const load = async () => {
  try {
    const q = props.product != null ? `?product=${encodeURIComponent(String(props.product))}` : ''
    const data = await api.get('/reviews' + q)
    reviews.value = data?.reviews || []
    average.value = data?.average ?? null
  } catch {
    reviews.value = []
    average.value = null
  }
  emit('summary', { count: reviews.value.length, average: average.value })
}
onMounted(load)
watch(() => props.product, load)

const dateLabel = (d: string) => new Date(d + 'T12:00:00').toLocaleDateString('es-CO', { month: 'long', year: 'numeric' })
</script>

<template>
  <section v-if="reviews.length" :class="compact ? 'mt-16' : 'py-24 md:py-36'" aria-labelledby="reviews-title">
    <div :class="compact ? '' : 'mx-auto max-w-7xl px-4 sm:px-6 lg:px-8'">
      <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <h2 id="reviews-title" :class="compact ? 'text-2xl md:text-3xl font-extrabold tracking-tight' : 'section-title'">
          Lo que dicen <span :class="compact ? 'text-brand' : 'section-title-accent'">nuestros clientes</span>
        </h2>
        <p v-if="average" class="flex items-center gap-2 text-lg">
          <span class="text-warning tracking-wider" aria-hidden="true">{{ '★'.repeat(Math.round(average)) }}</span>
          <span class="font-bold tabular-nums">{{ average.toLocaleString('es-CO') }}</span>
          <span class="text-muted">de 5 · {{ reviews.length }} {{ reviews.length === 1 ? 'reseña' : 'reseñas' }}</span>
        </p>
      </div>

      <ul :class="['mt-10 grid gap-5', compact ? 'sm:grid-cols-2' : 'sm:grid-cols-2 lg:grid-cols-3']">
        <li v-for="r in (compact ? reviews.slice(0, 4) : reviews.slice(0, 6))" :key="r.id" class="flex flex-col bg-surface rounded-[var(--radius-card)] overflow-hidden">
          <img v-if="r.photo_url" :src="r.photo_url" :alt="`Pieza de ${r.name}`" class="aspect-[4/3] w-full object-cover" loading="lazy" decoding="async" @error="(e: any) => e.target.style.display = 'none'">
          <div class="p-6 flex flex-col flex-1">
            <p class="text-warning text-lg tracking-wider" :aria-label="`${r.rating} de 5 estrellas`">{{ '★'.repeat(r.rating) }}<span class="text-line">{{ '★'.repeat(5 - r.rating) }}</span></p>
            <blockquote class="mt-3 text-ink leading-relaxed flex-1">“{{ r.comment }}”</blockquote>
            <p class="mt-4 text-sm"><span class="font-semibold">{{ r.name }}</span> <span class="text-muted">· {{ dateLabel(r.date) }}</span></p>
          </div>
        </li>
      </ul>
    </div>
  </section>
</template>
