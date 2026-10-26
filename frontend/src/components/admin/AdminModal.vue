<script setup lang="ts">
/**
 * Diálogo del panel: foco atrapado, Escape y clic fuera cierran, título y pie
 * con las acciones. Todos los modales del admin se arman sobre este.
 */
import { ref, toRef, computed } from 'vue'
import { useFocusTrap } from '../../composables/useFocusTrap'

const props = withDefaults(defineProps<{
  show: boolean
  title: string
  description?: string
  size?: 'md' | 'lg' | 'xl'
  /** Mientras guarda no se cierra con Escape ni clic fuera */
  busy?: boolean
}>(), { description: '', size: 'md', busy: false })

const emit = defineEmits<{ (e: 'close'): void }>()

const panel = ref<HTMLElement | null>(null)
const close = () => { if (!props.busy) emit('close') }
useFocusTrap(panel, toRef(props, 'show'), { onEscape: close })

const width = computed(() => ({ md: 'max-w-lg', lg: 'max-w-3xl', xl: 'max-w-5xl' })[props.size])
const titleId = `admin-modal-${Math.random().toString(36).slice(2, 8)}`
</script>

<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition duration-200"
      enter-from-class="opacity-0"
      leave-active-class="transition duration-150"
      leave-to-class="opacity-0"
    >
      <div v-if="show" class="fixed inset-0 z-[90] flex items-end sm:items-center justify-center bg-black/50 p-0 sm:p-6" @mousedown.self="close">
        <div
          ref="panel"
          role="dialog"
          aria-modal="true"
          :aria-labelledby="titleId"
          :class="['w-full bg-canvas text-ink shadow-2xl flex flex-col max-h-[92vh] rounded-t-[var(--radius-panel)] sm:rounded-[var(--radius-panel)]', width]"
        >
          <header class="flex items-start justify-between gap-4 px-5 md:px-7 pt-5 md:pt-6 pb-4">
            <div class="min-w-0">
              <h2 :id="titleId" class="admin-h2 text-[1.375rem]">{{ title }}</h2>
              <p v-if="description" class="mt-1 text-sm text-muted">{{ description }}</p>
            </div>
            <button type="button" class="icon-btn shrink-0 -mr-2 text-muted hover:text-ink hover:bg-surface-2" aria-label="Cerrar" :disabled="busy" @click="close">
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
          </header>
          <div class="px-5 md:px-7 pb-5 overflow-y-auto">
            <slot />
          </div>
          <footer v-if="$slots.footer" class="px-5 md:px-7 py-4 border-t border-line flex flex-wrap justify-end gap-3">
            <slot name="footer" />
          </footer>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
