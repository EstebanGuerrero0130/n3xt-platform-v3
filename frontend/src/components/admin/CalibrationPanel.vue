<script setup lang="ts">
/**
 * Calibración del tiempo de impresión: compara lo que tardaron las impresiones
 * terminadas con lo calculado y propone un factor por tecnología y material.
 * Aplicarlo cambia el cotizador, por eso pide la sección Parámetros (canApply).
 */
import { ref, computed, onMounted } from 'vue'
import { api } from '../../services/api'
import { deviationText, missingSamples, factorText, type CalibrationRow } from '../../utils/calibration'

interface TechRow extends CalibrationRow { technology: string }
interface MaterialRow extends CalibrationRow { material_id: string; material_name: string | null; technology: string }
interface PrinterRow extends Omit<CalibrationRow, 'suggested_factor'> { printer_id: number; printer_name: string | null }
interface Applied { time_factor: Record<string, number>; by_material: Record<string, number> }
interface Stats {
  min_samples: number
  by_technology: TechRow[]
  by_material: MaterialRow[]
  by_printer: PrinterRow[]
  applied: Applied
}
type Group = 'time_factor' | 'by_material'

const props = withDefaults(defineProps<{
  canApply?: boolean
  showNotify?: (msg: string, type?: string) => void
}>(), { canApply: false, showNotify: undefined })

const stats = ref<Stats | null>(null)
const loading = ref(true)
const error = ref('')
const saving = ref('')

const minSamples = computed(() => stats.value?.min_samples ?? 5)
const applied = computed<Applied>(() => stats.value?.applied ?? { time_factor: {}, by_material: {} })

const notify = (msg: string, type = 'success') => props.showNotify?.(msg, type)

const load = async () => {
  loading.value = true
  error.value = ''
  try {
    stats.value = await api.get('/admin/calibration', true)
  } catch (err) {
    error.value = err instanceof Error ? err.message : 'No se pudo cargar la calibración'
  } finally {
    loading.value = false
  }
}
onMounted(load)

// null quita el factor
const setFactor = async (group: Group, id: string, factor: number | null) => {
  saving.value = `${group}:${id}`
  try {
    const res = await api.post('/admin/calibration/apply', { [group]: { [id]: factor } })
    if (stats.value && res?.applied) stats.value.applied = res.applied
    notify(factor === null ? 'Ajuste quitado' : 'Ajuste aplicado a las próximas cotizaciones')
  } catch (err) {
    notify(err instanceof Error ? err.message : 'No se pudo guardar', 'error')
  } finally {
    saving.value = ''
  }
}

const rows = computed(() => [
  ...(stats.value?.by_technology ?? []).map(r => ({ ...r, group: 'time_factor' as Group, id: r.technology, label: r.technology, hint: 'Toda la tecnología' })),
  ...(stats.value?.by_material ?? []).map(r => ({ ...r, group: 'by_material' as Group, id: r.material_id, label: r.material_name || r.material_id, hint: `Material · ${r.technology}` })),
])
// Factores aplicados sin impresiones registradas todavía (p. ej. material sin datos)
const orphanFactors = computed(() => {
  const seen = new Set(rows.value.map(r => `${r.group}:${r.id}`))
  return (['time_factor', 'by_material'] as Group[]).flatMap(group =>
    Object.entries(applied.value[group] ?? {}).filter(([id]) => !seen.has(`${group}:${id}`)).map(([id, factor]) => ({ group, id, factor })))
})
const currentOf = (group: Group, id: string) => applied.value[group]?.[id] ?? null
</script>

<template>
  <section class="admin-card space-y-6" aria-labelledby="calibration-title">
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3">
      <div>
        <h2 id="calibration-title" class="admin-h2">Calibración del tiempo</h2>
        <p class="text-sm text-muted mt-1">Compara lo que tardaron las impresiones terminadas con lo calculado. El ajuste se aplica a las próximas cotizaciones.</p>
      </div>
      <button type="button" class="btn-outlined btn-sm" :disabled="loading" @click="load">Actualizar</button>
    </div>

    <p v-if="loading" class="py-8 text-center text-muted">Cargando…</p>
    <p v-else-if="error" role="alert" class="py-4 text-center text-danger">{{ error }}</p>
    <template v-else>
      <p v-if="!rows.length" class="py-6 text-center text-muted">
        Aún no hay impresiones terminadas con tiempo registrado. Se cuentan al pasar un pedido de «Imprimiendo» a la siguiente etapa.
      </p>

      <div v-else class="overflow-x-auto -mx-1 px-1">
        <table class="admin-table min-w-[44rem]">
          <thead>
            <tr><th>Grupo</th><th>Impresiones</th><th>Resultado</th><th>Sugerido</th><th>Actual</th><th class="sr-only">Acciones</th></tr>
          </thead>
          <tbody>
            <tr v-for="r in rows" :key="`${r.group}:${r.id}`">
              <td>
                <span class="font-semibold">{{ r.label }}</span>
                <span class="block text-sm text-muted">{{ r.hint }}</span>
              </td>
              <td class="tabular-nums">{{ r.samples }}</td>
              <td>
                <span>{{ deviationText(r.median_ratio) }}</span>
                <span v-if="missingSamples(r.samples, minSamples)" class="block text-sm text-muted">
                  Faltan {{ missingSamples(r.samples, minSamples) }} impresiones para sugerir un ajuste
                </span>
              </td>
              <td class="tabular-nums font-semibold">{{ factorText(r.suggested_factor) }}</td>
              <td>
                <span v-if="currentOf(r.group, r.id) !== null" class="badge bg-primary/15 text-brand">{{ factorText(currentOf(r.group, r.id)) }}</span>
                <span v-else class="badge bg-surface-2 text-muted">Sin ajuste</span>
              </td>
              <td class="text-right whitespace-nowrap">
                <template v-if="canApply">
                  <button
                    v-if="r.suggested_factor !== null && r.suggested_factor !== currentOf(r.group, r.id)"
                    type="button" class="btn-primary btn-sm" :disabled="!!saving"
                    :aria-label="`Aplicar ${factorText(r.suggested_factor)} a ${r.label}`"
                    @click="setFactor(r.group, r.id, r.suggested_factor)"
                  >Aplicar</button>
                  <button
                    v-if="currentOf(r.group, r.id) !== null"
                    type="button" class="ml-2 btn-outlined btn-sm" :disabled="!!saving"
                    :aria-label="`Quitar el ajuste de ${r.label}`"
                    @click="setFactor(r.group, r.id, null)"
                  >Quitar</button>
                </template>
              </td>
            </tr>
            <tr v-for="o in orphanFactors" :key="`${o.group}:${o.id}`">
              <td><span class="font-semibold">{{ o.id }}</span><span class="block text-sm text-muted">{{ o.group === 'time_factor' ? 'Toda la tecnología' : 'Material' }}</span></td>
              <td class="tabular-nums">0</td>
              <td class="text-muted">Sin impresiones registradas</td>
              <td>—</td>
              <td><span class="badge bg-primary/15 text-brand">{{ factorText(o.factor) }}</span></td>
              <td class="text-right">
                <button v-if="canApply" type="button" class="btn-outlined btn-sm" :disabled="!!saving" @click="setFactor(o.group, o.id, null)">Quitar</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <p v-if="!canApply && rows.length" class="text-sm text-muted">Para aplicar o quitar ajustes se necesita el permiso de Parámetros.</p>

      <details v-if="stats?.by_printer.length" class="text-sm">
        <summary class="cursor-pointer font-semibold">Por máquina (informativo)</summary>
        <div class="overflow-x-auto -mx-1 px-1 mt-3">
          <table class="admin-table min-w-[28rem]">
            <thead><tr><th>Máquina</th><th>Impresiones</th><th>Resultado</th></tr></thead>
            <tbody>
              <tr v-for="p in stats.by_printer" :key="p.printer_id">
                <td class="font-semibold">{{ p.printer_name || `Máquina ${p.printer_id}` }}</td>
                <td class="tabular-nums">{{ p.samples }}</td>
                <td>{{ deviationText(p.median_ratio) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </details>
    </template>
  </section>
</template>
