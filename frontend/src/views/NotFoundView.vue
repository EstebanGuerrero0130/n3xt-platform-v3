<script setup lang="ts">
import AppNavbar from '../components/AppNavbar.vue'
import AppFooter from '../components/AppFooter.vue'
import { usePageMeta } from '../composables/usePageMeta'

usePageMeta({
  title: 'Página no encontrada | N3XT 3D',
  description: 'La página que buscas no existe o fue movida.',
  noIndex: true,
})
</script>

<template>
  <div class="min-h-screen flex flex-col bg-[#f8fafc] dark:bg-[#0a0f14] text-slate-900 dark:text-white overflow-x-hidden transition-colors duration-500">
    <AppNavbar subtext="Centro de Precisión Industrial" />

    <main class="flex-1 flex flex-col items-center justify-center text-center px-6 py-28">
      <div class="inline-flex items-center gap-3 px-4 py-2 bg-emerald-500/10 rounded-full border border-emerald-500/20 mb-10">
        <span class="w-2 h-2 bg-emerald-500 rounded-full"></span>
        <span class="text-[10px] font-black text-emerald-700 dark:text-emerald-400 uppercase tracking-[0.4em]">Error 404</span>
      </div>
      <h1 class="text-6xl md:text-9xl font-black tracking-tighter uppercase leading-[0.85] mb-8">
        PÁGINA <br/>
        <span class="text-transparent bg-clip-text bg-gradient-to-r from-emerald-600 to-emerald-400 italic">NO ENCONTRADA.</span>
      </h1>
      <p class="max-w-md text-xs md:text-sm text-slate-500 dark:text-gray-400 font-bold uppercase tracking-[0.2em] leading-relaxed mb-12">
        La ruta que buscas no existe o fue movida.
      </p>
      <router-link to="/" class="inline-flex items-center gap-4 px-10 py-5 bg-slate-900 dark:bg-white text-white dark:text-gray-950 rounded-full font-black text-xs uppercase tracking-[0.3em] hover:bg-emerald-500 dark:hover:bg-emerald-500 hover:text-white transition-all duration-500">
        Volver al inicio <span aria-hidden="true">→</span>
      </router-link>
    </main>

    <AppFooter />
  </div>
</template>
