<script setup lang="ts">
// Separador sobrio: una línea fina con el color de borde del tema, alineada al contenedor.
// Se conservan las props históricas para no romper usos existentes:
// - color y opacity ya no tiñen la línea (manda el token border-line, válido en ambos temas).
// - height es el alto total del separador (espacio vertical); la línea queda centrada.
// - flip no tiene efecto visible en una línea simétrica.
defineProps({
  color: { type: String, default: '' },
  height: { type: String, default: '1px' },
  opacity: { type: [String, Number], default: 1 },
  flip: { type: Boolean, default: false }
})
</script>

<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex items-center pointer-events-none" :style="{ height }" aria-hidden="true">
    <div class="h-px w-full bg-line"></div>
  </div>
</template>
