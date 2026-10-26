<script setup lang="ts">
import { ref, computed, type PropType } from 'vue'
import AdminModal from './AdminModal.vue'

const props = defineProps({
  notification: { type: Object as PropType<any>, required: true },
  confirmDialog: { type: Object as PropType<any>, required: true },
  isDark: { type: Boolean, default: false }
})

const emit = defineEmits(['close'])

// Mientras corre la acción no se puede volver a confirmar (evita doble ejecución)
const running = ref(false)
// Solo las acciones que borran o vacían llevan el botón rojo
const isDanger = computed(() => /eliminar|borrar|purga|vaciar|reiniciar|peligro/i.test(`${props.confirmDialog.title} ${props.confirmDialog.message}`))

const handleClose = () => {
  if (!running.value) emit('close')
}

const handleConfirm = async (onConfirm: any) => {
  if (running.value) return
  running.value = true
  try {
    if (onConfirm) await onConfirm()
  } finally {
    running.value = false
    emit('close')
  }
}

const TONES: Record<string, string> = {
  success: 'bg-ink text-canvas',
  warning: 'bg-warning text-white',
  error: 'bg-danger text-white',
}
</script>

<template>
  <!-- Aviso breve -->
  <teleport to="body">
    <div class="fixed inset-x-0 top-4 z-[200] flex justify-center px-4 pointer-events-none" :role="notification.type === 'error' ? 'alert' : 'status'" aria-live="polite">
      <transition
        enter-active-class="transition duration-200"
        enter-from-class="opacity-0 -translate-y-2"
        leave-active-class="transition duration-150"
        leave-to-class="opacity-0"
      >
        <p
          v-if="notification.show"
          :class="['pointer-events-auto max-w-md rounded-full px-5 py-3 text-[0.9375rem] font-medium shadow-xl', TONES[notification.type] || TONES.success]"
        >{{ notification.message }}</p>
      </transition>
    </div>
  </teleport>

  <!-- Confirmación / aviso -->
  <AdminModal :show="confirmDialog.show" :title="confirmDialog.title" :busy="running" @close="handleClose">
    <p class="text-ink leading-relaxed">{{ confirmDialog.message }}</p>
    <template #footer>
      <button v-if="confirmDialog.mode === 'confirm'" type="button" class="btn-outlined" :disabled="running" @click="handleClose">Cancelar</button>
      <button
        type="button"
        :class="confirmDialog.mode === 'confirm' && isDanger ? 'btn-danger' : 'btn-primary'"
        :disabled="running"
        data-autofocus
        @click="handleConfirm(confirmDialog.onConfirm)"
      >{{ running ? 'Procesando…' : confirmDialog.mode === 'alert' ? 'Entendido' : 'Confirmar' }}</button>
    </template>
  </AdminModal>
</template>
