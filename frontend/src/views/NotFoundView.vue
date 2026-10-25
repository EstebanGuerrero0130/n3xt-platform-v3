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
  <div class="min-h-screen flex flex-col bg-canvas text-ink">
    <AppNavbar />

    <main id="main" class="flex-1 flex flex-col items-center justify-center text-center px-4 sm:px-6 py-16 md:py-24">
      <p class="text-sm text-muted">Error 404</p>
      <h1 class="mt-3 text-4xl sm:text-5xl lg:text-6xl font-extrabold tracking-tight">No encontramos esta página</h1>
      <p class="mt-4 text-lg text-muted max-w-prose">Puede que el enlace esté mal escrito o que la página se haya movido.</p>
      <div class="mt-8 flex flex-col sm:flex-row gap-3">
        <router-link to="/" class="btn-primary">Ir al inicio</router-link>
        <router-link to="/catalog" class="btn-secondary">Ver catálogo</router-link>
      </div>
    </main>

    <AppFooter />
  </div>
</template>
