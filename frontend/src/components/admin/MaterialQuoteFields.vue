<script setup lang="ts">
/* Parámetros del cotizador de un material (vacío = valor por defecto del taller). */
defineProps<{ idPrefix: string }>()
const model = defineModel<{ speed_factor: number | string | null; waste_pct: number | string | null; failure_pct: number | string | null }>({ required: true })
</script>

<template>
  <fieldset class="space-y-3">
    <legend class="admin-label">Cotizador <span class="font-normal text-muted">(opcional, vacío = por defecto)</span></legend>
    <div class="grid gap-4 sm:grid-cols-3">
      <div>
        <label :for="`${idPrefix}-speed`" class="admin-label mb-2">Factor de tiempo</label>
        <input :id="`${idPrefix}-speed`" v-model="model.speed_factor" type="number" min="0.1" max="10" step="0.05" placeholder="1" class="field tabular-nums" :aria-describedby="`${idPrefix}-speed-help`">
        <p :id="`${idPrefix}-speed-help`" class="mt-1.5 text-sm text-muted">1,2 = imprime 20 % más lento.</p>
      </div>
      <div>
        <label :for="`${idPrefix}-waste`" class="admin-label mb-2">Desperdicio (%)</label>
        <input :id="`${idPrefix}-waste`" v-model="model.waste_pct" type="number" min="0" max="100" step="0.5" placeholder="0" class="field tabular-nums">
        <p class="mt-1.5 text-sm text-muted">Material extra que se pierde.</p>
      </div>
      <div>
        <label :for="`${idPrefix}-fail`" class="admin-label mb-2">Reserva por fallos (%)</label>
        <input :id="`${idPrefix}-fail`" v-model="model.failure_pct" type="number" min="0" max="100" step="0.5" placeholder="Global" class="field tabular-nums">
        <p class="mt-1.5 text-sm text-muted">Reemplaza la de Parámetros.</p>
      </div>
    </div>
  </fieldset>
</template>
