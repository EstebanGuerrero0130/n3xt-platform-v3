<script setup lang="ts">
import { ref } from 'vue'

const legalModal = ref({
    show: false,
    title: '',
    content: ''
})

const openLegal = (type) => {
    legalModal.value.show = true
    if (type === 'privacy') {
        legalModal.value.title = 'Política de Privacidad'
        legalModal.value.content = 'Contenido en redacción...'
    } else {
        legalModal.value.title = 'Términos y Condiciones'
        legalModal.value.content = 'Contenido en redacción...'
    }
}

const setContent = (title, content) => {
    legalModal.value.title = title
    legalModal.value.content = content
    legalModal.value.show = true
}

defineExpose({ openLegal, setContent })
</script>

<template>
    <transition name="fade">
        <div v-if="legalModal.show" class="fixed inset-0 z-[100] flex items-center justify-center p-6 md:p-12">
            <div class="absolute inset-0 bg-black/90 backdrop-blur-xl" @click="legalModal.show = false"></div>
            <div class="relative w-full max-w-4xl bg-[#0a0f14] border border-white/10 rounded-[3rem] p-10 md:p-16 shadow-2xl overflow-hidden max-h-[80vh] flex flex-col">
                <div class="absolute top-0 right-0 p-8">
                    <button class="w-12 h-12 rounded-2xl bg-white/5 text-white flex items-center justify-center hover:bg-primary transition-colors text-2xl" @click="legalModal.show = false">✕</button>
                </div>
                
                <div class="mb-12">
                    <h2 class="text-3xl md:text-5xl font-black text-white uppercase tracking-tighter italic border-l-8 border-primary pl-8">{{ legalModal.title }}</h2>
                    <p class="text-[10px] font-black text-gray-500 uppercase tracking-widest mt-4 ml-10">Documentación Legal Oficial N3XT 3D</p>
                </div>

                <div class="flex-1 overflow-y-auto pr-6 custom-scrollbar text-sm md:text-lg text-gray-400 font-medium leading-relaxed whitespace-pre-wrap">
                    {{ legalModal.content }}
                </div>

                <div class="mt-12 pt-8 border-t border-white/5 flex justify-end">
                    <button class="px-10 py-4 bg-primary text-white rounded-2xl font-black text-[10px] uppercase tracking-widest hover:bg-emerald-600 transition-all" @click="legalModal.show = false">Entendido</button>
                </div>
            </div>
        </div>
    </transition>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active {
  transition: opacity 0.4s cubic-bezier(0.22, 1, 0.36, 1);
}
.fade-enter-from, .fade-leave-to {
  opacity: 0;
}
</style>
