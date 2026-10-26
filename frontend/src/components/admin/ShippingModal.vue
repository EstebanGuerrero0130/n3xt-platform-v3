<script setup lang="ts">
import { computed } from 'vue'
import AdminModal from './AdminModal.vue'

const props = defineProps<{
  modelValue: boolean
  orderToShip: any
  trackingGuide: string
  trackingCarrier: string
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', val: boolean): void
  (e: 'update:trackingGuide', val: string): void
  (e: 'update:trackingCarrier', val: string): void
  (e: 'confirm-shipping'): void
}>()

const CARRIERS = ['Servientrega', 'Interrapidísimo', 'Envía', 'Coordinadora', 'TCC', 'Recoge en el taller', 'Otra']
// Si el cliente recoge en el taller no hay guía
const isPickup = computed(() => props.trackingCarrier === 'Recoge en el taller')
const canConfirm = computed(() => !!props.trackingCarrier && (isPickup.value || !!props.trackingGuide.trim()))
const confirm = () => {
  if (isPickup.value && !props.trackingGuide.trim()) emit('update:trackingGuide', 'Recoge en el taller')
  emit('confirm-shipping')
}
</script>

<template>
  <AdminModal
    :show="modelValue"
    :title="`Registrar envío del pedido #${orderToShip?.id ?? ''}`"
    description="El cliente recibe la guía por correo y la ve en su seguimiento."
    @close="emit('update:modelValue', false)"
  >
    <form id="shipping-form" class="space-y-4" @submit.prevent="canConfirm && confirm()">
      <div>
        <label for="ship-carrier" class="admin-label mb-2">Transportadora</label>
        <select id="ship-carrier" :value="trackingCarrier" class="field" data-autofocus @change="emit('update:trackingCarrier', ($event.target as HTMLSelectElement).value)">
          <option value="">Elegir…</option>
          <option v-for="c in CARRIERS" :key="c" :value="c">{{ c }}</option>
        </select>
      </div>
      <div v-if="!isPickup">
        <label for="ship-guide" class="admin-label mb-2">Número de guía</label>
        <input id="ship-guide" :value="trackingGuide" type="text" inputmode="numeric" class="field tabular-nums" @input="emit('update:trackingGuide', ($event.target as HTMLInputElement).value)">
      </div>
    </form>
    <template #footer>
      <button type="button" class="btn-outlined" @click="emit('update:modelValue', false)">Cancelar</button>
      <button type="submit" form="shipping-form" class="btn-primary" :disabled="!canConfirm">Marcar como enviado</button>
    </template>
  </AdminModal>
</template>
