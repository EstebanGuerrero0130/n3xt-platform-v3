<script setup lang="ts">
import { computed, type PropType } from 'vue'

const props = defineProps({
  order: { type: Object as PropType<any>, required: true }
})

const emit = defineEmits(['assign', 'download', 'view', 'download-pdf', 'update-status', 'toggle-paid'])

const statusOrder = ['pending', 'printing', 'post-processing', 'completed', 'shipped']

const canMoveLeft = computed(() => {
  const idx = statusOrder.indexOf(props.order.status)
  return idx > 0
})

const canMoveRight = computed(() => {
  const idx = statusOrder.indexOf(props.order.status)
  return idx >= 0 && idx < statusOrder.length - 1
})

const moveLeft = () => {
  const idx = statusOrder.indexOf(props.order.status)
  if (idx > 0) {
    emit('update-status', { orderId: props.order.id, status: statusOrder[idx - 1] })
  }
}

const moveRight = () => {
  const idx = statusOrder.indexOf(props.order.status)
  if (idx < statusOrder.length - 1) {
    const nextStatus = statusOrder[idx + 1]
    if (nextStatus === 'printing') {
      emit('assign', props.order)
    } else {
      emit('update-status', { orderId: props.order.id, status: nextStatus })
    }
  }
}

const isCatalog = computed(() => props.order.technology === 'CATALOGO')
const hasFile = computed(() => !!props.order.file_path || (Array.isArray(props.order.models) && props.order.models.length > 0))
const money = (n: any) => '$' + Math.round(Number(n) || 0).toLocaleString('es-CO')

// Cancelar avisa al cliente por correo: siempre se confirma
const cancelOrder = () => {
  if (!confirm(`¿Cancelar el pedido #${props.order.id} de ${props.order.customer_name || 'este cliente'}? Se le avisará por correo.`)) return
  emit('update-status', { orderId: props.order.id, status: 'cancelled' })
}

const isStalled = computed(() => {
  if (['shipped', 'cancelled', 'completed'].includes(props.order.status)) return false;
  
  const dateStr = props.order.updated_at || props.order.created_at;
  if (!dateStr) return false;

  // Lógica de parsing ultra-robusta
  let lastUpdate;
  if (dateStr.includes('T')) {
    lastUpdate = new Date(dateStr).getTime();
  } else {
    lastUpdate = new Date(dateStr.replace(' ', 'T')).getTime();
  }
  
  if (isNaN(lastUpdate)) return false;

  const now = Date.now();
  const diffInMs = now - lastUpdate;
  
  // Solo si la diferencia es positiva y mayor a 24 horas (86,400,000 ms)
  return diffInMs > (24 * 60 * 60 * 1000);
});
</script>

<template>
  <article
    :class="[
      'group bg-surface rounded-[var(--radius-card)] p-4 cursor-grab active:cursor-grabbing border transition-colors',
      isStalled ? 'border-warning/60' : 'border-transparent hover:border-line'
    ]"
    :aria-label="`Pedido #${order.id} de ${order.customer_name || 'cliente'}`"
  >
    <!-- Etiquetas -->
    <div class="flex flex-wrap items-center gap-1.5 mb-3">
      <span class="text-sm font-semibold tabular-nums text-muted">#{{ order.id }}</span>
      <span class="badge bg-surface-2 text-ink">{{ isCatalog ? 'Catálogo' : order.technology }}</span>
      <span v-if="order.is_paid" class="badge bg-success/10 text-success">Pagado</span>
      <span v-if="isStalled" class="badge bg-warning/10 text-warning" title="Más de 24 h sin cambios">Detenido</span>
    </div>

    <!-- Cliente y trabajo -->
    <h3 class="font-semibold text-ink leading-snug line-clamp-1">{{ order.customer_name || 'Sin nombre' }}</h3>
    <p v-if="order.job_name" class="text-sm text-muted line-clamp-1">{{ order.job_name }}</p>

    <p class="mt-3 text-xl font-extrabold tabular-nums">{{ money(order.total_price) }}</p>
    <p class="mt-1 text-sm text-muted">
      {{ order.material_name || order.material_id }}<template v-if="Number(order.estimated_weight_g) > 0"> · {{ Math.round(order.estimated_weight_g) }} g</template><template v-if="order.qty > 1"> · {{ order.qty }} u.</template>
    </p>

    <p v-if="order.status === 'printing' && order.printer" class="mt-3 text-sm"><span class="text-muted">Máquina:</span> <span class="font-semibold">{{ order.printer.name }}</span></p>
    <p v-if="order.status === 'shipped' && order.tracking_guide" class="mt-3 text-sm"><span class="text-muted">{{ order.tracking_carrier || 'Guía' }}:</span> <span class="font-semibold tabular-nums">{{ order.tracking_guide }}</span></p>

    <!-- Siguiente paso -->
    <div class="mt-4">
      <button v-if="order.status === 'pending'" type="button" class="btn-primary btn-sm w-full" @click.stop="$emit('assign', order)">Asignar a impresora</button>
      <div v-else-if="order.status === 'printing'" class="grid grid-cols-2 gap-2">
        <button type="button" class="btn-outlined btn-sm" @click.stop="$emit('assign', order)">Cambiar máquina</button>
        <button type="button" class="btn-primary btn-sm" @click.stop="$emit('update-status', { orderId: order.id, status: 'post-processing' })">Pasar a acabado</button>
      </div>
      <button v-else-if="order.status === 'post-processing'" type="button" class="btn-primary btn-sm w-full" @click.stop="$emit('update-status', { orderId: order.id, status: 'completed' })">Marcar listo</button>
      <button v-else-if="order.status === 'completed'" type="button" class="btn-primary btn-sm w-full" @click.stop="$emit('update-status', { orderId: order.id, status: 'shipped' })">Registrar envío</button>
    </div>

    <!-- Acciones -->
    <div class="mt-3 pt-3 border-t border-line flex items-center gap-1">
      <button type="button" class="icon-btn text-muted hover:text-ink hover:bg-surface-2" title="Ver detalle" aria-label="Ver detalle" @click.stop="$emit('view', order)">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
      </button>
      <button type="button" class="icon-btn text-muted hover:text-ink hover:bg-surface-2" title="Cotización en PDF" aria-label="Cotización en PDF" @click.stop="$emit('download-pdf', order)">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
      </button>
      <button v-if="hasFile" type="button" class="icon-btn text-muted hover:text-ink hover:bg-surface-2" title="Descargar archivo 3D" aria-label="Descargar archivo 3D" @click.stop="$emit('download', order)">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
      </button>
      <button v-if="!order.is_paid" type="button" class="icon-btn text-muted hover:text-success hover:bg-success/10" title="Confirmar pago" aria-label="Confirmar pago" @click.stop="$emit('toggle-paid', order.id)">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
      </button>

      <span class="flex-1"></span>
      <button v-if="canMoveLeft" type="button" class="icon-btn text-muted hover:text-ink hover:bg-surface-2" title="Etapa anterior" aria-label="Mover a la etapa anterior" @click.stop="moveLeft">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
      </button>
      <button v-if="canMoveRight" type="button" class="icon-btn text-muted hover:text-ink hover:bg-surface-2" title="Etapa siguiente" aria-label="Mover a la etapa siguiente" @click.stop="moveRight">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
      </button>
      <button v-if="order.status !== 'cancelled' && order.status !== 'shipped'" type="button" class="icon-btn text-muted hover:text-danger hover:bg-danger/10" title="Cancelar pedido" aria-label="Cancelar pedido" @click.stop="cancelOrder">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </div>
  </article>
</template>
