<template>
  <!-- N3XT Premium Notification -->
  <teleport to="body">
    <transition name="notif-slide">
      <div
        v-if="notification.show"
        :class="[
          'fixed top-6 left-1/2 -translate-x-1/2 z-[200] px-8 py-4 rounded-[2rem] font-black text-xs uppercase tracking-[0.3em] shadow-2xl backdrop-blur-3xl flex items-center gap-5 border transition-all duration-700 animate-in fade-in slide-in-from-top-4',
          notification.type === 'success'
            ? 'bg-emerald-500/95 text-white border-emerald-400/50 shadow-emerald-500/20'
            : notification.type === 'warning'
            ? 'bg-amber-500/95 text-white border-amber-400/50 shadow-amber-500/20'
            : 'bg-rose-500/95 text-white border-rose-400/50 shadow-rose-500/20'
        ]"
      >
        <div :class="['w-3 h-3 rounded-full', notification.type === 'success' ? 'bg-white animate-ping' : notification.type === 'warning' ? 'bg-white animate-pulse' : 'bg-white animate-pulse']"></div>
        <span class="relative">{{ notification.message }}</span>
        <div class="absolute -bottom-1 left-0 h-[3px] bg-white/30 rounded-full animate-shrink-width" style="animation: shrink 4s linear forwards;"></div>
      </div>
    </transition>
  </teleport>

  <!-- N3XT Premium Confirm Dialog -->
  <teleport to="body">
    <transition name="fade-scale">
      <div
        v-if="confirmDialog.show"
        class="fixed inset-0 z-[150] flex items-center justify-center p-6"
      >
        <div class="absolute inset-0 bg-black/70 backdrop-blur-md" @click="handleClose"></div>
        <div
          :class="[
            'relative w-full max-w-lg p-10 rounded-[3rem] border shadow-2xl overflow-hidden',
            isDark ? 'bg-gray-900 border-white/10' : 'bg-white border-gray-100'
          ]"
        >
          <div class="absolute -top-20 -right-20 w-48 h-48 bg-primary/10 rounded-full blur-[80px] pointer-events-none"></div>

          <div v-if="confirmDialog.icon" class="mb-8 flex justify-center">
            <div class="w-20 h-20 bg-rose-500/10 rounded-[2rem] flex items-center justify-center border border-rose-500/20">
              <svg class="w-10 h-10 text-rose-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" :d="confirmDialog.icon"/>
              </svg>
            </div>
          </div>

          <h3 class="text-2xl font-black uppercase tracking-tighter mb-4 text-center" :class="isDark ? 'text-white' : 'text-gray-900'">{{ confirmDialog.title }}</h3>
          <p class="text-sm font-bold text-gray-400 text-center mb-10 uppercase leading-relaxed tracking-tight">{{ confirmDialog.message }}</p>

          <div class="flex gap-4 justify-center">
            <button
              v-if="confirmDialog.mode === 'confirm'"
              class="px-10 py-5 bg-gray-100 dark:bg-white/10 rounded-2xl font-black text-xs uppercase tracking-[0.3em] hover:bg-gray-200 dark:hover:bg-white/20 transition-all flex-1"
              @click="handleClose"
            >
              Cancelar
            </button>
            <button
              :class="[
                'px-10 py-5 rounded-2xl font-black text-xs uppercase tracking-[0.3em] transition-all flex-1',
                confirmDialog.mode === 'alert' ? 'bg-primary text-white hover:bg-primary/90' : 'bg-rose-500 text-white hover:bg-rose-600'
              ]"
              @click="handleConfirm(confirmDialog.onConfirm)"
            >
              {{ confirmDialog.mode === 'alert' ? 'Entendido' : 'Confirmar' }}
            </button>
          </div>

          <div class="mt-8 pt-6 border-t border-gray-100 dark:border-white/5 text-center">
            <span class="text-[8px] font-black text-gray-400 uppercase tracking-[0.5em]">N3XT OS v3.2 • Acción Requerida</span>
          </div>
        </div>
      </div>
    </transition>
  </teleport>
</template>

<script setup lang="ts">
import { type PropType } from 'vue'

const props = defineProps({
  notification: { type: Object as PropType<any>, required: true },
  confirmDialog: { type: Object as PropType<any>, required: true },
  isDark: { type: Boolean, default: false }
})

const emit = defineEmits(['close'])

const handleClose = () => {
  emit('close')
}

const handleConfirm = async (onConfirm: any) => {
  if (onConfirm) {
    await onConfirm()
  }
  emit('close')
}
</script>

<style scoped>
@keyframes shrink {
  from { width: 100%; }
  to { width: 0%; }
}

.notif-slide-enter-active { transition: all 0.6s cubic-bezier(0.19, 1, 0.22, 1); }
.notif-slide-leave-active { transition: all 0.4s ease-in; }
.notif-slide-enter-from { opacity: 0; transform: translateX(-50%) translateY(-20px) scale(0.9); }
.notif-slide-leave-to { opacity: 0; transform: translateX(-50%) translateY(-30px) scale(0.9); }

.fade-scale-enter-active { transition: all 0.4s cubic-bezier(0.19, 1, 0.22, 1); }
.fade-scale-leave-active { transition: all 0.2s ease-in; }
.fade-scale-enter-from { opacity: 0; transform: scale(0.9); }
.fade-scale-leave-to { opacity: 0; transform: scale(0.95); }
</style>
