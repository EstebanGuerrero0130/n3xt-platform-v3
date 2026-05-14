<script setup>
import { onMounted, computed } from 'vue'
import { useRoute } from 'vue-router'
import WhatsAppWidget from './components/WhatsAppWidget.vue'

const route = useRoute()
const showWhatsApp = computed(() => !route.path.startsWith('/admin'))

onMounted(() => {
  // Por defecto DARK a menos que esté guardado como light
  const theme = localStorage.getItem('n3xt_theme')
  const isDark = theme !== 'light'
  
  if (isDark) {
    document.documentElement.classList.add('dark')
    if (!theme) localStorage.setItem('n3xt_theme', 'dark')
  } else {
    document.documentElement.classList.remove('dark')
  }
})
</script>

<template>
  <router-view />
  <WhatsAppWidget v-if="showWhatsApp" />
</template>

<style>
</style>
