<script setup lang="ts">
import { onMounted, computed } from 'vue'
import { useRoute } from 'vue-router'
import WhatsAppWidget from './components/WhatsAppWidget.vue'

const route = useRoute()
// Contáctanos ya abre WhatsApp y en el Cotizador el botón flotante tapa el resumen de precio
const hideWhatsAppOn = ['/project/init', '/quote']
const showWhatsApp = computed(() => !route.path.startsWith('/admin') && !hideWhatsAppOn.includes(route.path))

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
  <router-view />
  <WhatsAppWidget v-if="showWhatsApp" />
</template>

<style>
</style>
