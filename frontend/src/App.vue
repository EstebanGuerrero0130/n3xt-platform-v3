<script setup lang="ts">
import { onMounted, computed } from 'vue'
import { useRoute } from 'vue-router'
import WhatsAppWidget from './components/WhatsAppWidget.vue'

const route = useRoute()
// Contáctanos ya abre WhatsApp y en el Cotizador el botón flotante tapa el resumen de precio
const hideWhatsAppOn = ['/project/init', '/quote']
const showWhatsApp = computed(() => !route.path.startsWith('/admin') && !hideWhatsAppOn.includes(route.path))

// Clave de transición: cambia con la vista, no con los parámetros
// (/galeria/a → /galeria/b reutiliza el componente y su watcher, como antes)
const viewKey = computed(() => String(route.matched[0]?.path ?? route.path))

onMounted(() => {
  // Respetar preferencia guardada, default dark
  const savedTheme = localStorage.getItem('n3xt_theme')
  if (savedTheme === 'light') {
    document.documentElement.classList.remove('dark')
  } else {
    document.documentElement.classList.add('dark')
    localStorage.setItem('n3xt_theme', 'dark')
  }
})
</script>

<template>
  <router-view v-slot="{ Component }">
    <transition name="page" mode="out-in" :duration="{ enter: 320, leave: 160 }">
      <component :is="Component" :key="viewKey" />
    </transition>
  </router-view>
  <WhatsAppWidget v-if="showWhatsApp" />
</template>

<style>
/* Transición entre rutas: la barra de navegación (header) queda quieta para que
   no parpadee; solo el contenido se funde con un desplazamiento corto. */
.page-leave-active > :not(header) {
  transition: opacity 160ms ease-in, transform 160ms ease-in;
}
.page-leave-to > :not(header) {
  opacity: 0;
  transform: translateY(-6px);
}
.page-enter-active > :not(header) {
  transition: opacity 320ms var(--ease-premium), transform 320ms var(--ease-premium);
}
.page-enter-from > :not(header) {
  opacity: 0;
  transform: translateY(10px);
}
</style>
