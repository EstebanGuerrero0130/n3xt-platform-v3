<template>
  <div class="space-y-8">
    <header class="flex flex-col md:flex-row md:items-end md:justify-between gap-6">
      <div>
        <h1 class="admin-title">Parámetros <span class="text-brand">de precio</span></h1>
        <p class="admin-lead">Con estos valores el servidor calcula el precio de cada cotización. Los pedidos ya creados guardan los valores de su momento.</p>
      </div>
      <button type="button" class="btn-primary btn-lg" :disabled="savingSettings" @click="saveSettings()">
        {{ savingSettings ? 'Guardando…' : 'Guardar parámetros' }}
      </button>
    </header>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-12 items-start">
      <div class="xl:col-span-8 space-y-6">
        <!-- Costos de máquina -->
        <section class="admin-card" aria-labelledby="set-machine">
          <h2 id="set-machine" class="admin-h2">Costos de máquina</h2>
          <div class="mt-5 grid gap-5 sm:grid-cols-2">
            <div v-for="f in MACHINE_COSTS" :key="f.key">
              <label :for="`set-${f.key}`" class="admin-label mb-2">{{ f.label }} <span class="opacity-70">({{ f.unit }})</span></label>
              <input :id="`set-${f.key}`" v-model.number="localSettings.infra[f.key]" type="number" min="0" step="any" class="field tabular-nums" :aria-describedby="`set-${f.key}-help`">
              <p :id="`set-${f.key}-help`" class="mt-1.5 text-sm text-muted">{{ f.help }} {{ defaultSuggestions[f.key] }}.</p>
            </div>
            <div class="sm:col-span-2">
              <label for="set-load" class="admin-label mb-2">Factor de carga eléctrica <span class="opacity-70">(0 a 1)</span></label>
              <div class="flex items-center gap-4">
                <input id="set-load" v-model.number="localSettings.infra.load_factor" type="number" step="0.05" min="0" max="1" class="field tabular-nums max-w-[8rem]" aria-describedby="set-load-help">
                <div class="flex-1 h-2 rounded-full bg-surface-2 overflow-hidden" aria-hidden="true"><div class="h-full rounded-full bg-primary" :style="{ width: Math.min(Math.max((localSettings.infra.load_factor || 0) * 100, 0), 100) + '%' }"></div></div>
              </div>
              <p id="set-load-help" class="mt-1.5 text-sm text-muted">Qué parte del tiempo la máquina consume a plena potencia. 0,4 es un valor típico.</p>
            </div>
          </div>
        </section>

        <!-- Mano de obra -->
        <section class="admin-card" aria-labelledby="set-labor">
          <h2 id="set-labor" class="admin-h2">Mano de obra</h2>
          <div class="mt-5 grid gap-5 sm:grid-cols-2">
            <div>
              <label for="set-labor-hr" class="admin-label mb-2">Valor de la hora <span class="opacity-70">($)</span></label>
              <input id="set-labor-hr" v-model.number="localSettings.prep.mano_obra_hr" type="number" min="0" step="any" class="field tabular-nums">
              <p class="mt-1.5 text-sm text-muted">{{ defaultSuggestions.mano_obra_hr }}.</p>
            </div>
            <div>
              <label for="set-prep" class="admin-label mb-2">Tiempo de preparación y acabado <span class="opacity-70">(% del tiempo de impresión)</span></label>
              <input id="set-prep" v-model.number="localSettings.prep.prep_time_pct" type="number" min="0" max="100" class="field tabular-nums">
              <p class="mt-1.5 text-sm text-muted">Laminado, retirar soportes, limpieza. {{ defaultSuggestions.prep_time_pct }}.</p>
            </div>
          </div>
        </section>

        <!-- Márgenes -->
        <section class="admin-card" aria-labelledby="set-margins">
          <h2 id="set-margins" class="admin-h2">Márgenes e impuestos <span class="text-base font-normal text-muted">(%)</span></h2>
          <div class="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            <div v-for="f in MARGINS" :key="f.key">
              <label :for="`set-${f.key}`" class="admin-label mb-2">{{ f.label }}</label>
              <input :id="`set-${f.key}`" v-model.number="localSettings[f.group][f.key]" type="number" min="0" max="500" step="0.5" class="field tabular-nums">
              <p class="mt-1.5 text-sm text-muted">{{ f.help }}</p>
            </div>
          </div>
        </section>

        <!-- Reglas comerciales -->
        <section class="admin-card" aria-labelledby="set-rules">
          <h2 id="set-rules" class="admin-h2">Reglas comerciales</h2>
          <p class="mt-1 text-sm text-muted">Se aplican en el cotizador y en el servidor. En 0 no cambian el precio.</p>
          <div class="mt-5 grid gap-5 sm:grid-cols-2">
            <div v-for="f in RULE_FIELDS" :key="f.key">
              <label :for="`set-rule-${f.key}`" class="admin-label mb-2">{{ f.label }} <span class="opacity-70">({{ f.unit }})</span></label>
              <input :id="`set-rule-${f.key}`" v-model.number="localSettings.rules[f.key]" type="number" min="0" :step="f.step" class="field tabular-nums" :aria-describedby="`set-rule-${f.key}-help`">
              <p :id="`set-rule-${f.key}-help`" class="mt-1.5 text-sm text-muted">{{ f.help }}</p>
            </div>
          </div>

          <fieldset class="mt-6">
            <legend class="admin-label">Descuento por volumen</legend>
            <p class="mt-1 text-sm text-muted">Según el total de piezas del pedido, antes del cupón. Cupón y volumen juntos nunca pasan del 50 %.</p>
            <ul v-if="localSettings.rules.volume_tiers.length" class="mt-3 space-y-2">
              <li v-for="(t, i) in localSettings.rules.volume_tiers" :key="i" class="flex flex-wrap items-end gap-3">
                <div>
                  <label :for="`set-tier-qty-${i}`" class="admin-label mb-1">Desde (piezas)</label>
                  <input :id="`set-tier-qty-${i}`" v-model.number="t.min_qty" type="number" min="1" step="1" class="field h-10 w-32 tabular-nums">
                </div>
                <div>
                  <label :for="`set-tier-pct-${i}`" class="admin-label mb-1">Descuento (%)</label>
                  <input :id="`set-tier-pct-${i}`" v-model.number="t.discount_pct" type="number" min="0" max="50" step="0.5" class="field h-10 w-32 tabular-nums">
                </div>
                <button type="button" class="btn-outlined btn-sm" :aria-label="`Quitar el tramo ${Number(i) + 1}`" @click="removeTier(Number(i))">Quitar</button>
              </li>
            </ul>
            <button type="button" class="mt-3 btn-outlined btn-sm" @click="addTier">Añadir tramo</button>
          </fieldset>
        </section>
      </div>

      <div class="xl:col-span-4 space-y-6">
        <!-- Empresa -->
        <section class="admin-card" aria-labelledby="set-company">
          <h2 id="set-company" class="admin-h2">Datos de la empresa</h2>
          <p class="mt-1 text-sm text-muted">Aparecen en cotizaciones, rótulos y reportes en PDF.</p>
          <div class="mt-5 flex items-center gap-4">
            <div class="w-16 h-16 shrink-0 rounded-[var(--radius-control)] bg-white overflow-hidden flex items-center justify-center ring-1 ring-line">
              <img v-if="logoUrl && logoUrl !== '/logo.png'" :src="logoUrl" alt="Logo actual" class="w-full h-full object-contain" @error="(e) => { if (e.target) (e.target as HTMLImageElement).style.display = 'none' }">
              <span v-else class="font-display font-black text-lg text-[#101814]">N3XT</span>
            </div>
            <input ref="logoInput" type="file" accept="image/png,image/jpeg,image/webp" class="hidden" @change="handleLogoUpload">
            <button type="button" class="btn-outlined btn-sm" :disabled="uploadingLogo" @click="($refs.logoInput as HTMLInputElement).click()">{{ uploadingLogo ? 'Subiendo…' : 'Cambiar logo' }}</button>
          </div>
          <div class="mt-5 space-y-4">
            <div v-for="f in COMPANY_FIELDS" :key="f.key">
              <label :for="`set-company-${f.key}`" class="admin-label mb-2">{{ f.label }}</label>
              <input :id="`set-company-${f.key}`" v-model="localSettings.company[f.key]" :type="f.type" class="field">
            </div>
          </div>
        </section>

        <!-- Copia de seguridad -->
        <section class="admin-card" aria-labelledby="set-backup">
          <h2 id="set-backup" class="admin-h2">Copia de seguridad</h2>
          <p class="mt-2 text-sm text-muted">Descarga un archivo con pedidos, inventario, clientes, máquinas y ajustes. No incluye contraseñas.</p>
          <button type="button" class="mt-4 btn-outlined w-full" :disabled="downloadingBackup" @click="downloadBackup">{{ downloadingBackup ? 'Preparando…' : 'Descargar copia' }}</button>
        </section>

        <!-- Zona de riesgo -->
        <section class="admin-card border border-danger/40" aria-labelledby="set-danger">
          <h2 id="set-danger" class="admin-h2 text-danger">Borrar todos los datos</h2>
          <p class="mt-2 text-sm text-muted">Elimina pedidos, materiales, inventario, clientes y máquinas. No se puede deshacer: descarga antes una copia de seguridad.</p>
          <form class="mt-4" @submit.prevent="handlePurgeAll">
            <label for="purge-confirm" class="admin-label mb-2">Escribe <strong class="text-ink">{{ PURGE_PHRASE }}</strong> para habilitar el botón</label>
            <input id="purge-confirm" v-model="purgeText" type="text" autocomplete="off" class="field">
            <button type="submit" class="mt-3 btn-danger w-full" :disabled="purgeText.trim().toUpperCase() !== PURGE_PHRASE">Borrar todo</button>
          </form>
        </section>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, watch } from 'vue'
import { api } from '../../services/api'

const props = defineProps({
  settings: { type: Object, required: true },
  savingSettings: { type: Boolean, default: false },
  uploadingLogo: { type: Boolean, default: false },
  logoUrl: { type: String, default: '/logo.png' },
  logoError: { type: Boolean, default: false },
  isLocalhost: { type: Boolean, default: false },
  showNotify: { type: Function, default: null },
  askConfirm: { type: Function, default: null },
})

const emit = defineEmits([
  'save-settings',
  'logo-upload',
  'purge-all',
  'catalog-create',
  'catalog-reset',
  'scroll-catalog-item',
])


// Valores sugeridos por defecto para orientar al usuario
const defaultSuggestions: Record<string, string> = {
  luz_hr: 'Sugerido: 926',
  depr_hr: 'Sugerido: 400',
  mant_hr: 'Sugerido: 700',
  etiquetas: 'Sugerido: 500',
  mano_obra_hr: 'Sugerido: 1,000',
  prep_time_pct: 'Sugerido: 10',
  ganancia: 'Sugerido: 20-50%',
  fallos: 'Sugerido: 5',
  iva: 'Sugerido: 19',
  transporte: 'Sugerido: 5',
  marketing: 'Sugerido: 10',
}

// Valores sugeridos para rellenar campos vacíos
const suggestedDefaults: Record<string, Record<string, number>> = {
  infra: { luz_hr: 926, depr_hr: 400, mant_hr: 700, etiquetas: 500 },
  prep: { mano_obra_hr: 1000 },
  oper: { transporte: 5, ganancia: 20, marketing: 10, fallos: 5 },
  margin: { iva: 19 },
}

const emptyRules = () => ({ min_order_total: 0, volume_tiers: [] as { min_qty: number; discount_pct: number }[], round_to: 0, labor_per_piece_min: 0, rush_pct: 0 })

const applyDefaults = (target: any) => {
  // Reglas comerciales: sin configurar no cambian el precio
  target.rules = { ...emptyRules(), ...(target.rules && typeof target.rules === 'object' ? target.rules : {}) }
  if (!Array.isArray(target.rules.volume_tiers)) target.rules.volume_tiers = []
  Object.keys(suggestedDefaults).forEach(category => {
    if (!target[category]) return
    Object.keys(suggestedDefaults[category]).forEach(key => {
      // 0 es un valor válido (IVA 0 %, sin marketing): solo se rellenan campos vacíos
      if (target[category][key] === null || target[category][key] === undefined || target[category][key] === '') {
        target[category][key] = suggestedDefaults[category][key]
      }
    })
  })
}

// Create a local editable copy of settings to avoid prop mutations
const localSettings = reactive(JSON.parse(JSON.stringify(props.settings)))
applyDefaults(localSettings)

// Sync parent prop changes back to local copy
watch(() => props.settings, (newVal) => {
  if (newVal) {
    const fresh = JSON.parse(JSON.stringify(newVal))
    Object.keys(fresh).forEach(key => {
      localSettings[key] = fresh[key]
    })
    applyDefaults(localSettings)
  }
}, { deep: true, immediate: true })

// Descuentos por volumen: filas editables (las vacías o inválidas las descarta el servidor)
const addTier = () => {
  const tiers = localSettings.rules.volume_tiers
  const last = tiers[tiers.length - 1]
  tiers.push({ min_qty: last ? Number(last.min_qty || 0) * 2 || 10 : 10, discount_pct: last ? Number(last.discount_pct || 0) + 5 : 5 })
}
const removeTier = (i: number) => localSettings.rules.volume_tiers.splice(i, 1)

const RULE_FIELDS = [
  { key: 'min_order_total', label: 'Pedido mínimo', unit: '$ antes de IVA', step: '1000', help: 'Ningún pedido baja de este valor. 0 = sin mínimo.' },
  { key: 'round_to', label: 'Redondear precio unitario a', unit: '$', step: '100', help: 'Hacia arriba, por ejemplo a 500 o 1.000. 0 = sin redondeo.' },
  { key: 'labor_per_piece_min', label: 'Posproceso por pieza', unit: 'minutos', step: '1', help: 'Retirar soportes, lijar o limpiar; se cobra al valor de la hora de mano de obra.' },
  { key: 'rush_pct', label: 'Recargo por urgencia', unit: '%', step: '5', help: 'Con más de 0, el cliente ve la opción «Urgente» en el cotizador.' },
]

const saveSettings = (silent = false) => {
  // Pass the local settings data so the parent can sync
  emit('save-settings', silent, JSON.parse(JSON.stringify(localSettings)))
}

const handleLogoUpload = (event: Event) => {
  emit('logo-upload', event)
}

// Copia de seguridad: JSON con todas las tablas (sin contraseñas ni códigos)
const downloadingBackup = ref(false)
const downloadBackup = async () => {
  downloadingBackup.value = true
  try {
    const res = await fetch(`${api.baseUrl}/admin/backup/export`, { credentials: 'include', headers: { Accept: 'application/json' } })
    if (!res.ok) throw new Error(`HTTP ${res.status}`)
    const url = URL.createObjectURL(await res.blob())
    const a = document.createElement('a')
    a.href = url
    a.download = `n3xt_copia_${new Date().toISOString().slice(0, 10)}.json`
    a.click()
    setTimeout(() => URL.revokeObjectURL(url), 1000)
  } catch (err: any) {
    props.showNotify?.('No se pudo descargar la copia: ' + err.message, 'error')
  } finally {
    downloadingBackup.value = false
  }
}

// Purga total: además de la confirmación del panel, hay que escribir la frase
const PURGE_PHRASE = 'BORRAR TODO'
const purgeText = ref('')
const handlePurgeAll = () => {
  if (purgeText.value.trim().toUpperCase() !== PURGE_PHRASE) return
  purgeText.value = ''
  emit('purge-all')
}

const MACHINE_COSTS = [
  { key: 'luz_hr', label: 'Energía', unit: '$ / hora', help: 'Consumo eléctrico de una máquina por hora de impresión.' },
  { key: 'depr_hr', label: 'Depreciación', unit: '$ / hora', help: 'Lo que pierde de valor la máquina por hora de uso.' },
  { key: 'mant_hr', label: 'Mantenimiento', unit: '$ / hora', help: 'Repuestos y revisiones repartidos por hora.' },
  { key: 'etiquetas', label: 'Empaque y etiquetas', unit: '$ / pedido', help: 'Costo fijo de embalaje de cada pedido.' },
]
const MARGINS = [
  { group: 'oper', key: 'ganancia', label: 'Ganancia', help: 'Sobre el costo de producción.' },
  { group: 'oper', key: 'fallos', label: 'Reserva por fallos', help: 'Cubre impresiones que hay que repetir.' },
  { group: 'oper', key: 'transporte', label: 'Logística', help: 'Envíos y desplazamientos.' },
  { group: 'oper', key: 'marketing', label: 'Marketing', help: 'Publicidad y redes.' },
  { group: 'margin', key: 'iva', label: 'IVA', help: 'Pon 0 si no facturas con IVA.' },
]
const COMPANY_FIELDS = [
  { key: 'name', label: 'Nombre de la empresa', type: 'text' },
  { key: 'slogan', label: 'Eslogan', type: 'text' },
  { key: 'nit', label: 'NIT o cédula', type: 'text' },
  { key: 'phone', label: 'Teléfono / WhatsApp', type: 'tel' },
  { key: 'email', label: 'Correo de ventas', type: 'email' },
  { key: 'address', label: 'Dirección del taller', type: 'text' },
  { key: 'website', label: 'Sitio web', type: 'url' },
]
</script>


<style>
/* Range slider styling for SettingsPanel (not reliant on parent scoped CSS) */
input[type=range].range-slider {
  -webkit-appearance: none;
  appearance: none;
  background: transparent;
}
input[type=range].range-slider:focus {
  outline: none;
}
input[type=range].range-slider::-webkit-slider-runnable-track {
  width: 100%;
  height: 8px;
  cursor: pointer;
  background: #f1f5f9;
  border-radius: 4px;
}
input[type=range].range-slider::-webkit-slider-thumb {
  height: 20px;
  width: 20px;
  border-radius: 50%;
  background: #059669;
  cursor: pointer;
  -webkit-appearance: none;
  appearance: none;
  margin-top: -6px;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 0 12px rgba(5, 150, 105, 0.4);
  border: 2px solid white;
  transition: all 0.2s;
}
input[type=range].range-slider:hover::-webkit-slider-thumb {
  transform: scale(1.2);
}
input[type=range].range-slider::-moz-range-track {
  width: 100%;
  height: 8px;
  cursor: pointer;
  background: #f1f5f9;
  border-radius: 4px;
}
input[type=range].range-slider::-moz-range-thumb {
  height: 20px;
  width: 20px;
  border-radius: 50%;
  background: #059669;
  cursor: pointer;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 0 12px rgba(5, 150, 105, 0.4);
  border: 2px solid white;
}

/* Eliminar spinners de todos los inputs numéricos transparentes del panel */
input[type=number].no-spinner::-webkit-outer-spin-button,
input[type=number].no-spinner::-webkit-inner-spin-button {
  -webkit-appearance: none;
  margin: 0;
}
input[type=number].no-spinner {
  -moz-appearance: textfield;
  appearance: textfield;
}

/* Eliminar spinners de TODOS los inputs de tipo number con fondo transparente */
input[type=number]::-webkit-outer-spin-button,
input[type=number]::-webkit-inner-spin-button {
  -webkit-appearance: none;
  margin: 0;
}
input[type=number] {
  -moz-appearance: textfield;
  appearance: textfield;
}
</style>

