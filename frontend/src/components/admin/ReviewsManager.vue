<script setup lang="ts">
/** Moderación de reseñas: solo las aprobadas se ven en el sitio. */
import { ref, computed, onMounted, inject } from 'vue'
import { api } from '../../services/api'

interface AdminReview {
  id: number; order_id: number; job_name?: string; author_name: string; rating: number
  comment: string; photo_url: string | null; status: 'pending' | 'approved' | 'rejected'; created_at: string
}

const showNotify = inject<(msg: string, type?: string) => void>('showNotify')
const reviews = ref<AdminReview[]>([])
const loading = ref(true)
const filter = ref<'pending' | 'approved' | 'rejected' | 'all'>('pending')
const FILTERS = [
  { id: 'pending', label: 'Por revisar' }, { id: 'approved', label: 'Publicadas' },
  { id: 'rejected', label: 'No publicadas' }, { id: 'all', label: 'Todas' },
] as const

const load = async () => {
  loading.value = true
  try {
    const data = await api.get('/admin/reviews', true)
    reviews.value = Array.isArray(data) ? data : []
  } finally {
    loading.value = false
  }
}
onMounted(load)

const counts = computed(() => ({
  pending: reviews.value.filter(r => r.status === 'pending').length,
  approved: reviews.value.filter(r => r.status === 'approved').length,
  rejected: reviews.value.filter(r => r.status === 'rejected').length,
  all: reviews.value.length,
}))
const shown = computed(() => filter.value === 'all' ? reviews.value : reviews.value.filter(r => r.status === filter.value))

const setStatus = async (r: AdminReview, status: AdminReview['status']) => {
  try {
    await api.patch(`/admin/reviews/${r.id}`, { status })
    r.status = status
    showNotify?.(status === 'approved' ? 'Reseña publicada' : 'Reseña oculta del sitio', 'success')
  } catch (err) {
    showNotify?.('No se pudo actualizar: ' + (err instanceof Error ? err.message : ''), 'error')
  }
}
const remove = async (r: AdminReview) => {
  if (!confirm(`¿Eliminar la reseña de ${r.author_name}? No se puede deshacer.`)) return
  try {
    await api.delete(`/admin/reviews/${r.id}`)
    reviews.value = reviews.value.filter(x => x.id !== r.id)
  } catch (err) {
    showNotify?.('No se pudo eliminar: ' + (err instanceof Error ? err.message : ''), 'error')
  }
}
</script>

<template>
  <div class="space-y-4">
    <p class="text-muted">Los clientes califican sus pedidos terminados. Revisa cada reseña antes de publicarla en la Home y en los productos.</p>
    <div class="flex flex-wrap gap-2" role="group" aria-label="Estado de las reseñas">
      <button v-for="f in FILTERS" :key="f.id" type="button" class="chip !min-h-9 !px-3 text-sm" :aria-pressed="filter === f.id" @click="filter = f.id">
        {{ f.label }} <span class="tabular-nums opacity-80">{{ counts[f.id] }}</span>
      </button>
    </div>

    <div v-if="loading" class="admin-card h-32 animate-pulse" aria-hidden="true"></div>
    <p v-else-if="!shown.length" class="admin-card py-12 text-center text-muted">{{ filter === 'pending' ? 'No hay reseñas por revisar.' : 'No hay reseñas en este estado.' }}</p>

    <article v-for="r in shown" v-else :key="r.id" class="admin-card flex flex-col sm:flex-row gap-4">
      <img v-if="r.photo_url" :src="r.photo_url" alt="" class="w-full sm:w-32 h-32 rounded-[var(--radius-control)] object-cover shrink-0">
      <div class="min-w-0 flex-1">
        <div class="flex flex-wrap items-center gap-2">
          <span class="text-warning tracking-wider" :aria-label="`${r.rating} de 5 estrellas`">{{ '★'.repeat(r.rating) }}<span class="text-line">{{ '★'.repeat(5 - r.rating) }}</span></span>
          <span :class="['badge', r.status === 'approved' ? 'bg-success/10 text-success' : r.status === 'rejected' ? 'bg-surface-2 text-muted' : 'bg-warning/10 text-warning']">{{ r.status === 'approved' ? 'Publicada' : r.status === 'rejected' ? 'No publicada' : 'Por revisar' }}</span>
        </div>
        <p class="mt-2 leading-relaxed">“{{ r.comment }}”</p>
        <p class="mt-2 text-sm text-muted">{{ r.author_name }} · pedido #{{ r.order_id }}<template v-if="r.job_name"> ({{ r.job_name }})</template> · {{ new Date(r.created_at).toLocaleDateString('es-CO') }}</p>
      </div>
      <div class="flex sm:flex-col gap-2 shrink-0">
        <button v-if="r.status !== 'approved'" type="button" class="btn-primary btn-sm" @click="setStatus(r, 'approved')">Publicar</button>
        <button v-if="r.status !== 'rejected'" type="button" class="btn-outlined btn-sm" @click="setStatus(r, 'rejected')">No publicar</button>
        <button type="button" class="btn-sm px-3 rounded-full font-semibold text-danger hover:bg-danger/10" @click="remove(r)">Eliminar</button>
      </div>
    </article>
  </div>
</template>
