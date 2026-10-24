<script setup lang="ts">
import { ref, watch, nextTick, onBeforeUnmount, type PropType } from 'vue'
import { useCaptcha } from '../composables/useCaptcha'
import { useTurnstile } from '../composables/useTurnstile'

interface QuoteModelSummary {
  id: number
  name: string
  hasModel: boolean
  dimensions: { x: number; y: number; z: number }
}
interface MaterialOption { id: string; name: string }

interface CustomerForm {
  name: string; company: string; document: string; email: string; phone: string
  city: string; address: string; use: string; comments: string
}

const props = defineProps({
  show: { type: Boolean, default: false },
  models: { type: Array as PropType<QuoteModelSummary[]>, default: () => [] },
  materials: { type: Array as PropType<MaterialOption[]>, default: () => [] },
  selectedTechnology: { type: String, default: 'FDM' },
  selectedMaterial: { type: String, default: '' },
  qty: { type: Number, default: 1 },
  breakdown: { type: Object as PropType<{ total: number; discount: number; weight: number; duration: number }>, default: () => ({ total: 0, discount: 0, weight: 0, duration: 0 }) },
  activeCoupon: { type: Object as PropType<{ label: string; discount: number } | null>, default: null },
  isSubmitting: { type: Boolean, default: false },
  notification: { type: Object, default: () => ({ show: false, message: '', type: 'info' }) }
})

const emit = defineEmits<{
  close: []
  submit: [payload: { customerForm: CustomerForm; captchaVerified: boolean; turnstileToken: string }]
}>()

const modalStep = ref(1)
const customerForm = ref<CustomerForm>({
  name: '', company: '', document: '', email: '', phone: '',
  city: '', address: '', use: 'Funcional', comments: ''
})
const { challenge, answer, verify, isLocked, reset } = useCaptcha()
const turnstile = useTurnstile()
const turnstileEl = ref<HTMLElement | null>(null)
const turnstileHint = ref('')
const step1Form = ref<HTMLFormElement | null>(null)
const step2Form = ref<HTMLFormElement | null>(null)

const handleClose = () => {
  modalStep.value = 1
  emit('close')
}

/** Validates previous steps (native required/email checks) before moving forward. */
const goToStep = (target: number) => {
  if (target > 1 && step1Form.value && !step1Form.value.checkValidity()) {
    modalStep.value = 1
    nextTick(() => step1Form.value?.reportValidity())
    return
  }
  if (target > 2 && step2Form.value && !step2Form.value.checkValidity()) {
    modalStep.value = 2
    nextTick(() => step2Form.value?.reportValidity())
    return
  }
  modalStep.value = target
}

const handleConfirm = () => {
  if (props.isSubmitting) return
  goToStep(3)
  if (modalStep.value !== 3) return
  if (turnstile.enabled && !turnstile.token.value) {
    turnstileHint.value = 'Marca la casilla de verificación para enviar el pedido.'
    return
  }
  turnstileHint.value = ''
  if (!verify()) return
  emit('submit', { customerForm: { ...customerForm.value }, captchaVerified: true, turnstileToken: turnstile.token.value })
}

const onKeydown = (e: KeyboardEvent) => {
  if (e.key === 'Escape') handleClose()
}

watch(() => props.show, (v) => {
  if (v) {
    modalStep.value = 1
    reset()
    window.addEventListener('keydown', onKeydown)
  } else {
    window.removeEventListener('keydown', onKeydown)
    turnstile.remove()
  }
})

// Render Turnstile once the confirmation step is visible
watch(modalStep, (step) => {
  if (step === 3 && props.show) nextTick(() => turnstile.render(turnstileEl.value))
})

// Turnstile tokens are single-use: reset after a failed submit (modal still open)
watch(() => props.isSubmitting, (now, before) => {
  if (before && !now && props.show) {
    turnstile.reset()
    reset()
  }
})

onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown))

const getMaterialName = (id: string) => {
  const mat = props.materials.find(m => m.id === id)
  return mat ? mat.name : id
}

const formatTime = (h: number) => {
  if (!h || h < 0) return '0m';
  const hours = Math.floor(h);
  const minutes = Math.round((h - hours) * 60);
  if (hours === 0) return `${minutes}m`;
  return `${hours}h ${minutes}m`;
}
</script>

<template>
<transition
  enter-active-class="transition duration-200 ease-out" enter-from-class="opacity-0" enter-to-class="opacity-100"
  leave-active-class="transition duration-150 ease-in" leave-from-class="opacity-100" leave-to-class="opacity-0">
  <div v-if="show" class="fixed inset-0 z-[100] flex items-center justify-center p-3 sm:p-6 bg-black/60" role="dialog" aria-modal="true" aria-labelledby="quote-order-modal-title">
    <div class="bg-surface w-full max-w-2xl rounded-[var(--radius-panel)] shadow-2xl border border-line overflow-hidden flex flex-col max-h-[92vh]">

      <!-- Encabezado + pasos -->
      <div class="px-5 sm:px-8 pt-6 pb-5 border-b border-line shrink-0">
        <div class="flex items-start justify-between gap-4">
          <div>
            <h2 id="quote-order-modal-title" class="text-2xl font-extrabold tracking-tight text-ink">Solicitar cotización</h2>
            <p class="mt-1 text-sm text-muted">Déjanos tus datos y el taller revisa tu pieza antes de confirmar el precio.</p>
          </div>
          <button class="icon-btn shrink-0 -mr-2 -mt-1 bg-transparent text-muted hover:bg-surface-2 hover:text-ink" type="button" aria-label="Cerrar" @click="handleClose">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
          </button>
        </div>

        <ol class="mt-6 grid grid-cols-3" aria-label="Pasos de la solicitud">
          <li v-for="s in [{ n: 1, label: 'Tus datos' }, { n: 2, label: 'Entrega' }, { n: 3, label: 'Confirmar' }]" :key="s.n" class="relative flex flex-col items-center text-center">
            <span v-if="s.n < 3" class="absolute top-4 left-[calc(50%+1.5rem)] right-[calc(-50%+1.5rem)] h-0.5 rounded" :class="modalStep > s.n ? 'bg-primary' : 'bg-line'" aria-hidden="true"></span>
            <button
              type="button"
              :disabled="s.n > modalStep"
              :aria-current="modalStep === s.n ? 'step' : undefined"
              :aria-label="'Ir al paso ' + s.n"
              :class="modalStep > s.n ? 'bg-primary border-primary text-white' : (modalStep === s.n ? 'border-primary bg-surface text-brand' : 'border-line bg-surface text-muted')"
              class="relative w-8 h-8 rounded-full border-2 flex items-center justify-center text-sm font-semibold tabular-nums transition-colors disabled:cursor-not-allowed"
              @click="goToStep(s.n)"
            >
              <svg v-if="modalStep > s.n" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
              <span v-else>{{ s.n }}</span>
            </button>
            <span class="mt-2 text-xs sm:text-sm" :class="modalStep === s.n ? 'font-semibold text-ink' : 'font-medium text-muted'">{{ s.label }}</span>
          </li>
        </ol>
      </div>

      <div class="px-5 sm:px-8 py-6 overflow-y-auto flex-1">
        <!-- PASO 1: DATOS DEL CLIENTE -->
        <form v-show="modalStep === 1" ref="step1Form" class="space-y-5" novalidate @submit.prevent="goToStep(2)">
          <div>
            <h3 class="text-lg font-semibold text-ink [font-stretch:100%] tracking-normal">Tus datos</h3>
            <p class="text-sm text-muted">Paso 1 de 3. Los campos con <span class="text-red-600 dark:text-red-400">*</span> son obligatorios.</p>
          </div>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-ink mb-2" for="order-name">Nombre completo <span class="text-red-600 dark:text-red-400" aria-hidden="true">*</span></label>
              <input id="order-name" v-model="customerForm.name" type="text" autocomplete="name" aria-describedby="order-name-error" class="field" required>
              <p id="order-name-error" class="field-error">Escribe tu nombre.</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-ink mb-2" for="order-company">Empresa <span class="font-normal text-muted">(opcional)</span></label>
              <input id="order-company" v-model="customerForm.company" type="text" autocomplete="organization" class="field">
            </div>
            <div>
              <label class="block text-sm font-medium text-ink mb-2" for="order-document">NIT o cédula <span class="font-normal text-muted">(opcional)</span></label>
              <input id="order-document" v-model="customerForm.document" type="text" class="field">
            </div>
            <div>
              <label class="block text-sm font-medium text-ink mb-2" for="order-email">Email <span class="text-red-600 dark:text-red-400" aria-hidden="true">*</span></label>
              <input id="order-email" v-model="customerForm.email" type="email" autocomplete="email" aria-describedby="order-email-error" class="field" required>
              <p id="order-email-error" class="field-error">Escribe un email válido, por ejemplo nombre@correo.com.</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-ink mb-2" for="order-phone">WhatsApp o teléfono <span class="text-red-600 dark:text-red-400" aria-hidden="true">*</span></label>
              <input id="order-phone" v-model="customerForm.phone" type="tel" autocomplete="tel" aria-describedby="order-phone-error" class="field" required>
              <p id="order-phone-error" class="field-error">Escribe un número de contacto.</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-ink mb-2" for="order-city">Ciudad</label>
              <input id="order-city" v-model="customerForm.city" type="text" autocomplete="address-level2" class="field">
            </div>
          </div>
          <div class="pt-2 flex justify-end">
            <button class="btn-primary w-full sm:w-auto" type="submit">Continuar</button>
          </div>
        </form>

        <!-- PASO 2: DIRECCIÓN + USO -->
        <form v-show="modalStep === 2" ref="step2Form" class="space-y-5" novalidate @submit.prevent="goToStep(3)">
          <div>
            <h3 class="text-lg font-semibold text-ink [font-stretch:100%] tracking-normal">Entrega y uso</h3>
            <p class="text-sm text-muted">Paso 2 de 3</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-ink mb-2" for="order-address">Dirección de envío <span class="text-red-600 dark:text-red-400" aria-hidden="true">*</span></label>
            <input id="order-address" v-model="customerForm.address" type="text" autocomplete="street-address" aria-describedby="order-address-error" class="field" required>
            <p id="order-address-error" class="field-error">Escribe la dirección donde quieres recibir el pedido.</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-ink mb-2" for="order-use">Uso de la pieza</label>
            <select id="order-use" v-model="customerForm.use" class="field cursor-pointer">
              <option value="Prototipo">Prototipo rápido</option>
              <option value="Funcional">Pieza mecánica o funcional</option>
              <option value="Decorativo">Arte o decoración</option>
              <option value="Medico">Uso industrial o médico</option>
              <option value="Joyeria">Joyería o dental</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-ink mb-2" for="order-comments">Notas para el taller <span class="font-normal text-muted">(opcional)</span></label>
            <textarea id="order-comments" v-model="customerForm.comments" rows="3" class="field h-auto py-3 resize-y" placeholder="Ej.: acabado liso, color específico, fecha límite"></textarea>
          </div>
          <div class="pt-2 flex flex-col-reverse sm:flex-row sm:justify-between gap-3">
            <button class="btn-secondary w-full sm:w-auto" type="button" @click="modalStep = 1">Atrás</button>
            <button class="btn-primary w-full sm:w-auto" type="submit">Continuar</button>
          </div>
        </form>

        <!-- PASO 3: RESUMEN + CONFIRMAR -->
        <form v-show="modalStep === 3" class="space-y-5" novalidate @submit.prevent="handleConfirm">
          <div>
            <h3 class="text-lg font-semibold text-ink [font-stretch:100%] tracking-normal">Revisa y confirma</h3>
            <p class="text-sm text-muted">Paso 3 de 3</p>
          </div>

          <div class="rounded-[var(--radius-card)] border border-line divide-y divide-line">
            <dl class="grid grid-cols-1 min-[400px]:grid-cols-2 gap-x-6 gap-y-3 p-4 text-sm">
              <div><dt class="text-muted">Tecnología</dt><dd class="font-medium text-ink">{{ selectedTechnology === 'SLA' ? 'SLA (resina)' : 'FDM (filamento)' }}</dd></div>
              <div><dt class="text-muted">Material</dt><dd class="font-medium text-ink">{{ getMaterialName(selectedMaterial) }}</dd></div>
              <div><dt class="text-muted">Modelos</dt><dd class="font-medium text-ink tabular-nums">{{ models.length }} {{ models.length === 1 ? 'modelo' : 'modelos' }}</dd></div>
              <div><dt class="text-muted">Cantidad</dt><dd class="font-medium text-ink tabular-nums">{{ qty }} {{ qty === 1 ? 'unidad' : 'unidades' }}</dd></div>
            </dl>

            <ul class="p-4 space-y-1.5 text-sm">
              <li v-for="m in models" :key="m.id" class="flex justify-between gap-4">
                <span class="text-ink">{{ m.name }}</span>
                <span class="tabular-nums" :class="m.hasModel ? 'text-ink' : 'text-muted'">{{ m.hasModel ? m.dimensions.x.toFixed(0) + ' × ' + m.dimensions.y.toFixed(0) + ' × ' + m.dimensions.z.toFixed(0) + ' mm' : 'Sin archivo' }}</span>
              </li>
            </ul>

            <dl class="p-4 space-y-2 text-sm">
              <div class="flex justify-between gap-4"><dt class="text-muted">Contacto</dt><dd class="text-ink text-right break-all">{{ customerForm.name }}<br>{{ customerForm.email }}</dd></div>
              <div class="flex justify-between gap-4"><dt class="text-muted">Envío a</dt><dd class="text-ink text-right">{{ customerForm.address }}<template v-if="customerForm.city">, {{ customerForm.city }}</template></dd></div>
            </dl>

            <dl class="p-4 space-y-2 text-sm">
              <div class="flex justify-between gap-4"><dt class="text-muted">Peso estimado</dt><dd class="tabular-nums text-ink">{{ (breakdown.weight || 0).toFixed(1) }} g</dd></div>
              <div class="flex justify-between gap-4"><dt class="text-muted">Tiempo estimado</dt><dd class="tabular-nums text-ink">{{ formatTime(breakdown.duration || 0) }}</dd></div>
              <div v-if="activeCoupon" class="flex justify-between gap-4"><dt class="text-resin">Descuento ({{ activeCoupon.label }})</dt><dd class="tabular-nums text-resin">−${{ Math.round(breakdown.discount).toLocaleString('es-CO') }}</dd></div>
              <div class="flex items-baseline justify-between gap-4 pt-2 border-t border-line">
                <dt class="font-medium text-ink">Total estimado</dt>
                <dd class="text-2xl font-extrabold tracking-tight tabular-nums text-resin">${{ Math.round(breakdown.total).toLocaleString('es-CO') }}</dd>
              </div>
              <p class="text-xs text-muted">Precio estimado con IVA, sujeto a revisión del taller.</p>
            </dl>
          </div>

          <!-- Verificación -->
          <div>
            <label for="order-captcha" class="block text-sm font-medium text-ink mb-2">Verificación: <span id="order-captcha-q" class="font-semibold tabular-nums">{{ challenge.text }}</span></label>
            <input id="order-captcha" v-model="answer" type="number" inputmode="numeric" :disabled="isLocked" aria-label="Respuesta de validación" aria-describedby="order-captcha-q" class="field w-32 tabular-nums disabled:opacity-50" placeholder="Respuesta">
            <p v-if="isLocked" class="mt-1.5 text-sm text-red-600 dark:text-red-400" role="alert">Demasiados intentos. Espera 30 segundos y vuelve a intentarlo.</p>
          </div>

          <!-- Cloudflare Turnstile -->
          <div v-if="turnstile.enabled" class="space-y-2">
            <div ref="turnstileEl"></div>
            <p v-if="turnstileHint || turnstile.error.value" class="text-sm text-red-600 dark:text-red-400" role="alert">{{ turnstileHint || turnstile.error.value }}</p>
          </div>

          <div class="pt-2 flex flex-col-reverse sm:flex-row sm:justify-between gap-3">
            <button class="btn-secondary w-full sm:w-auto" type="button" @click="modalStep = 2">Atrás</button>
            <button :disabled="isSubmitting" type="submit" class="btn-primary w-full sm:w-auto">
              <span v-if="isSubmitting" class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin" aria-hidden="true"></span>
              <span>{{ isSubmitting ? 'Enviando…' : 'Confirmar pedido' }}</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</transition>
<transition
  enter-active-class="transition duration-300 ease-out" enter-from-class="translate-y-4 opacity-0" enter-to-class="translate-y-0 opacity-100"
  leave-active-class="transition duration-200 ease-in" leave-from-class="translate-y-0 opacity-100" leave-to-class="translate-y-4 opacity-0">
  <div
    v-if="props.notification?.show"
    role="status"
    aria-live="polite"
    class="fixed inset-x-4 bottom-6 z-[200] mx-auto max-w-md flex items-start gap-3 rounded-[var(--radius-card)] border border-line bg-surface px-4 py-3 shadow-xl"
  >
    <span
      class="mt-0.5 w-6 h-6 shrink-0 rounded-full flex items-center justify-center text-sm font-bold text-white"
      :class="props.notification?.type === 'success' ? 'bg-primary' : (props.notification?.type === 'warning' ? 'bg-amber-600' : (props.notification?.type === 'error' ? 'bg-red-600' : 'bg-ink'))"
      aria-hidden="true"
    >{{ props.notification?.type === 'success' ? '✓' : '!' }}</span>
    <p class="text-[0.9375rem] text-ink">{{ props.notification?.message }}</p>
  </div>
</transition>
</template>

<style scoped>
.field {
  width: 100%;
  height: 2.75rem;
  padding: 0 1rem;
  border-radius: var(--radius-control);
  border: 1px solid var(--border-main);
  background-color: var(--bg-main);
  color: var(--text-main);
  font-size: 1rem;
}
.field::placeholder { color: color-mix(in srgb, var(--text-muted) 70%, transparent); }
.field:focus { outline: none; border-color: var(--color-primary); }
.field:user-invalid { border-color: var(--color-red-600, #dc2626); }
.field-error {
  display: none;
  margin-top: 0.375rem;
  font-size: 0.875rem;
  color: var(--color-red-600, #dc2626);
}
:where(.dark) .field-error { color: var(--color-red-400, #f87171); }
.field:user-invalid + .field-error { display: block; }
</style>
