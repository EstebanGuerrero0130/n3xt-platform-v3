<script setup lang="ts">
import { ref, watch, onBeforeUnmount } from 'vue'

const legalModal = ref({
    show: false,
    title: '',
    content: ''
})

const openLegal = (type: string) => {
    legalModal.value.show = true
    if (type === 'privacy') {
        legalModal.value.title = 'Política de Privacidad'
        legalModal.value.content = 'Contenido en redacción...'
    } else {
        legalModal.value.title = 'Términos y Condiciones'
        legalModal.value.content = 'Contenido en redacción...'
    }
}

const setContent = (title: string, content: string) => {
    legalModal.value.title = title
    legalModal.value.content = content
    legalModal.value.show = true
}

const close = () => { legalModal.value.show = false }

const onKeydown = (e: KeyboardEvent) => {
    if (e.key === 'Escape') close()
}

watch(() => legalModal.value.show, (open) => {
    if (open) window.addEventListener('keydown', onKeydown)
    else window.removeEventListener('keydown', onKeydown)
})

onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown))

defineExpose({ openLegal, setContent })
</script>

<template>
    <transition name="fade">
        <div v-if="legalModal.show" class="fixed inset-0 z-[100] flex items-center justify-center p-3 sm:p-6" role="dialog" aria-modal="true" aria-labelledby="legal-modal-title">
            <div class="absolute inset-0 bg-black/60" @click="close"></div>
            <div class="relative w-full max-w-3xl bg-surface border border-line rounded-[var(--radius-panel)] shadow-2xl overflow-hidden max-h-[85vh] flex flex-col">
                <div class="flex items-start justify-between gap-4 px-5 sm:px-8 pt-6 pb-5 border-b border-line">
                    <div>
                        <h2 id="legal-modal-title" class="text-2xl md:text-3xl font-extrabold tracking-tight text-ink">{{ legalModal.title }}</h2>
                        <p class="mt-1 text-sm text-muted">Documento legal de N3XT 3D</p>
                    </div>
                    <button type="button" class="icon-btn shrink-0 -mr-2 -mt-1 bg-transparent text-muted hover:bg-surface-2 hover:text-ink" aria-label="Cerrar" @click="close">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="flex-1 overflow-y-auto px-5 sm:px-8 py-6">
                    <div class="max-w-prose text-base leading-relaxed text-ink whitespace-pre-wrap">{{ legalModal.content }}</div>
                </div>

                <div class="px-5 sm:px-8 py-4 border-t border-line flex justify-end">
                    <button type="button" class="btn-primary" @click="close">Entendido</button>
                </div>
            </div>
        </div>
    </transition>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active {
  transition: opacity 0.2s ease;
}
.fade-enter-from, .fade-leave-to {
  opacity: 0;
}
</style>
