<template>
  <AdminModal :show="!!order" :title="order ? `Pedido #${order.id}` : ''" :description="order ? `${order.job_name || (isCatalog ? 'Producto de catálogo' : 'Impresión 3D')} · ${new Date(order.created_at).toLocaleDateString('es-CO', { day: 'numeric', month: 'long', year: 'numeric' })}` : ''" size="xl" @close="$emit('close')">
    <template v-if="order">
      <div class="flex flex-wrap items-center gap-2 mb-6">
        <span :class="['badge', statusClass]">{{ statusLabel }}</span>
        <span v-if="order.is_urgent" class="badge bg-danger/10 text-danger" title="Pagó el recargo urgente: va primero en la cola">Urgente</span>
        <span :class="['badge', order.is_paid ? 'bg-success/10 text-success' : 'bg-warning/10 text-warning']">{{ order.is_paid ? 'Pagado' : 'Pago pendiente' }}</span>
        <span v-if="order.printer" class="badge bg-surface-2 text-ink">Máquina: {{ order.printer.name }}</span>
      </div>

      <!-- Avance -->
      <ol v-if="order.status !== 'cancelled'" class="grid grid-cols-5 gap-2 mb-8" aria-label="Avance del pedido">
        <li v-for="(step, idx) in statusSteps" :key="step.id" :aria-current="getStatusIndex(order.status) === idx ? 'step' : undefined">
          <div :class="['h-1.5 rounded-full', getStatusIndex(order.status) >= idx ? 'bg-primary' : 'bg-surface-2']"></div>
          <p :class="['mt-2 text-sm', getStatusIndex(order.status) >= idx ? 'text-ink font-semibold' : 'text-muted']">{{ step.label }}</p>
        </li>
      </ol>

      <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
        <div class="lg:col-span-7 space-y-6">
          <!-- Piezas -->
          <section aria-labelledby="od-pieces">
            <h3 id="od-pieces" class="admin-h2 text-base">{{ isCatalog ? 'Producto' : 'Piezas' }}</h3>
            <ul v-if="pieces.length" class="mt-3 divide-y divide-line rounded-[var(--radius-control)] bg-surface">
              <li v-for="(p, i) in pieces" :key="i" class="px-4 py-3 flex flex-wrap items-center justify-between gap-x-4 gap-y-1">
                <span class="font-medium break-all">{{ p.name || `Modelo ${i + 1}` }} <span class="text-muted tabular-nums">× {{ p.qty || 1 }}</span></span>
                <button v-if="p.hasFile" type="button" class="order-last sm:order-none btn-outlined btn-sm" :aria-label="`Descargar ${p.name || 'modelo ' + (i + 1)}`" @click="emit('download', i)">Descargar</button>
                <span class="text-sm text-muted tabular-nums">
                  <template v-if="p.dims">{{ p.dims }} mm</template><template v-if="p.dims && p.weight"> · </template><template v-if="p.weight">{{ Math.round(p.weight) }} g</template><template v-if="p.params"> · {{ p.params }}</template><template v-if="p.subtotal"> · {{ money(p.subtotal) }}</template>
                </span>
              </li>
            </ul>
            <p v-else class="mt-3 text-muted">{{ isCatalog ? `${order.job_name?.replace(/^Catálogo: /, '') || 'Producto'} × ${order.qty || 1}` : 'Pedido manual, sin archivo 3D.' }}</p>
            <dl class="mt-4 grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
              <div><dt class="text-muted">Tecnología</dt><dd class="mt-0.5 font-medium">{{ isCatalog ? 'Catálogo' : order.technology }}</dd></div>
              <div><dt class="text-muted">Material</dt><dd class="mt-0.5 font-medium">{{ order.material_name || materialName }}</dd></div>
              <div v-if="order.infill"><dt class="text-muted">Relleno</dt><dd class="mt-0.5 font-medium tabular-nums">{{ order.infill }} %</dd></div>
              <div><dt class="text-muted">Unidades</dt><dd class="mt-0.5 font-medium tabular-nums">{{ order.qty || 1 }}</dd></div>
              <div><dt class="text-muted">Peso</dt><dd class="mt-0.5 font-medium tabular-nums">{{ Math.round(order.estimated_weight_g || 0) }} g</dd></div>
              <div><dt class="text-muted">Tiempo de máquina</dt><dd class="mt-0.5 font-medium tabular-nums">{{ hours(order.estimated_duration_h) }}</dd></div>
            </dl>
          </section>

          <!-- Cliente y envío -->
          <section aria-labelledby="od-customer" class="grid gap-4 sm:grid-cols-2">
            <div>
              <h3 id="od-customer" class="admin-h2 text-base">Cliente</h3>
              <p class="mt-2 font-semibold">{{ order.customer_name || 'Sin nombre' }}</p>
              <p v-if="order.customer_company" class="text-sm text-muted">{{ order.customer_company }}</p>
              <p v-if="order.customer_id_document" class="text-sm text-muted">CC/NIT {{ order.customer_id_document }}</p>
              <p v-if="order.customer_email" class="text-sm"><a :href="'mailto:' + order.customer_email" class="text-brand hover:underline break-all">{{ order.customer_email }}</a></p>
              <p v-if="order.customer_phone" class="text-sm"><a :href="'tel:' + order.customer_phone" class="hover:underline">{{ order.customer_phone }}</a></p>
            </div>
            <div>
              <h3 class="admin-h2 text-base">Envío</h3>
              <p v-if="order.shipping_address || order.shipping_city" class="mt-2 text-sm">{{ [order.shipping_address, order.shipping_city, order.shipping_zip].filter(Boolean).join(', ') }}</p>
              <p v-else class="mt-2 text-sm text-muted">Sin dirección registrada.</p>
              <p v-if="order.shipping_reference" class="text-sm text-muted">{{ order.shipping_reference }}</p>
            </div>
          </section>

          <!-- Guía -->
          <section v-if="canEdit && ['completed', 'shipped'].includes(order.status)" aria-labelledby="od-tracking">
            <h3 id="od-tracking" class="admin-h2 text-base">Guía de envío</h3>
            <form class="mt-3 grid gap-3 sm:grid-cols-[1fr_1fr_auto] sm:items-end" @submit.prevent="saveTracking">
              <div><label for="od-carrier" class="admin-label mb-1">Transportadora</label><input id="od-carrier" v-model="localCarrier" type="text" placeholder="Servientrega" class="field"></div>
              <div><label for="od-guide" class="admin-label mb-1">Número de guía</label><input id="od-guide" v-model="localGuide" type="text" inputmode="numeric" class="field tabular-nums"></div>
              <button type="submit" class="btn-primary">{{ trackingSaved ? 'Guardada' : 'Guardar guía' }}</button>
            </form>
          </section>

          <section v-if="order.comments" aria-labelledby="od-notes">
            <h3 id="od-notes" class="admin-h2 text-base">Notas</h3>
            <p class="mt-2 text-sm whitespace-pre-line">{{ order.comments }}</p>
          </section>
        </div>

        <div class="lg:col-span-5 space-y-6">
          <!-- Precio y costos -->
          <section class="admin-card" aria-labelledby="od-money">
            <div class="flex items-baseline justify-between gap-3">
              <h3 id="od-money" class="admin-h2 text-base">Precio y costos</h3>
              <span v-if="breakdown" class="text-sm font-semibold" :class="(breakdown.profit_margin_pct || 0) >= 25 ? 'text-success' : 'text-warning'">{{ breakdown.profit_margin_pct || 0 }} % de margen</span>
            </div>
            <p class="mt-3 admin-kpi">{{ money(order.total_price) }}</p>
            <dl v-if="breakdown" class="mt-4 text-sm">
              <div
v-for="row in [
                ['Material', breakdown.material], ['Energía', breakdown.luz], ['Mano de obra', breakdown.labor],
                ['Depreciación', breakdown.depr], ['Mantenimiento', breakdown.mant], ['Empaque', breakdown.prep_pack],
              ]" :key="String(row[0])" class="flex justify-between py-1.5">
                <dt class="text-muted">{{ row[0] }}</dt><dd class="tabular-nums">{{ money(row[1]) }}</dd>
              </div>
              <div class="flex justify-between pt-3 mt-2 border-t border-line font-semibold"><dt>Costo de producción</dt><dd class="tabular-nums">{{ money(breakdown.production_cost) }}</dd></div>
              <div class="flex justify-between pt-1.5 font-semibold"><dt>Margen</dt><dd class="tabular-nums" :class="breakdown.operating_margin < 0 ? 'text-danger' : 'text-success'">{{ money(breakdown.operating_margin) }}</dd></div>
            </dl>
          </section>

          <!-- Insumos extra -->
          <section class="admin-card" aria-labelledby="od-extras">
            <h3 id="od-extras" class="admin-h2 text-base">Insumos extra</h3>
            <ul v-if="order.extra_items?.length" class="mt-3 space-y-1.5 text-sm">
              <li v-for="(item, idx) in order.extra_items" :key="idx" class="flex justify-between gap-3">
                <span>{{ item.name }}<template v-if="item.qty > 1"> × {{ item.qty }}</template></span>
                <span class="tabular-nums">{{ money(item.cost) }}</span>
              </li>
            </ul>
            <p v-else class="mt-2 text-sm text-muted">Sin insumos extra.</p>
            <p v-if="actionError" role="alert" class="mt-3 text-sm text-danger">{{ actionError }}</p>
            <form v-if="canEdit && showAddForm" class="mt-4 space-y-2" @submit.prevent="addExtra">
              <label for="od-extra" class="admin-label">Insumo</label>
              <select id="od-extra" v-model="newExtra.material_id" class="field">
                <option value="">Elegir…</option>
                <option v-for="m in (utilities.length ? utilities : materials)" :key="m.id" :value="m.id">{{ m.name }}</option>
              </select>
              <div class="flex gap-2">
                <label for="od-extra-qty" class="sr-only">Cantidad</label>
                <input id="od-extra-qty" v-model.number="newExtra.qty" type="number" min="1" class="field tabular-nums max-w-[7rem]">
                <button type="submit" class="btn-primary" :disabled="!newExtra.material_id">Agregar</button>
                <button type="button" class="btn-outlined" @click="showAddForm = false">Cancelar</button>
              </div>
            </form>
            <button v-else-if="canEdit" type="button" class="mt-4 btn-outlined btn-sm" @click="showAddForm = true">Agregar insumo</button>
          </section>
        </div>
      </div>
    </template>
  </AdminModal>
</template>

<script setup lang="ts">
import { ref, computed, watch, type PropType } from 'vue'
import { api } from '../../services/api'
import { calcOrderDetailBreakdown } from '../../services/costCalculator'
import logger from '../../utils/logger'
import AdminModal from './AdminModal.vue'

const props = defineProps({
  order: { type: Object, default: null },
  materials: { type: Array as PropType<{ id: string; name: string; cost_per_kg?: number | string }[]>, default: () => [] },
  settings: { type: Object, default: () => ({ infra: {}, prep: {}, margin: {} }) },
  /** Guía e insumos solo con permiso de Producción (permisos del equipo) */
  canEdit: { type: Boolean, default: true },
})

const actionError = ref('')

const emit = defineEmits(['close', 'updated', 'update-order', 'download'])

const showAddForm = ref(false)
const newExtra = ref({ material_id: '', qty: 1 })

// Local state for tracking fields to avoid prop mutation
const localCarrier = ref(props.order?.tracking_carrier || '')
const localGuide = ref(props.order?.tracking_guide || '')

// Sync local state when prop changes
watch(() => props.order?.tracking_carrier, (val) => { localCarrier.value = val || '' })
watch(() => props.order?.tracking_guide, (val) => { localGuide.value = val || '' })

const statusSteps = [
  { id: 'pending', label: 'Por revisar', desc: 'En cola de revisión.' },
  { id: 'printing', label: 'En impresión', desc: 'En máquina.' },
  { id: 'post-processing', label: 'En acabado', desc: 'Limpieza y curado.' },
  { id: 'completed', label: 'Listo', desc: 'Listo para entrega.' },
  { id: 'shipped', label: 'Enviado', desc: 'En camino al cliente.' },
]

const getStatusIndex = (status: string) => {
  const mapping: Record<string, number> = { pending: 0, printing: 1, 'post-processing': 2, completed: 3, shipped: 4 }
  return mapping[status] ?? -1
}

const statusLabel = computed(() => {
  const idx = getStatusIndex(props.order?.status)
  if (props.order?.status === 'cancelled') return 'Cancelado'
  return idx >= 0 ? statusSteps[idx].label : props.order?.status
})

const statusClass = computed(() => {
  switch (props.order?.status) {
    case 'pending': return 'bg-warning/10 text-warning'
    case 'printing': return 'bg-primary text-white'
    case 'completed': return 'bg-success/10 text-success'
    case 'cancelled': return 'bg-danger/10 text-danger'
    default: return 'bg-surface-2 text-ink'
  }
})

const money = (n: any) => '$' + Math.round(Number(n) || 0).toLocaleString('es-CO')
const isCatalog = computed(() => props.order?.technology === 'CATALOGO')
// Relleno y capa con que se cotizó el modelo (los pedidos viejos no los tienen)
const printParams = (m: any) => [
  m.infill != null && m.infill !== '' ? `relleno ${m.infill} %` : '',
  Number(m.layer_height) > 0 ? `capa ${Number(m.layer_height)} mm` : '',
].filter(Boolean).join(' · ')
// Piezas del pedido (models[] o el archivo suelto de pedidos viejos)
const pieces = computed(() => {
  const o = props.order
  if (!o) return []
  if (Array.isArray(o.models) && o.models.length) {
    return o.models.map((m: any) => ({ name: m.original_filename, qty: m.qty, dims: m.dimensions_mm, weight: m.weight_g, params: printParams(m), subtotal: m.subtotal, hasFile: !!m.path }))
  }
  return o.original_filename ? [{ name: o.original_filename, qty: o.qty, dims: o.dimensions_mm, weight: o.estimated_weight_g, params: '', subtotal: null, hasFile: !!o.file_path }] : []
})
const utilities = computed(() => props.materials.filter((m: any) => m.type && m.type !== 'material'))
const trackingSaved = ref(false)
const hours = (h: any) => {
  const total = Math.round((Number(h) || 0) * 60)
  if (!total) return '—'
  return total < 60 ? `${total} min` : `${Math.floor(total / 60)} h${total % 60 ? ' ' + (total % 60) + ' min' : ''}`
}


const materialName = computed(() => {
  if (!props.order?.material_id) return '-'
  const mat = props.materials.find(m => m.id === props.order.material_id)
  return mat ? mat.name : props.order.material_id
})

const breakdown = computed(() => {
  if (!props.order) return null
  const s = props.order.cost_snapshot?.settings || props.settings
  const matCostPerKg = props.order.cost_snapshot?.material_cost_per_kg
    || props.materials.find(m => m.id === props.order.material_id)?.cost_per_kg
    || 0
  return calcOrderDetailBreakdown(props.order, s, matCostPerKg)
})

const addExtra = async () => {
  if (!newExtra.value.material_id || newExtra.value.qty <= 0) return
  actionError.value = ''
  try {
    await api.post(`/admin/orders/${props.order.id}/extras`, newExtra.value)
    emit('updated')
    showAddForm.value = false
    newExtra.value = { material_id: '', qty: 1 }
  } catch (err) {
    logger.error('Error adding extra:', err)
    actionError.value = `No se pudo agregar el insumo: ${err instanceof Error ? err.message : 'error desconocido'}`
  }
}

const saveTracking = async () => {
  actionError.value = ''
  try {
    // El endpoint exige el estado: se reenvía el actual junto con la guía
    await api.patch(`/admin/orders/${props.order.id}/status`, {
      status: props.order.status,
      tracking_guide: localGuide.value,
      tracking_carrier: localCarrier.value
    })
    trackingSaved.value = true
    setTimeout(() => { trackingSaved.value = false }, 2500)
    emit('updated')
  } catch (err) {
    logger.error('Error saving tracking:', err)
    actionError.value = `No se pudo guardar la guía: ${err instanceof Error ? err.message : 'error desconocido'}`
  }
}
</script>
