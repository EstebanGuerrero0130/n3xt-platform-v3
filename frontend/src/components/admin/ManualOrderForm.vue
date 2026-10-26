<template>
  <AdminModal :show="visible" title="Nuevo pedido manual" description="Para pedidos que llegan por WhatsApp o en el taller. El precio lo pones tú." size="xl" :busy="submitting" @close="$emit('close')">
    <form id="manual-order-form" class="grid gap-6 lg:grid-cols-2" novalidate @submit.prevent="canSubmit && submit()">
      <section class="space-y-4" aria-labelledby="mo-customer">
        <h3 id="mo-customer" class="admin-h2 text-base">Cliente</h3>
        <div v-if="contacts.length">
          <label for="mo-contact" class="admin-label mb-2">Cliente guardado <span class="opacity-70">(opcional)</span></label>
          <select id="mo-contact" class="field" @change="selectCustomer">
            <option value="">Elegir de Contactos…</option>
            <option v-for="c in contacts" :key="c.id" :value="c.id">{{ c.name }}</option>
          </select>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
          <div class="sm:col-span-2"><label for="mo-name" class="admin-label mb-2">Nombre</label><input id="mo-name" v-model="form.customer_name" type="text" required class="field" data-autofocus></div>
          <div><label for="mo-email" class="admin-label mb-2">Correo <span class="opacity-70">(para los avisos)</span></label><input id="mo-email" v-model="form.customer_email" type="email" class="field"></div>
          <div><label for="mo-phone" class="admin-label mb-2">WhatsApp</label><input id="mo-phone" v-model="form.customer_phone" type="tel" class="field"></div>
          <div><label for="mo-doc" class="admin-label mb-2">CC / NIT</label><input id="mo-doc" v-model="form.customer_id_document" type="text" class="field"></div>
          <div><label for="mo-city" class="admin-label mb-2">Ciudad</label><input id="mo-city" v-model="form.shipping_city" type="text" class="field"></div>
          <div class="sm:col-span-2"><label for="mo-address" class="admin-label mb-2">Dirección de envío</label><input id="mo-address" v-model="form.shipping_address" type="text" class="field"></div>
        </div>
      </section>

      <section class="space-y-4" aria-labelledby="mo-job">
        <h3 id="mo-job" class="admin-h2 text-base">Trabajo</h3>
        <div><label for="mo-jobname" class="admin-label mb-2">Nombre del trabajo</label><input id="mo-jobname" v-model="form.job_name" type="text" placeholder="Ej. Llaveros evento" class="field"></div>
        <div class="grid gap-4 sm:grid-cols-2">
          <fieldset>
            <legend class="admin-label mb-2">Tecnología</legend>
            <div class="flex gap-2">
              <button v-for="t in ['FDM', 'SLA']" :key="t" type="button" class="chip" :aria-pressed="form.technology === t" @click="form.technology = t; form.material_id = ''">{{ t }}</button>
            </div>
          </fieldset>
          <div>
            <label for="mo-material" class="admin-label mb-2">Material</label>
            <select id="mo-material" v-model="form.material_id" class="field" required>
              <option value="">Elegir…</option>
              <option v-for="m in filteredMaterials" :key="m.id" :value="m.id">{{ m.name }}</option>
            </select>
          </div>
        </div>
        <div class="grid grid-cols-3 gap-3">
          <div><label for="mo-weight" class="admin-label mb-2">Peso (g)</label><input id="mo-weight" v-model.number="form.weight_g" type="number" min="0" step="0.1" class="field tabular-nums"></div>
          <div><label for="mo-qty" class="admin-label mb-2">Unidades</label><input id="mo-qty" v-model.number="form.qty" type="number" min="1" class="field tabular-nums"></div>
          <div><label for="mo-hours" class="admin-label mb-2">Horas</label><input id="mo-hours" v-model.number="form.duration_h" type="number" min="0" step="0.5" class="field tabular-nums"></div>
        </div>
        <div><label for="mo-price" class="admin-label mb-2">Precio final ($)</label><input id="mo-price" v-model.number="form.total_price" type="number" min="0" class="field tabular-nums text-lg font-semibold"></div>
        <div><label for="mo-comments" class="admin-label mb-2">Notas</label><textarea id="mo-comments" v-model="form.comments" rows="2" class="field !h-auto py-2.5"></textarea></div>
      </section>
    </form>
    <template #footer>
      <p v-if="submitError" role="alert" class="mr-auto self-center text-sm text-danger">{{ submitError }}</p>
      <button type="button" class="btn-outlined" :disabled="submitting" @click="$emit('close')">Cancelar</button>
      <button type="submit" form="manual-order-form" class="btn-primary" :disabled="submitting || !canSubmit">{{ submitting ? 'Creando…' : 'Crear pedido' }}</button>
    </template>
  </AdminModal>
</template>

<script setup lang="ts">
import { ref, reactive, computed, type PropType } from 'vue'
import { api } from '../../services/api'
import logger from '../../utils/logger'
import AdminModal from './AdminModal.vue'

interface MaterialOption { id: string; name: string; category?: string; cost_per_kg?: number | string }
interface ContactOption {
  id: number | string; name: string; company?: string; customer_id_document?: string
  email?: string; phone?: string; address_full?: string; location?: string
  city_dept_country?: string; zip_code?: string; location_reference?: string; notes?: string
}

const props = defineProps({
  visible: Boolean,
  materials: { type: Array as PropType<MaterialOption[]>, default: () => [] },
  contacts: { type: Array as PropType<ContactOption[]>, default: () => [] },
  settings: { type: Object, default: () => ({ infra: {}, prep: {}, margin: {} }) },
})

const emit = defineEmits(['close', 'created'])

const submitting = ref(false)
const submitError = ref('')

const form = reactive({
  customer_id: '' as number | string, customer_name: '', customer_company: '', customer_id_document: '',
  customer_email: '', customer_phone: '', shipping_address: '', shipping_city: '',
  shipping_zip: '', shipping_reference: '',
  material_id: '', weight_g: 0, duration_h: 0, total_price: 0,
  qty: 1, technology: 'FDM', comments: '', job_name: '',
})
const canSubmit = computed(() => !!form.customer_name.trim() && !!form.material_id && Number(form.total_price) > 0)

const filteredMaterials = computed(() =>
  props.materials.filter(m => m.category === form.technology || m.category === 'General')
)

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

const submit = async () => {
  if (submitting.value) return
  submitting.value = true
  submitError.value = ''
  try {
    await api.post('/orders', {
      ...form, estimated_weight_g: form.weight_g, estimated_duration_h: form.duration_h,
      volume_mm3: (form.weight_g / 1.25) * 1000,
      cost_snapshot: {
        settings: JSON.parse(JSON.stringify(props.settings)),
        material_cost_per_kg: props.materials.find(m => m.id === form.material_id)?.cost_per_kg || 0,
      }
    })
    emit('created')
    reset()
  } catch (err) {
    logger.error('Error:', err)
    submitError.value = err instanceof Error ? err.message : 'No se pudo crear la orden.'
  } finally {
    submitting.value = false
  }
}

const reset = () => {
  Object.assign(form, {
    customer_id: '', customer_name: '', customer_company: '', customer_id_document: '', customer_email: '',
    customer_phone: '', shipping_address: '', shipping_city: '', shipping_zip: '', shipping_reference: '',
    material_id: '', weight_g: 0, duration_h: 0, total_price: 0, qty: 1,
    technology: 'FDM', comments: '', job_name: '',
  })
}
</script>
