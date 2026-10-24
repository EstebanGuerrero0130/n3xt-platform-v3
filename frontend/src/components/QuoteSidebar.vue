<script setup lang="ts">
import { ref, computed, watch, type PropType } from 'vue'

interface SidebarModel {
  id: number
  hasModel: boolean
  hasSlicing: boolean
  infill: number
  layerHeight: number
  dimensions: { x: number; y: number; z: number }
  [key: string]: unknown
}
interface SidebarMaterial { id: string; name: string; category: string; cost_per_kg?: number | string; density?: number | string }
interface SidebarExtra { id: string; qty: number }

const props = defineProps({
  models: { type: Array as PropType<SidebarModel[]>, default: () => [] },
  activeModelIdx: { type: Number, default: 0 },
  materials: { type: Array as PropType<SidebarMaterial[]>, default: () => [] },
  utilities: { type: Array as PropType<SidebarMaterial[]>, default: () => [] },
  selectedTechnology: { type: String, default: 'FDM' },
  selectedMaterial: { type: String, default: '' },
  qty: { type: Number, default: 1 },
  selectedExtras: { type: Array as PropType<SidebarExtra[]>, default: () => [] },
  activeCoupon: { type: Object as PropType<{ label: string; discount: number } | null>, default: null },
  couponCode: { type: String, default: '' },
  breakdown: { type: Object as PropType<{ weight: number; duration: number; matCost: number; infraCost: number; utilityCost: number; marginCost: number; discount: number; subtotal: number; iva: number; ivaPct?: number; total: number }>, default: () => ({ weight: 0, duration: 0, matCost: 0, infraCost: 0, utilityCost: 0, marginCost: 0, discount: 0, subtotal: 0, iva: 0, ivaPct: 19, total: 0 }) },
  isSlicing: { type: Boolean, default: false },
  previousTotal: { type: Number, default: 0 }
})

const emit = defineEmits([
  'update:selectedTechnology',
  'update:selectedMaterial',
  'update:qty',
  'update:couponCode',
  'toggleExtra',
  'applyCoupon',
  'calculate',
  'requestQuote',
  'update:models'
])

const localQty = ref(props.qty)
const localCoupon = ref(props.couponCode)

watch(() => props.qty, (v) => { localQty.value = v })
// Cantidad: solo enteros >= 1
const normalizeQty = (v: unknown) => Math.max(1, Math.floor(Number(v)) || 1)
watch(localQty, (v) => {
  if ((v as unknown) === '') return // permitir borrar mientras se escribe
  const n = normalizeQty(v)
  if (n !== v) localQty.value = n
  emit('update:qty', n)
})
watch(localCoupon, (v) => emit('update:couponCode', v))

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

const hasSlicingOrSLA = computed(() => 
  props.models.some(m => m.hasSlicing) || props.selectedTechnology === 'SLA'
)

// FDM: algún modelo cargado sin laminado vigente (nuevo o con parámetros modificados)
const needsRecalc = computed(() =>
  props.selectedTechnology === 'FDM' && props.models.some(m => m.hasModel && !m.hasSlicing)
)

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
          class="relative text-left p-3.5 rounded-[var(--radius-card)] border transition-colors"
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
    <section v-if="selectedTechnology === 'FDM' || models[activeModelIdx]?.hasModel" aria-labelledby="quote-params-title">
      <h3 id="quote-params-title" class="text-sm font-semibold text-ink [font-stretch:100%] tracking-normal mb-3">Parámetros</h3>
      <div v-if="selectedTechnology === 'FDM'" class="grid grid-cols-1 min-[400px]:grid-cols-2 gap-4">
        <div>
          <label for="quote-infill" class="block text-sm font-medium text-ink mb-2">Relleno (%)</label>
          <input id="quote-infill" type="number" :value="models[activeModelIdx]?.infill || 15" min="5" max="100" step="5" inputmode="numeric" class="w-full h-11 px-4 rounded-[var(--radius-control)] border border-line bg-canvas text-ink tabular-nums focus:outline-none focus:border-primary" @input="$emit('update:models', models.map((m, i) => i === activeModelIdx ? { ...m, infill: Number(($event.target as HTMLInputElement).value) } : m))">
        </div>
        <div>
          <label for="quote-layer" class="block text-sm font-medium text-ink mb-2">Altura de capa</label>
          <select id="quote-layer" :value="models[activeModelIdx]?.layerHeight || 0.2" class="w-full h-11 px-3 rounded-[var(--radius-control)] border border-line bg-canvas text-ink cursor-pointer focus:outline-none focus:border-primary" @change="$emit('update:models', models.map((m, i) => i === activeModelIdx ? { ...m, layerHeight: Number(($event.target as HTMLSelectElement).value) } : m))">
            <option :value="0.12">0,12 mm (ultra detalle)</option>
            <option :value="0.16">0,16 mm (alta)</option>
            <option :value="0.2">0,20 mm (estándar)</option>
            <option :value="0.28">0,28 mm (rápida)</option>
          </select>
        </div>
      </div>

      <dl v-if="models[activeModelIdx]?.hasModel" class="mt-4 divide-y divide-line rounded-[var(--radius-control)] border border-line text-sm">
        <div class="flex items-center justify-between gap-4 px-4 py-2.5">
          <dt class="text-muted">Escala</dt>
          <dd class="font-medium tabular-nums text-ink">{{ Math.round(Number(models[activeModelIdx]?.currentScale ?? 1) * 100) }} %</dd>
        </div>
        <div class="flex items-center justify-between gap-4 px-4 py-2.5">
          <dt class="text-muted">Tamaño (X × Y × Z)</dt>
          <dd class="font-medium tabular-nums text-ink text-right">
            {{ Number(models[activeModelIdx]?.dimensions.x || 0).toFixed(1) }} × {{ Number(models[activeModelIdx]?.dimensions.y || 0).toFixed(1) }} × {{ Number(models[activeModelIdx]?.dimensions.z || 0).toFixed(1) }} mm
          </dd>
        </div>
      </dl>
      <p v-if="models[activeModelIdx]?.hasModel" class="mt-2 text-xs text-muted">Para cambiar la escala usa la herramienta Escalar del visor.</p>
    </section>

    <!-- Cantidad -->
    <section>
      <label for="quote-qty" class="block text-sm font-semibold text-ink mb-3">Cantidad</label>
      <div class="inline-flex items-stretch h-11 rounded-[var(--radius-control)] border border-line bg-canvas overflow-hidden">
        <button type="button" class="w-11 flex items-center justify-center text-lg text-muted hover:bg-surface-2 hover:text-ink transition-colors" aria-label="Disminuir cantidad" @click="localQty = Math.max(1, normalizeQty(localQty) - 1)">−</button>
        <input id="quote-qty" v-model.number="localQty" type="number" min="1" step="1" inputmode="numeric" aria-label="Cantidad" class="w-16 border-x border-line bg-transparent text-center font-semibold tabular-nums text-ink focus:outline-none focus:bg-surface [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none" @blur="localQty = normalizeQty(localQty)">
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
          class="w-full flex items-center gap-3 px-4 py-3 rounded-[var(--radius-control)] border text-left text-sm transition-colors"
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
        <input id="quote-coupon" v-model="localCoupon" type="text" autocomplete="off" placeholder="Escribe tu código" class="min-w-0 flex-1 h-11 px-4 rounded-[var(--radius-control)] border border-line bg-canvas text-ink uppercase placeholder:normal-case placeholder:text-muted/70 focus:outline-none focus:border-primary" @keyup.enter="$emit('applyCoupon')">
        <button type="button" class="btn-secondary shrink-0" @click="$emit('applyCoupon')">Aplicar</button>
      </div>
      <p v-if="activeCoupon" class="mt-2 text-sm font-medium text-resin">{{ activeCoupon.label }}: {{ Math.round(activeCoupon.discount * 100) }} % de descuento aplicado</p>
    </section>

    <!-- Calcular (FDM) -->
    <div v-if="selectedTechnology === 'FDM'" class="space-y-2">
      <p v-if="needsRecalc && !isSlicing" id="quote-recalc-hint" class="text-sm text-amber-700 dark:text-amber-300">
        Calcula el precio con el archivo y los parámetros actuales para poder solicitar la cotización.
      </p>
      <button
        type="button"
        :disabled="isSlicing || !models[activeModelIdx]?.hasModel"
        :class="needsRecalc || isSlicing ? 'btn-primary btn-lg' : 'btn-secondary'"
        class="w-full"
        :aria-describedby="needsRecalc && !isSlicing ? 'quote-recalc-hint' : undefined"
        @click="$emit('calculate')"
      >
        <span v-if="isSlicing" class="w-4 h-4 border-2 border-current/30 border-t-current rounded-full animate-spin" aria-hidden="true"></span>
        <span>{{ isSlicing ? 'Calculando…' : (models[activeModelIdx]?.hasSlicing ? 'Recalcular precio' : 'Calcular precio') }}</span>
      </button>
    </div>

    <!-- Resumen de precio -->
    <section aria-labelledby="quote-summary-title" aria-live="polite" class="rounded-[var(--radius-card)] border border-line bg-canvas p-5">
      <div class="flex items-center justify-between gap-3">
        <h3 id="quote-summary-title" class="text-sm font-semibold text-ink [font-stretch:100%] tracking-normal">Resumen de precio</h3>
        <span class="inline-flex items-center gap-1.5 text-xs font-medium" :class="isPriced ? 'text-brand' : 'text-muted'">
          <span class="w-1.5 h-1.5 rounded-full" :class="isPriced ? 'bg-primary' : 'bg-muted/60'" aria-hidden="true"></span>
          {{ isPriced ? 'Calculado' : 'Pendiente de cálculo' }}
        </span>
      </div>

      <template v-if="isPriced">
        <p class="mt-3 text-sm text-muted">Total estimado (IVA incluido)</p>
        <p class="price-count text-3xl font-extrabold tracking-tight tabular-nums text-resin" :class="{ 'bump': previousTotal !== breakdown.total && previousTotal > 0 }">
          ${{ Math.round(breakdown.total).toLocaleString('es-CO') }}
        </p>

        <dl class="mt-4 space-y-2 text-sm">
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

      <p class="mt-4 text-xs text-muted">Precio estimado, sujeto a revisión del taller.</p>
    </section>

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
