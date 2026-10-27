<template>
  <AdminModal :show="visible" title="Simulador de precios" description="Calcula el precio con los valores de Parámetros. Puedes descargar la cotización o convertirla en pedido." size="xl" @close="$emit('close')">
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
      <!-- Datos -->
      <form class="lg:col-span-7 space-y-6" novalidate @submit.prevent>
        <section class="grid gap-4 sm:grid-cols-2" aria-labelledby="sim-client">
          <h3 id="sim-client" class="sm:col-span-2 admin-h2 text-base">Proyecto y cliente</h3>
          <div><label for="sim-job" class="admin-label mb-2">Nombre del proyecto</label><input id="sim-job" v-model="form.job_name" placeholder="Ej. Engranaje de hélice" class="field" data-autofocus></div>
          <div v-if="contacts.length">
            <label for="sim-contact" class="admin-label mb-2">Cliente guardado</label>
            <select id="sim-contact" class="field" @change="selectCustomer"><option value="">Elegir…</option><option v-for="c in contacts" :key="c.id" :value="c.id">{{ c.name }}</option></select>
          </div>
          <div><label for="sim-name" class="admin-label mb-2">Nombre del cliente</label><input id="sim-name" v-model="form.customer_name" class="field"></div>
          <div><label for="sim-phone" class="admin-label mb-2">WhatsApp</label><input id="sim-phone" v-model="form.customer_phone" type="tel" class="field"></div>
          <div class="sm:col-span-2"><label for="sim-email" class="admin-label mb-2">Correo</label><input id="sim-email" v-model="form.customer_email" type="email" class="field"></div>
        </section>

        <section class="grid gap-4 sm:grid-cols-2" aria-labelledby="sim-print">
          <h3 id="sim-print" class="sm:col-span-2 admin-h2 text-base">Producción por cama</h3>
          <p class="sm:col-span-2 -mt-2 text-sm text-muted">Pon el peso y el tiempo de <strong>una cama</strong> (lo que dice el laminador). Si sacas varios pedidos iguales, sube las camas.</p>
          <div class="sm:col-span-2">
            <label for="sim-material" class="admin-label mb-2">Material</label>
            <select id="sim-material" v-model="form.material_id" class="field">
              <option value="">Elegir…</option>
              <option v-for="m in baseMaterials" :key="m.id" :value="m.id">{{ m.name }} · {{ money(m.cost_per_kg) }}/{{ m.unit === 'ml' ? 'L' : 'kg' }}</option>
            </select>
          </div>
          <div><label for="sim-weight" class="admin-label mb-2">Peso por cama (g)</label><input id="sim-weight" v-model.number="form.weight_g" type="number" min="0" step="0.1" class="field tabular-nums"></div>
          <div><label for="sim-time" class="admin-label mb-2">Tiempo por cama (h:mm)</label><input id="sim-time" v-model="form.time_str" placeholder="2:30" inputmode="numeric" class="field tabular-nums"></div>
          <div><label for="sim-beds" class="admin-label mb-2">Camas (lotes)</label><input id="sim-beds" v-model.number="form.beds" type="number" min="1" step="1" class="field tabular-nums"></div>
          <div><label for="sim-pieces" class="admin-label mb-2">Pzs x cama</label><input id="sim-pieces" v-model.number="form.pieces_per_bed" type="number" min="1" step="1" class="field tabular-nums"></div>
          <p class="sm:col-span-2 text-sm text-muted tabular-nums" aria-live="polite">
            Total: <strong class="text-ink">{{ run.pieces }} {{ run.pieces === 1 ? 'pieza' : 'piezas' }}</strong> en {{ run.beds }} {{ run.beds === 1 ? 'cama' : 'camas' }} ·
            {{ run.weightG.toLocaleString('es-CO', { maximumFractionDigits: 1 }) }} g · {{ hoursLabel(run.hours) }}
          </p>
        </section>

        <section aria-labelledby="sim-extras">
          <h3 id="sim-extras" class="admin-h2 text-base">Insumos extra</h3>
          <label for="sim-extra" class="sr-only">Agregar insumo</label>
          <select id="sim-extra" class="mt-3 field" @change="addExtra">
            <option value="">Agregar insumo…</option>
            <option v-for="m in extraMaterials" :key="m.id" :value="m.id">{{ m.name }} · {{ money(m.cost_per_kg) }}/{{ m.unit || 'unidad' }}</option>
          </select>
          <ul v-if="form.extra_items.length" class="mt-3 divide-y divide-line rounded-[var(--radius-control)] bg-surface">
            <li v-for="(item, idx) in form.extra_items" :key="idx" class="flex items-center gap-3 px-3 py-2">
              <span class="flex-1 min-w-0 truncate">{{ item.name }}</span>
              <span class="inline-flex items-center rounded-full border border-line">
                <button type="button" class="w-9 h-9" :aria-label="`Menos ${item.name}`" @click="item.qty = Math.max(1, item.qty - 1)">−</button>
                <span class="w-6 text-center tabular-nums">{{ item.qty }}</span>
                <button type="button" class="w-9 h-9" :aria-label="`Más ${item.name}`" @click="item.qty++">+</button>
              </span>
              <span class="w-24 text-right tabular-nums">{{ money(item.cost * item.qty) }}</span>
              <button type="button" class="icon-btn text-muted hover:text-danger hover:bg-danger/10" :aria-label="`Quitar ${item.name}`" @click="removeExtra(idx)">✕</button>
            </li>
          </ul>
        </section>

        <section class="grid gap-4 grid-cols-2 sm:grid-cols-3" aria-labelledby="sim-margins">
          <h3 id="sim-margins" class="col-span-2 sm:col-span-3 admin-h2 text-base">Márgenes de esta cotización <span class="text-sm font-normal text-muted">(%)</span></h3>
          <div><label for="sim-profit" class="admin-label mb-1">Ganancia</label><input id="sim-profit" v-model.number="form.profit_pct" type="number" min="0" class="field h-10 tabular-nums"></div>
          <div><label for="sim-fail" class="admin-label mb-1">Fallos</label><input id="sim-fail" v-model.number="form.fallos_pct" type="number" min="0" step="0.5" class="field h-10 tabular-nums"></div>
          <div><label for="sim-log" class="admin-label mb-1">Logística</label><input id="sim-log" v-model.number="form.transporte_pct" type="number" min="0" step="0.5" class="field h-10 tabular-nums"></div>
          <div><label for="sim-mkt" class="admin-label mb-1">Marketing</label><input id="sim-mkt" v-model.number="form.marketing_pct" type="number" min="0" step="0.5" class="field h-10 tabular-nums"></div>
          <div><label for="sim-iva" class="admin-label mb-1">IVA</label><input id="sim-iva" v-model.number="form.iva_pct" type="number" min="0" max="100" step="0.5" class="field h-10 tabular-nums"></div>
          <div><label for="sim-disc" class="admin-label mb-1">Descuento</label><input id="sim-disc" v-model.number="form.discount_pct" type="number" min="0" max="100" class="field h-10 tabular-nums"></div>
        </section>

        <div><label for="sim-notes" class="admin-label mb-2">Notas internas</label><textarea id="sim-notes" v-model="form.comments" rows="2" class="field !h-auto py-2.5"></textarea></div>
      </form>

      <!-- Resultado -->
      <aside class="lg:col-span-5 space-y-4 lg:sticky lg:top-0 self-start" aria-live="polite">
        <section class="admin-card">
          <p class="admin-label">Total</p>
          <p class="mt-1 text-4xl font-extrabold tabular-nums">{{ money(result.total) }}</p>
          <dl class="mt-4 grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
            <div><dt class="text-muted">Por cama</dt><dd class="font-semibold tabular-nums">{{ money(result.bed_price) }}</dd></div>
            <div><dt class="text-muted">Por pieza</dt><dd class="font-semibold tabular-nums">{{ money(result.unit_price) }}</dd></div>
            <div><dt class="text-muted">Margen</dt><dd class="font-semibold tabular-nums">{{ result.profit_margin_pct.toFixed(1) }} %</dd></div>
            <div><dt class="text-muted">Costo / hora</dt><dd class="font-semibold tabular-nums">{{ money(result.production / (result.total_hours || 1)) }}</dd></div>
          </dl>
          <div v-if="result.total > 0" class="mt-4 flex h-2.5 rounded-full overflow-hidden bg-surface-2" aria-hidden="true">
            <div class="bg-[#3fcf72]" :style="{ width: pct(result.material) + '%' }"></div>
            <div class="bg-[#8dd6ff]" :style="{ width: pct(result.luz + result.labor + result.depr + result.mant + result.etiquetas + result.extras) + '%' }"></div>
            <div class="bg-[#fbbf24]" :style="{ width: pct(result.logistics + result.marketing + result.failures) + '%' }"></div>
            <div class="bg-[#08872b]" :style="{ width: pct(result.profit) + '%' }"></div>
          </div>
          <p v-if="result.total > 0" class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted">
            <span><span class="inline-block w-2 h-2 rounded-full bg-[#3fcf72] mr-1"></span>Material</span>
            <span><span class="inline-block w-2 h-2 rounded-full bg-[#8dd6ff] mr-1"></span>Producción</span>
            <span><span class="inline-block w-2 h-2 rounded-full bg-[#fbbf24] mr-1"></span>Operación</span>
            <span><span class="inline-block w-2 h-2 rounded-full bg-[#08872b] mr-1"></span>Ganancia</span>
          </p>
        </section>

        <section class="admin-card text-sm" aria-label="Desglose">
          <dl class="space-y-1.5">
            <div class="flex justify-between"><dt class="text-muted">Material</dt><dd class="tabular-nums">{{ money(result.material) }}</dd></div>
            <div class="flex justify-between"><dt class="text-muted">Energía <span class="opacity-70">(carga {{ ((settings.infra?.load_factor ?? 0.4) * 100).toFixed(0) }} %)</span></dt><dd class="tabular-nums">{{ money(result.luz) }}</dd></div>
            <div class="flex justify-between"><dt class="text-muted">Mano de obra</dt><dd class="tabular-nums">{{ money(result.labor) }}</dd></div>
            <div class="flex justify-between"><dt class="text-muted">Depreciación</dt><dd class="tabular-nums">{{ money(result.depr) }}</dd></div>
            <div class="flex justify-between"><dt class="text-muted">Mantenimiento</dt><dd class="tabular-nums">{{ money(result.mant) }}</dd></div>
            <div class="flex justify-between"><dt class="text-muted">Empaque</dt><dd class="tabular-nums">{{ money(result.etiquetas) }}</dd></div>
            <div class="flex justify-between"><dt class="text-muted">Insumos extra</dt><dd class="tabular-nums">{{ money(result.extras) }}</dd></div>
            <div class="flex justify-between pt-2 border-t border-line"><dt class="text-muted">Logística ({{ form.transporte_pct }} %)</dt><dd class="tabular-nums">{{ money(result.logistics) }}</dd></div>
            <div class="flex justify-between"><dt class="text-muted">Marketing ({{ form.marketing_pct }} %)</dt><dd class="tabular-nums">{{ money(result.marketing) }}</dd></div>
            <div class="flex justify-between"><dt class="text-muted">Fallos ({{ form.fallos_pct }} %)</dt><dd class="tabular-nums">{{ money(result.failures) }}</dd></div>
            <div class="flex justify-between"><dt class="text-muted">Ganancia ({{ form.profit_pct }} %)</dt><dd class="tabular-nums">{{ money(result.profit) }}</dd></div>
            <div v-if="result.discount > 0" class="flex justify-between text-success"><dt>Descuento ({{ form.discount_pct }} %)</dt><dd class="tabular-nums">−{{ money(result.discount) }}</dd></div>
            <div class="flex justify-between pt-2 border-t border-line"><dt class="text-muted">Subtotal</dt><dd class="tabular-nums">{{ money(result.subtotal) }}</dd></div>
            <div class="flex justify-between"><dt class="text-muted">IVA ({{ form.iva_pct }} %)</dt><dd class="tabular-nums">{{ money(result.iva) }}</dd></div>
            <div class="flex justify-between pt-2 border-t border-line font-semibold text-base"><dt>Total</dt><dd class="tabular-nums">{{ money(result.total) }}</dd></div>
          </dl>
          <p v-if="form.discount_pct >= 100" role="alert" class="mt-3 text-danger font-semibold">Un descuento del 100 % deja el pedido en $0.</p>
        </section>
      </aside>
    </div>
    <template #footer>
      <button type="button" class="btn-outlined" @click="$emit('close')">Cerrar</button>
      <button type="button" class="btn-outlined" :disabled="!form.material_id" @click="emitFormData('download-pdf')">Descargar cotización</button>
      <button type="button" class="btn-primary" :disabled="!canConvert" :title="canConvert ? '' : 'Falta material, precio o nombre del cliente'" @click="emitFormData('create-order')">Convertir en pedido</button>
    </template>
  </AdminModal>
</template>

<script setup lang="ts">
import { reactive, computed, watch } from 'vue'
import AdminModal from './AdminModal.vue'
import { calcProductionCost, calcFinalPrice } from '../../services/costCalculator'

const props = withDefaults(defineProps<{
  visible: boolean
  settings?: Record<string, any>
  materials?: Array<any>
  contacts?: Array<any>
}>(), {
  visible: false,
  settings: () => ({ infra: {}, prep: {}, oper: {}, margin: {} }),
  materials: () => [],
  contacts: () => []
})

const emit = defineEmits(['close', 'create-order', 'download-pdf'])

const form = reactive({
  job_name: '', customer_id: '', customer_name: '', customer_company: '',
  customer_id_document: '', customer_email: '', customer_phone: '',
  shipping_address: '', shipping_city: '', shipping_zip: '', shipping_reference: '',
  // Peso y tiempo son de UNA cama; camas × piezas por cama = piezas del pedido
  material_id: '', weight_g: 0, time_str: '0:00', beds: 1, pieces_per_bed: 1,
  profit_pct: props.settings?.oper?.ganancia ?? 20,
  discount_pct: 0,
  transporte_pct: props.settings?.oper?.transporte ?? 5,
  marketing_pct: props.settings?.oper?.marketing ?? 10,
  fallos_pct: props.settings?.oper?.fallos ?? 5,
  iva_pct: props.settings?.margin?.iva ?? 19,
  extra_items: [] as Array<{ id: string; name: string; cost: number; unit: string; qty: number }>, comments: '',
})

const extraMaterials = computed(() =>
  props.materials.filter(m => m.type !== 'material')
)
// Material base: solo filamentos y resinas (antes listaba también insumos)
const baseMaterials = computed(() => props.materials.filter(m => !m.type || m.type === 'material'))

// Los % por defecto se leían una sola vez, a veces antes de cargar los ajustes:
// se toman de Parámetros cada vez que se abre
watch(() => props.visible, (open) => {
  if (!open) return
  form.profit_pct = props.settings?.oper?.ganancia ?? 20
  form.transporte_pct = props.settings?.oper?.transporte ?? 5
  form.marketing_pct = props.settings?.oper?.marketing ?? 10
  form.fallos_pct = props.settings?.oper?.fallos ?? 5
  form.iva_pct = props.settings?.margin?.iva ?? 19
})
const parseHours = (str: string) => {
  const [h, m] = String(str || '').split(':').map(Number)
  return (h || 0) + (m || 0) / 60
}
const hoursLabel = (hours: number) => {
  const total = Math.round(hours * 60)
  return `${Math.floor(total / 60)}:${String(total % 60).padStart(2, '0')} h`
}
const wholeAtLeastOne = (n: unknown) => Math.max(1, Math.floor(Number(n) || 1))

/** La corrida completa: lo de una cama multiplicado por las camas. */
const run = computed(() => {
  const beds = wholeAtLeastOne(form.beds)
  const perBed = wholeAtLeastOne(form.pieces_per_bed)
  return {
    beds,
    pieces: beds * perBed,
    weightG: (Number(form.weight_g) || 0) * beds,
    hours: parseHours(form.time_str) * beds,
  }
})

const money = (n: any) => '$' + Math.round(Number(n) || 0).toLocaleString('es-CO')
const pct = (part: number) => result.value.total > 0 ? Math.max(0, (part / result.value.total) * 100) : 0
const canConvert = computed(() => !!form.material_id && result.value.total > 0 && !!form.customer_name.trim())

const result = computed(() => {
  const mat = props.materials.find(m => m.id === form.material_id)
  if (!mat || !props.settings.infra) return {
    material: 0, luz: 0, labor: 0, depr: 0, mant: 0, etiquetas: 0, extras: 0,
    production: 0, logistics: 0, marketing: 0, failures: 0, profit: 0,
    subtotal: 0, iva: 0, total: 0, unit_price: 0, bed_price: 0, discount: 0,
    profit_margin_pct: 0, total_hours: 0,
    isSafetyAlert: false, pcts: { material: 0, luz: 0, labor: 0, depr: 0, mant: 0, etiquetas: 0, extras: 0 }
  }

  const { pieces: qty, beds, weightG, hours: totalHours } = run.value
  const extrasCost = form.extra_items.reduce((acc, item) => acc + item.cost * item.qty, 0)

  const prod = calcProductionCost({
    weightG, totalHours, costPerKg: mat.cost_per_kg,
    infra: props.settings.infra, prep: props.settings.prep, extrasCost,
  })

  const price = calcFinalPrice({
    productionCost: prod.total,
    oper: { transporte: form.transporte_pct, marketing: form.marketing_pct, fallos: form.fallos_pct, ganancia: form.profit_pct },
    margin: { iva: props.settings.margin?.iva || 0 },
    overrides: { discountPct: form.discount_pct, ivaPct: form.iva_pct },
  })

  return {
    material: prod.material, luz: prod.luz, labor: prod.labor,
    depr: prod.depr, mant: prod.mant, etiquetas: prod.etiquetas,
    extras: prod.extras, production: prod.total,
    logistics: price.logistics, marketing: price.marketing,
    failures: price.failures, profit: price.profit,
    discount: price.discount, subtotal: price.subtotal,
    iva: price.iva, total: price.total,
    unit_price: Math.round(price.total / qty),
    bed_price: Math.round(price.total / beds),
    total_hours: totalHours, profit_margin_pct: price.profitMarginPct,
    isSafetyAlert: form.discount_pct === 100, pcts: prod.pcts,
  }
})

const selectCustomer = (e: any) => {
  const c = props.contacts.find(c => String(c.id) === String(e.target.value))
  if (c) {
    form.customer_id = c.id; form.customer_name = c.name
    form.customer_company = c.company || ''; form.customer_id_document = c.customer_id_document || ''
    form.customer_email = c.email || ''; form.customer_phone = c.phone || ''
    form.shipping_address = c.address_full || c.location || ''
    form.shipping_city = c.city_dept_country || ''; form.shipping_zip = c.zip_code || ''
    form.shipping_reference = c.location_reference || ''
    if (c.notes) form.comments = c.notes
  }
}

const emitFormData = (action: 'download-pdf' | 'create-order') => {
  const extrasCost = form.extra_items.reduce((acc, item) => acc + item.cost * item.qty, 0)

  // El pedido lleva los totales de la corrida (todas las camas)
  const payload = {
    job_name: form.job_name,
    material_id: form.material_id,
    weight_g: run.value.weightG,
    time_str: hoursLabel(run.value.hours).replace(' h', ''),
    total_hours: run.value.hours,
    total_price: result.value.total,
    pieces_per_batch: run.value.pieces,
    beds: run.value.beds,
    pieces_per_bed: wholeAtLeastOne(form.pieces_per_bed),
    bed_price: result.value.bed_price,
    profit_pct: form.profit_pct,
    discount_pct: form.discount_pct,
    extra_items: form.extra_items,
    extras_cost: extrasCost,
    comments: form.comments,
    customer_id: form.customer_id,
    customer_name: form.customer_name,
    customer_company: form.customer_company,
    customer_id_document: form.customer_id_document,
    customer_email: form.customer_email,
    customer_phone: form.customer_phone,
    shipping_address: form.shipping_address,
    shipping_city: form.shipping_city,
    shipping_zip: form.shipping_zip,
    shipping_reference: form.shipping_reference,
    transportation_pct: form.transporte_pct,
    marketing_pct: form.marketing_pct,
    failures_pct: form.fallos_pct,
  }
  emit(action, payload)
}

const addExtra = (e: any) => {
  const matId = e.target.value
  if (!matId) return
  const item = props.materials.find(m => m.id === matId)
  if (item) {
    const existing = form.extra_items.find(i => i.id === item.id)
    if (existing) existing.qty++
    else form.extra_items.push({ id: item.id, name: item.name, cost: item.cost_per_kg, unit: item.unit, qty: 1 })
  }
  e.target.value = ''
}

const removeExtra = (idx: number) => { form.extra_items.splice(idx, 1) }
</script>
