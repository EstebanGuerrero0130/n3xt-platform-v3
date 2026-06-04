<template>
  <!-- Premium Toast Notification -->
  <Teleport to="body">
    <div
      v-if="props.notification.show"
      class="fixed top-6 right-6 z-[9999] max-w-sm w-full animate-slide-in"
    >
      <div
        :class="[
          'relative overflow-hidden rounded-2xl border shadow-2xl backdrop-blur-xl p-5',
          props.notification.type === 'success' ? 'bg-emerald-900/90 border-emerald-600/50 text-emerald-100' : '',
          props.notification.type === 'error' ? 'bg-red-900/90 border-red-600/50 text-red-100' : '',
          props.notification.type === 'warning' ? 'bg-amber-900/90 border-amber-600/50 text-amber-100' : '',
        ]"
      >
        <div class="flex items-start gap-4">
          <div class="flex-shrink-0 mt-0.5">
            <svg v-if="props.notification.type === 'success'" class="w-6 h-6 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <svg v-else-if="props.notification.type === 'error'" class="w-6 h-6 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/>
            </svg>
            <svg v-else class="w-6 h-6 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
          </div>
          <div class="flex-1 min-w-0">
            <p class="text-sm font-semibold">{{ props.notification.message }}</p>
          </div>
          <button class="flex-shrink-0 opacity-60 hover:opacity-100 transition-opacity" @click="props.notification.show = false">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
          </button>
        </div>
        <div class="absolute bottom-0 left-0 right-0 h-1 bg-white/10">
          <div class="h-full bg-white/30 animate-shrink-bar" style="animation-duration: 4s"></div>
        </div>
      </div>
    </div>

    <!-- Premium Confirm/Alert Dialog -->
    <Transition name="modal-fade">
      <div
        v-if="props.confirmDialog.show"
        class="fixed inset-0 z-[9998] flex items-center justify-center p-4"
        @click.self="emit('cancel-confirm')"
      >
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm"></div>
        <div class="relative bg-gray-900 border border-gray-700 rounded-3xl shadow-2xl max-w-md w-full p-8 text-center">
          <div v-if="props.confirmDialog.icon" class="text-5xl mb-4">{{ props.confirmDialog.icon }}</div>
          <h3 class="text-xl font-bold text-white mb-3">{{ props.confirmDialog.title }}</h3>
          <p class="text-gray-400 text-sm mb-8 leading-relaxed">{{ props.confirmDialog.message }}</p>
          <div class="flex gap-4 justify-center">
            <button
              v-if="props.confirmDialog.mode === 'confirm'"
              class="px-6 py-3 rounded-xl bg-gray-800 hover:bg-gray-700 text-gray-300 font-semibold transition-all text-sm"
              @click="handleCancel"
            >
              Cancelar
            </button>
            <button
              :class="[
                'px-6 py-3 rounded-xl font-bold transition-all text-sm',
                props.confirmDialog.mode === 'alert' ? 'bg-emerald-600 hover:bg-emerald-500 text-white' :
                props.confirmDialog.title.includes('Eliminar') || props.confirmDialog.title.includes('Reiniciar') ? 'bg-red-600 hover:bg-red-500 text-white' :
                'bg-emerald-600 hover:bg-emerald-500 text-white'
              ]"
              @click="handleConfirm"
            >
              {{ props.confirmDialog.mode === 'alert' ? 'Entendido' : 'Confirmar' }}
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup lang="ts">
const props = defineProps({
  notification: { type: Object, default: () => ({ show: false, message: '', type: 'success' }) },
  confirmDialog: { type: Object, default: () => ({ show: false, title: '', message: '', icon: '', onConfirm: null, mode: 'confirm' }) },
})

const emit = defineEmits(['close-notification', 'confirm', 'cancel-confirm'])

const handleConfirm = async () => {
  if (props.confirmDialog.onConfirm) {
    await props.confirmDialog.onConfirm()
  }
  emit('confirm')
}

const handleCancel = () => {
  emit('cancel-confirm')
}
</script>

<style scoped>
.animate-slide-in {
  animation: slideIn 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}
@keyframes slideIn {
  from { transform: translateX(100%); opacity: 0; }
  to { transform: translateX(0); opacity: 1; }
}
.animate-shrink-bar {
  animation: shrinkBar 4s linear forwards;
}
@keyframes shrinkBar {
  from { width: 100%; }
  to { width: 0%; }
}
.modal-fade-enter-active, .modal-fade-leave-active {
  transition: opacity 0.2s ease;
}
.modal-fade-enter-from, .modal-fade-leave-to {
  opacity: 0;
}
</style>
