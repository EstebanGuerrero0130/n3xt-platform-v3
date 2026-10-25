import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'
import { ViteImageOptimizer } from 'vite-plugin-image-optimizer'

export default defineConfig({
  base: '/',
  plugins: [
    vue(),
    tailwindcss(),
    ViteImageOptimizer({
      png: { quality: 75 },
      jpg: { quality: 70 },
      webp: { quality: 70 },
      svg: { quality: 85 },
    }),
  ],
  build: {
    outDir: 'dist',
    chunkSizeWarningLimit: 700,
    assetsInlineLimit: 4096,
    rolldownOptions: {
      output: {
        // Grupos con prioridad: Vue se captura primero, así el grupo de gráficas
        // (solo admin) no lo arrastra y echarts no se precarga en las páginas públicas.
        codeSplitting: {
          groups: [
            { name: 'vue-vendor', test: /node_modules[\\/](vue|@vue|vue-router|@unhead)[\\/]/, priority: 20 },
            { name: 'charts-vendor', test: /node_modules[\\/](echarts|zrender|vue-echarts)[\\/]/, priority: 10 },
          ],
        },
      },
    },
  },
  server: {
    host: true,
    port: 5173
  }
})
