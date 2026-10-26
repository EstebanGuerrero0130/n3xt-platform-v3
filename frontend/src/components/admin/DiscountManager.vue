<script setup lang="ts">
/**
 * Cupones de descuento. Se guardan en el setting `coupons` con el formato que lee
 * el cotizador (QuotePricingService::findCoupon): { code, discount_pct, label,
 * active, max_uses }. Los usos los cuenta el servidor (`coupon_uses`).
 */
import { ref, computed, watch, type PropType } from 'vue'

interface Coupon {
  code: string
  discount_pct: number
  label?: string
  active: boolean
  max_uses?: number | null
}

const props = defineProps({
  coupons: { type: Array as PropType<Coupon[]>, default: () => [] },
  uses: { type: Object as PropType<Record<string, number>>, default: () => ({}) },
  welcomePct: { type: Number, default: 10 },
  saving: { type: Boolean, default: false },
})

const emit = defineEmits<{ (e: 'save', payload: { coupons: Coupon[]; welcomePct: number }): void }>()

const CODE_RE = /^[A-Z0-9-]{3,32}$/
const emptyForm = (): Coupon => ({ code: '', discount_pct: 10, label: '', active: true, max_uses: null })

const showForm = ref(false)
const form = ref<Coupon>(emptyForm())
const formError = ref('')
const welcome = ref(props.welcomePct)
watch(() => props.welcomePct, v => { welcome.value = v })

const usesOf = (code: string) => props.uses[code.toUpperCase()] ?? 0
const exhausted = (c: Coupon) => !!c.max_uses && usesOf(c.code) >= c.max_uses
const activeCount = computed(() => props.coupons.filter(c => c.active && !exhausted(c)).length)

const generateCode = () => {
  const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'
  form.value.code = 'N3XT-' + Array.from({ length: 6 }, () => chars[Math.floor(Math.random() * chars.length)]).join('')
}

const emitSave = (coupons: Coupon[]) => emit('save', { coupons, welcomePct: welcome.value })

const addCoupon = () => {
  formError.value = ''
  const code = form.value.code.toUpperCase().trim()
  const pct = Number(form.value.discount_pct)
  if (!CODE_RE.test(code)) {
    formError.value = 'El código lleva de 3 a 32 letras, números o guiones (sin espacios).'
    return
  }
  if (!(pct > 0 && pct <= 100)) {
    formError.value = 'El descuento va de 1 a 100 %.'
    return
  }
  if (code.startsWith('BIENVENIDA')) {
    formError.value = 'Los códigos BIENVENIDA… están reservados para los cupones de bienvenida.'
    return
  }
  if (props.coupons.some(c => c.code.toUpperCase() === code)) {
    formError.value = 'Ya existe un cupón con ese código.'
    return
  }
  const max = Number(form.value.max_uses)
  emitSave([...props.coupons, {
    code,
    discount_pct: pct,
    label: (form.value.label || '').trim(),
    active: true,
    max_uses: max > 0 ? Math.floor(max) : null,
  }])
  form.value = emptyForm()
  showForm.value = false
}

const toggleActive = (idx: number) => {
  emitSave(props.coupons.map((c, i) => i === idx ? { ...c, active: !c.active } : c))
}

const removeCoupon = (idx: number) => {
  if (!confirm(`¿Eliminar el cupón ${props.coupons[idx].code}? Los pedidos que ya lo usaron no cambian.`)) return
  emitSave(props.coupons.filter((_, i) => i !== idx))
}

const saveWelcome = () => {
  const pct = Number(welcome.value)
  if (!(pct > 0 && pct <= 50)) {
    welcome.value = props.welcomePct
    return
  }
  emitSave(props.coupons)
}
</script>

<template>
  <div class="space-y-8">
    <header class="flex flex-col md:flex-row md:items-end md:justify-between gap-6">
      <div>
        <h1 class="admin-title">Cupones <span class="text-brand">de descuento</span></h1>
        <p class="admin-lead">Códigos que el cliente escribe en el cotizador. El descuento se calcula en el servidor.</p>
      </div>
      <button type="button" class="btn-primary btn-lg" :disabled="saving" @click="showForm = !showForm; generateCode()">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v14m-7-7h14"/></svg>
        Nuevo cupón
      </button>
    </header>

    <!-- Nuevo cupón -->
    <form v-if="showForm" class="admin-card grid gap-5 md:grid-cols-12 md:items-end" novalidate @submit.prevent="addCoupon">
      <div class="md:col-span-4">
        <label for="coupon-code" class="admin-label mb-2">Código</label>
        <div class="flex gap-2">
          <input id="coupon-code" v-model="form.code" type="text" class="field uppercase font-mono tracking-wide" maxlength="32" autocomplete="off" required>
          <button type="button" class="btn-outlined shrink-0" title="Generar otro código" @click="generateCode">Generar</button>
        </div>
      </div>
      <div class="md:col-span-2">
        <label for="coupon-pct" class="admin-label mb-2">Descuento (%)</label>
        <input id="coupon-pct" v-model.number="form.discount_pct" type="number" min="1" max="100" class="field tabular-nums" required>
      </div>
      <div class="md:col-span-2">
        <label for="coupon-max" class="admin-label mb-2">Máximo de usos</label>
        <input id="coupon-max" v-model.number="form.max_uses" type="number" min="1" placeholder="Sin límite" class="field tabular-nums">
      </div>
      <div class="md:col-span-4">
        <label for="coupon-label" class="admin-label mb-2">Nombre interno <span class="opacity-70">(opcional)</span></label>
        <input id="coupon-label" v-model="form.label" type="text" maxlength="60" placeholder="Ej. Feria Bogotá" class="field">
      </div>
      <p v-if="formError" role="alert" class="md:col-span-12 text-sm text-danger">{{ formError }}</p>
      <div class="md:col-span-12 flex flex-wrap gap-3">
        <button type="submit" class="btn-primary" :disabled="saving">{{ saving ? 'Guardando…' : 'Crear cupón' }}</button>
        <button type="button" class="btn-outlined" @click="showForm = false; formError = ''">Cancelar</button>
      </div>
    </form>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-12 items-start">
      <!-- Lista -->
      <section class="admin-card lg:col-span-8" aria-labelledby="coupons-title">
        <div class="flex items-baseline justify-between gap-4 mb-4">
          <h2 id="coupons-title" class="admin-h2">Cupones</h2>
          <p class="text-sm text-muted">{{ activeCount }} activos de {{ coupons.length }}</p>
        </div>

        <p v-if="!coupons.length" class="py-8 text-center text-muted">Aún no hay cupones. Crea el primero con «Nuevo cupón».</p>

        <div v-else class="overflow-x-auto -mx-1 px-1">
          <table class="admin-table min-w-[36rem]">
            <thead>
              <tr><th>Código</th><th>Descuento</th><th>Usos</th><th>Estado</th><th class="sr-only">Acciones</th></tr>
            </thead>
            <tbody>
              <tr v-for="(c, idx) in coupons" :key="c.code">
                <td>
                  <span class="font-mono font-semibold">{{ c.code }}</span>
                  <span v-if="c.label" class="block text-sm text-muted">{{ c.label }}</span>
                </td>
                <td class="tabular-nums font-semibold">{{ c.discount_pct }} %</td>
                <td class="tabular-nums">{{ usesOf(c.code) }}<span v-if="c.max_uses" class="text-muted"> / {{ c.max_uses }}</span></td>
                <td>
                  <span v-if="exhausted(c)" class="badge bg-surface-2 text-muted">Agotado</span>
                  <span v-else-if="c.active" class="badge bg-success/10 text-success">Activo</span>
                  <span v-else class="badge bg-surface-2 text-muted">Pausado</span>
                </td>
                <td class="text-right whitespace-nowrap">
                  <button type="button" class="btn-outlined btn-sm" :disabled="saving" @click="toggleActive(idx)">{{ c.active ? 'Pausar' : 'Activar' }}</button>
                  <button type="button" class="ml-2 btn-sm inline-flex items-center rounded-full px-3 text-danger hover:bg-danger/10" :disabled="saving" :aria-label="`Eliminar ${c.code}`" @click="removeCoupon(idx)">Eliminar</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <!-- Cupón de bienvenida -->
      <section class="admin-card lg:col-span-4" aria-labelledby="welcome-title">
        <h2 id="welcome-title" class="admin-h2">Cupón de bienvenida</h2>
        <p class="mt-2 text-sm text-muted">Cada cliente que verifica su correo recibe un código personal (BIENVENIDA…) de un solo uso, válido solo con su correo.</p>
        <form class="mt-5" @submit.prevent="saveWelcome">
          <label for="welcome-pct" class="admin-label mb-2">Descuento (%)</label>
          <div class="flex gap-2">
            <input id="welcome-pct" v-model.number="welcome" type="number" min="1" max="50" class="field tabular-nums max-w-[8rem]">
            <button type="submit" class="btn-primary" :disabled="saving || welcome === welcomePct">Guardar</button>
          </div>
          <p class="mt-2 text-sm text-muted">Entre 1 y 50 %. Aplica a todos los cupones de bienvenida que aún no se han usado.</p>
        </form>
      </section>
    </div>
  </div>
</template>
