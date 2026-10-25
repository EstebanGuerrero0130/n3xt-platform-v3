<script setup lang="ts">
import { ref, computed, watch, type PropType } from 'vue'

interface SidebarModel {
  id: number
  hasModel: boolean
  hasSlicing: boolean
  qty: number
  dimensions: { x: number; y: number; z: number }
  [key: string]: unknown
}
interface SidebarMaterial { id: string; name: string; category: string; cost_per_kg?: number | string; density?: number | string }
interface SidebarExtra { id: string; qty: number }
interface ModelSummary { id: number; name: string; qty: number; total: number; priced: boolean }

const props = defineProps({
  models: { type: Array as PropType<SidebarModel[]>, default: () => [] },
  activeModelIdx: { type: Number, default: 0 },
  materials: { type: Array as PropType<SidebarMaterial[]>, default: () => [] },
  utilities: { type: Array as PropType<SidebarMaterial[]>, default: () => [] },
  selectedTechnology: { type: String, default: 'FDM' },
  selectedMaterial: { type: String, default: '' },
  /** Cantidad del modelo activo */
  qty: { type: Number, default: 1 },
  infill: { type: Number, default: 15 },
  layerHeight: { type: Number, default: 0.2 },
  selectedExtras: { type: Array as PropType<SidebarExtra[]>, default: () => [] },
  activeCoupon: { type: Object as PropType<{ code: string; label: string; discount_pct: number } | null>, default: null },
  couponCode: { type: String, default: '' },
  couponError: { type: String, default: '' },
  couponLoading: { type: Boolean, default: false },
  breakdown: { type: Object as PropType<{ pieces?: number; weight: number; duration: number; matCost: number; infraCost: number; utilityCost: number; marginCost: number; discount: number; subtotal: number; iva: number; ivaPct?: number; total: number }>, default: () => ({ pieces: 0, weight: 0, duration: 0, matCost: 0, infraCost: 0, utilityCost: 0, marginCost: 0, discount: 0, subtotal: 0, iva: 0, ivaPct: 19, total: 0 }) },
  modelSummaries: { type: Array as PropType<ModelSummary[]>, default: () => [] },
  isSlicing: { type: Boolean, default: false },
  previousTotal: { type: Number, default: 0 },
  lastOrder: { type: Object as PropType<{ id: number | string | null; total: number | null } | null>, default: null }
})

const emit = defineEmits([
  'update:selectedTechnology',
  'update:selectedMaterial',
  'update:qty',
  'update:infill',
  'update:layerHeight',
  'update:couponCode',
  'toggleExtra',
  'applyCoupon',
  'removeCoupon',
  'calculate',
  'requestQuote'
])

const localQty = ref(props.qty)
const localCoupon = ref(props.couponCode)

watch(() => props.qty, (v) => { localQty.value = v })
watch(() => props.couponCode, (v) => { if (v !== localCoupon.value) localCoupon.value = v })
// Cantidad: solo enteros >= 1
const normalizeQty = (v: unknown) => Math.max(1, Math.floor(Number(v)) || 1)
watch(localQty, (v) => {
  if ((v as unknown) === '') return // permitir borrar mientras se escribe
  const n = normalizeQty(v)
  if (n !== v) localQty.value = n
  if (n !== props.qty) emit('update:qty', n)
})
watch(localCoupon, (v) => emit('update:couponCode', v))

const activeModel = computed(() => props.models[props.activeModelIdx])
const loadedCount = computed(() => props.models.filter(m => m.hasModel).length)

const filteredMaterials = computed(() =>
  props.materials.filter(m => m.category === props.selectedTechnology)
)

const formatTime = (h: number) => {
    if (!h || h < 0) return '0m';
    const hours = Math.floor(h);
    const minutes = Math.round((h - hours) * 60);
    if (hours === 0) return `${minutes}m`;
    return `${hours}h ${minutes}m`;
}
const money = (n: number) => '$' + Math.round(n || 0).toLocaleString('es-CO')

const hasSlicingOrSLA = computed(() => 
  props.models.some(m => m.hasSlicing) || props.selectedTechnology === 'SLA'
)

// FDM: modelos cargados sin laminado vigente (nuevos o con parámetros modificados)
const pendingCount = computed(() =>
  props.selectedTechnology === 'FDM' ? props.models.filter(m => m.hasModel && !m.hasSlicing).length : 0
)
const needsRecalc = computed(() => pendingCount.value > 0)

const calcLabel = computed(() => {
  if (props.isSlicing) return 'Calculando…'
  if (!needsRecalc.value) return loadedCount.value > 1 ? 'Recalcular todos' : 'Recalcular precio'
  return pendingCount.value > 1 ? `Calcular precio (${pendingCount.value} modelos)` : 'Calcular precio'
})

const quoteBlockedReason = computed(() => {
  if (!props.models.some(m => m.hasModel)) return 'Sube un modelo 3D para solicitar la cotización.'
  if (needsRecalc.value) return 'Calcula (o recalcula) el precio antes de solicitar la cotización.'
  if (!(props.breakdown.total > 0)) return 'El precio aún no está calculado.'
  return ''
})

// Solo presentación: hay un precio vigente que mostrar en el resumen
const isPriced = computed(() => hasSlicingOrSLA.value && !needsRecalc.value && props.breakdown.total > 0)
</script>

<template>
  <div class="p-5 sm:p-6 lg:p-8 space-y-8">
    <!-- Encabezado -->
    <div>
      <h2 class="text-xl md:text-2xl font-extrabold tracking-tight text-ink">Configura tu pieza</h2>
      <p class="mt-1 text-sm text-muted">Elige tecnología, material y parámetros. El precio se calcula con tu archivo.</p>
    </div>

    <!-- Tecnología -->
    <section aria-labelledby="quote-tech-title">
      <h3 id="quote-tech-title" class="text-sm font-semibold text-ink [font-stretch:100%] tracking-normal mb-3">Tecnología</h3>
      <div class="grid grid-cols-2 gap-1 p-1 rounded-[var(--radius-control)] bg-surface-2 border border-line">
        <button
          v-for="t in ['FDM', 'SLA']" :key="t"
          type="button"
          :aria-pressed="selectedTechnology === t"
          :class="selectedTechnology === t ? 'bg-surface text-ink border-line' : 'border-transparent text-muted hover:text-ink'"
          class="min-h-11 px-3 py-1.5 rounded-[6px] border text-sm font-semibold transition-colors flex flex-col items-center justify-center leading-tight"
          @click="$emit('update:selectedTechnology', t)"
        >
          <span>{{ t }}</span>
          <span class="text-xs font-normal text-muted">{{ t === 'FDM' ? 'Filamento' : 'Resina' }}</span>
        </button>
      </div>
    </section>

    <!-- Material -->
    <section aria-labelledby="quote-material-title">
      <h3 id="quote-material-title" class="text-sm font-semibold text-ink [font-stretch:100%] tracking-normal mb-3">Material</h3>
      <div v-if="filteredMaterials.length" class="grid grid-cols-1 min-[400px]:grid-cols-2 gap-2">
        <button
          v-for="mat in filteredMaterials" :key="mat.id"
          type="button"
          :aria-pressed="selectedMaterial === mat.id"
          :class="selectedMaterial === mat.id ? 'border-primary bg-primary/5 ring-1 ring-primary' : 'border-line bg-surface hover:border-muted'"
          class="surface-card-interactive relative text-left p-3.5 rounded-[var(--radius-card)] border transition-colors"
          @click="$emit('update:selectedMaterial', mat.id)"
        >
          <span
            class="absolute top-3 right-3 w-5 h-5 rounded-full flex items-center justify-center border"
            :class="selectedMaterial === mat.id ? 'bg-primary border-primary text-white' : 'border-line'"
            aria-hidden="true"
          >
            <svg v-if="selectedMaterial === mat.id" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
          </span>
          <span class="block pr-7 text-[0.9375rem] font-semibold text-ink leading-snug">{{ mat.name }}</span>
          <span class="mt-1 block text-sm font-medium tabular-nums text-ink">${{ Number(mat.cost_per_kg || 0).toLocaleString('es-CO') }} <span class="font-normal text-muted">/ kg</span></span>
          <span v-if="mat.density" class="block text-xs text-muted tabular-nums">{{ mat.density }} g/cm³</span>
        </button>
      </div>
      <p v-else class="text-sm text-muted">No hay materiales disponibles para {{ selectedTechnology }} en este momento.</p>
    </section>

    <!-- Parámetros -->
    <section v-if="selectedTechnology === 'FDM' || activeModel?.hasModel" aria-labelledby="quote-params-title">
      <h3 id="quote-params-title" class="text-sm font-semibold text-ink [font-stretch:100%] tracking-normal mb-3">Parámetros</h3>
      <div v-if="selectedTechnology === 'FDM'" class="grid grid-cols-1 min-[400px]:grid-cols-2 gap-4">
        <div>
          <label for="quote-infill" class="block text-sm font-medium text-ink mb-2">Relleno (%)</label>
          <input id="quote-infill" type="number" :value="infill || 15" min="5" max="100" step="5" inputmode="numeric" :aria-describedby="models.length > 1 ? 'quote-params-scope' : undefined" class="w-full h-11 px-4 rounded-[var(--radius-control)] border border-line bg-canvas text-ink tabular-nums focus:outline-none focus:border-primary" @input="$emit('update:infill', Number(($event.target as HTMLInputElement).value))">
        </div>
        <div>
          <label for="quote-layer" class="block text-sm font-medium text-ink mb-2">Altura de capa</label>
          <select id="quote-layer" :value="layerHeight || 0.2" :aria-describedby="models.length > 1 ? 'quote-params-scope' : undefined" class="w-full h-11 px-3 rounded-[var(--radius-control)] border border-line bg-canvas text-ink cursor-pointer focus:outline-none focus:border-primary" @change="$emit('update:layerHeight', Number(($event.target as HTMLSelectElement).value))">
            <option :value="0.12">0,12 mm (ultra detalle)</option>
            <option :value="0.16">0,16 mm (alta)</option>
            <option :value="0.2">0,20 mm (estándar)</option>
            <option :value="0.28">0,28 mm (rápida)</option>
          </select>
        </div>
      </div>
      <p v-if="selectedTechnology === 'FDM' && models.length > 1" id="quote-params-scope" class="mt-2 text-sm text-muted">Relleno y altura de capa se aplican a todos los modelos del pedido.</p>

      <p v-if="activeModel?.hasModel && models.length > 1" class="mt-4 text-sm font-medium text-ink">Modelo seleccionado</p>
      <dl v-if="activeModel?.hasModel" :class="models.length > 1 ? 'mt-2' : 'mt-4'" class=" divide-y divide-line rounded-[var(--radius-control)] border border-line text-sm">
        <div class="flex items-center justify-between gap-4 px-4 py-2.5">
          <dt class="text-muted">Escala</dt>
          <dd class="font-medium tabular-nums text-ink">{{ Math.round(Number(activeModel?.currentScale ?? 1) * 100) }} %</dd>
        </div>
        <div class="flex items-center justify-between gap-4 px-4 py-2.5">
          <dt class="text-muted">Tamaño (X × Y × Z)</dt>
          <dd class="font-medium tabular-nums text-ink text-right">
            {{ Number(activeModel?.dimensions.x || 0).toFixed(1) }} × {{ Number(activeModel?.dimensions.y || 0).toFixed(1) }} × {{ Number(activeModel?.dimensions.z || 0).toFixed(1) }} mm
          </dd>
        </div>
      </dl>
      <p v-if="activeModel?.hasModel" class="mt-2 text-xs text-muted">Para cambiar la escala usa la herramienta Escalar del visor.</p>
    </section>

    <!-- Cantidad -->
    <section>
      <label for="quote-qty" class="block text-sm font-semibold text-ink mb-3">{{ models.length > 1 ? 'Cantidad de este modelo' : 'Cantidad' }}</label>
      <div class="inline-flex items-stretch h-11 rounded-[var(--radius-control)] border border-line bg-canvas overflow-hidden">
        <button type="button" class="w-11 flex items-center justify-center text-lg text-muted hover:bg-surface-2 hover:text-ink transition-colors" aria-label="Disminuir cantidad" @click="localQty = Math.max(1, normalizeQty(localQty) - 1)">−</button>
        <input id="quote-qty" v-model.number="localQty" type="number" min="1" step="1" inputmode="numeric" :aria-label="models.length > 1 ? 'Cantidad de este modelo' : 'Cantidad'" class="w-16 border-x border-line bg-transparent text-center font-semibold tabular-nums text-ink focus:outline-none focus:bg-surface [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none" @blur="localQty = normalizeQty(localQty)">
        <button type="button" class="w-11 flex items-center justify-center text-lg text-muted hover:bg-surface-2 hover:text-ink transition-colors" aria-label="Aumentar cantidad" @click="localQty = normalizeQty(localQty) + 1">+</button>
      </div>
    </section>

    <!-- Extras -->
    <section v-if="utilities.length > 0" aria-labelledby="quote-extras-title">
      <h3 id="quote-extras-title" class="text-sm font-semibold text-ink [font-stretch:100%] tracking-normal mb-3">Extras</h3>
      <div class="space-y-2">
        <button
          v-for="u in utilities" :key="u.id"
          type="button"
          :aria-pressed="!!selectedExtras.find(e => e.id === u.id)"
          :class="selectedExtras.find(e => e.id === u.id) ? 'border-primary bg-primary/5' : 'border-line bg-surface hover:border-muted'"
          class="surface-card-interactive w-full flex items-center gap-3 px-4 py-3 rounded-[var(--radius-control)] border text-left text-sm transition-colors"
          @click="$emit('toggleExtra', u.id)"
        >
          <span
            class="w-5 h-5 shrink-0 rounded-[4px] border flex items-center justify-center"
            :class="selectedExtras.find(e => e.id === u.id) ? 'bg-primary border-primary text-white' : 'border-line'"
            aria-hidden="true"
          >
            <svg v-if="selectedExtras.find(e => e.id === u.id)" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
          </span>
          <span class="flex-1 font-medium text-ink">{{ u.name }}</span>
          <span class="tabular-nums text-muted">${{ Number(u.cost_per_kg || 0).toLocaleString('es-CO') }} / u</span>
        </button>
      </div>
    </section>

    <!-- Cupón -->
    <section>
      <label for="quote-coupon" class="block text-sm font-semibold text-ink mb-3">Cupón de descuento</label>
      <div class="flex gap-2">
        <input
          id="quote-coupon"
          v-model="localCoupon"
          type="text"
          autocomplete="off"
          placeholder="Escribe tu código"
          :aria-invalid="couponError ? 'true' : undefined"
          :aria-describedby="couponError ? 'quote-coupon-error' : (activeCoupon ? 'quote-coupon-ok' : undefined)"
          class="min-w-0 flex-1 h-11 px-4 rounded-[var(--radius-control)] border bg-canvas text-ink uppercase placeholder:normal-case placeholder:text-muted/70 focus:outline-none focus:border-primary"
          :class="couponError ? 'border-red-600 dark:border-red-400' : 'border-line'"
          @keyup.enter="$emit('applyCoupon')"
        >
        <button type="button" class="btn-secondary shrink-0" :disabled="couponLoading" @click="$emit('applyCoupon')">
          <span v-if="couponLoading" class="w-4 h-4 border-2 border-current/30 border-t-current rounded-full animate-spin" aria-hidden="true"></span>
          <span>{{ couponLoading ? 'Validando…' : 'Aplicar' }}</span>
        </button>
      </div>
      <p v-if="couponError" id="quote-coupon-error" class="mt-1.5 text-sm text-red-600 dark:text-red-400" role="alert">{{ couponError }}</p>
      <div v-else-if="activeCoupon" id="quote-coupon-ok" class="mt-2 flex items-center justify-between gap-3">
        <p class="text-sm font-medium text-resin">{{ activeCoupon.label }} ({{ activeCoupon.code }}): {{ activeCoupon.discount_pct }} % de descuento</p>
        <button type="button" class="shrink-0 text-sm font-medium text-muted underline underline-offset-2 hover:text-ink" :aria-label="`Quitar el cupón ${activeCoupon.code}`" @click="$emit('removeCoupon')">Quitar</button>
      </div>
    </section>

    <!-- Calcular (FDM) -->
    <div v-if="selectedTechnology === 'FDM'" class="space-y-2">
      <p v-if="needsRecalc && !isSlicing" id="quote-recalc-hint" class="text-sm text-amber-700 dark:text-amber-300">
        {{ pendingCount > 1 ? `Hay ${pendingCount} modelos sin precio. Calcúlalo` : 'Calcula el precio' }} con los archivos y parámetros actuales para poder solicitar la cotización.
      </p>
      <button
        type="button"
        :disabled="isSlicing || loadedCount === 0"
        :class="needsRecalc || isSlicing ? 'btn-primary btn-lg' : 'btn-secondary'"
        class="w-full"
        :aria-describedby="needsRecalc && !isSlicing ? 'quote-recalc-hint' : undefined"
        @click="$emit('calculate')"
      >
        <span v-if="isSlicing" class="w-4 h-4 border-2 border-current/30 border-t-current rounded-full animate-spin" aria-hidden="true"></span>
        <span>{{ calcLabel }}</span>
      </button>
    </div>

    <!-- Resumen de precio -->
    <section aria-labelledby="quote-summary-title" aria-live="polite" class="surface-card rounded-[var(--radius-card)] border border-line bg-canvas p-5">
      <div class="flex items-center justify-between gap-3">
        <h3 id="quote-summary-title" class="text-sm font-semibold text-ink [font-stretch:100%] tracking-normal">Resumen de precio</h3>
        <span class="inline-flex items-center gap-1.5 text-xs font-medium" :class="isPriced ? 'text-brand' : 'text-muted'">
          <span class="w-1.5 h-1.5 rounded-full" :class="isPriced ? 'bg-primary' : 'bg-muted/60'" aria-hidden="true"></span>
          {{ isPriced ? 'Estimado' : 'Pendiente de cálculo' }}
        </span>
      </div>

      <template v-if="isPriced">
        <p class="mt-3 text-sm text-muted">Total estimado (IVA incluido)</p>
        <p class="price-count text-3xl font-extrabold tracking-tight tabular-nums text-resin" :class="{ 'bump': previousTotal !== breakdown.total && previousTotal > 0 }">
          ${{ Math.round(breakdown.total).toLocaleString('es-CO') }}
        </p>

        <ul v-if="modelSummaries.length > 1" class="mt-4 space-y-1.5 text-sm" aria-label="Estimado por modelo">
          <li v-for="ms in modelSummaries" :key="ms.id" class="flex justify-between gap-4">
            <span class="min-w-0 truncate text-ink">{{ ms.name }} <span class="text-muted tabular-nums">× {{ ms.qty }}</span></span>
            <span class="shrink-0 tabular-nums text-ink">{{ money(ms.total) }}</span>
          </li>
        </ul>

        <dl class="mt-4 space-y-2 text-sm" :class="modelSummaries.length > 1 ? 'border-t border-line pt-3' : ''">
          <div v-if="(breakdown.pieces ?? 0) > 1" class="flex justify-between gap-4"><dt class="text-muted">Piezas</dt><dd class="tabular-nums text-ink">{{ breakdown.pieces }}</dd></div>
          <div class="flex justify-between gap-4"><dt class="text-muted">Peso estimado</dt><dd class="tabular-nums text-ink">{{ breakdown.weight.toFixed(1) }} g</dd></div>
          <div class="flex justify-between gap-4"><dt class="text-muted">Tiempo de impresión</dt><dd class="tabular-nums text-ink">{{ formatTime(breakdown.duration) }}</dd></div>
          <div class="border-t border-line pt-2 flex justify-between gap-4"><dt class="text-muted">Material</dt><dd class="tabular-nums text-ink">${{ Math.round(breakdown.matCost).toLocaleString('es-CO') }}</dd></div>
          <div class="flex justify-between gap-4"><dt class="text-muted">Máquina y energía</dt><dd class="tabular-nums text-ink">${{ Math.round(breakdown.infraCost).toLocaleString('es-CO') }}</dd></div>
          <div class="flex justify-between gap-4"><dt class="text-muted">Extras</dt><dd class="tabular-nums text-ink">${{ Math.round(breakdown.utilityCost).toLocaleString('es-CO') }}</dd></div>
          <div class="flex justify-between gap-4"><dt class="text-muted">Operación y margen</dt><dd class="tabular-nums text-ink">${{ Math.round(breakdown.marginCost).toLocaleString('es-CO') }}</dd></div>
          <div v-if="breakdown.discount > 0" class="flex justify-between gap-4"><dt class="text-resin">Descuento</dt><dd class="tabular-nums text-resin">−${{ Math.round(breakdown.discount).toLocaleString('es-CO') }}</dd></div>
          <div class="border-t border-line pt-2 flex justify-between gap-4"><dt class="text-muted">Subtotal</dt><dd class="tabular-nums font-medium text-ink">${{ Math.round(breakdown.subtotal).toLocaleString('es-CO') }}</dd></div>
          <div class="flex justify-between gap-4"><dt class="text-muted">IVA ({{ breakdown.ivaPct ?? 19 }} %)</dt><dd class="tabular-nums text-ink">${{ Math.round(breakdown.iva).toLocaleString('es-CO') }}</dd></div>
        </dl>
      </template>
      <template v-else>
        <p class="mt-3 text-3xl font-extrabold tracking-tight text-muted/60" aria-hidden="true">$ —</p>
        <p class="mt-2 text-sm text-muted">
          {{ selectedTechnology === 'FDM' ? 'Sube tu modelo y pulsa Calcular precio para ver el desglose.' : 'Sube tu modelo para ver el desglose.' }}
        </p>
      </template>

      <p class="mt-4 text-sm text-muted">Es un precio estimado. El precio definitivo lo confirma el taller al revisar tus archivos.</p>
    </section>

    <!-- Último pedido enviado: total calculado por el servidor -->
    <div v-if="lastOrder" class="surface-card rounded-[var(--radius-card)] border border-line bg-canvas p-4 text-sm" role="status">
      <p class="font-semibold text-ink">Pedido{{ lastOrder.id ? ` #${lastOrder.id}` : '' }} recibido</p>
      <p v-if="lastOrder.total !== null" class="mt-1 text-muted">Total registrado: <span class="font-semibold tabular-nums text-resin">{{ money(lastOrder.total) }}</span>. Te escribiremos para confirmarlo.</p>
      <p v-else class="mt-1 text-muted">Te escribiremos por WhatsApp o email para confirmar el precio.</p>
    </div>

    <!-- CTA -->
    <div>
      <button
        type="button"
        :disabled="!!quoteBlockedReason"
        class="btn-primary btn-lg w-full"
        :aria-describedby="quoteBlockedReason ? 'quote-blocked-hint' : undefined"
        @click="$emit('requestQuote')"
      >
        Solicitar cotización
      </button>
      <p v-if="quoteBlockedReason" id="quote-blocked-hint" class="mt-2 text-center text-sm text-muted">{{ quoteBlockedReason }}</p>
    </div>
  </div>
</template>

<style scoped>
.price-count {
  display: inline-block;
}
.price-count.bump {
  animation: priceBump 0.35s cubic-bezier(0.22, 1, 0.36, 1);
}
@keyframes priceBump {
  0% { transform: scale(1); }
  50% { transform: scale(1.04); }
  100% { transform: scale(1); }
}
</style>
