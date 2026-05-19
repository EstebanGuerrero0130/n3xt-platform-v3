<script setup>
import { ref } from 'vue'

const isDark = ref(localStorage.getItem('n3xt_theme') !== 'light')
</script>

<template>
  <footer class="bg-black text-white py-20 px-6 border-t border-white/5 font-inter relative overflow-hidden">
    <div class="max-w-7xl mx-auto relative z-10">
      <div class="grid grid-cols-1 md:grid-cols-4 gap-12 md:gap-8">
        
        <!-- COL 1: BRAND -->
        <div class="space-y-6">
          <div class="flex items-center gap-4">
            <div class="w-12 h-12 bg-primary/10 border border-primary/20 rounded-2xl flex items-center justify-center shadow-[0_0_20px_rgba(var(--primary-rgb),0.1)]">
              <svg class="w-7 h-7 text-primary animate-pulse" viewBox="0 0 512 512" fill="none" xmlns="http://www.w3.org/2000/svg">
                <g transform="rotate(45 256 256)">
                  <path d="M236 370 C200 420 220 500 256 512 C292 500 312 420 276 370 Z" fill="currentColor" />
                  <path d="M200 280 C160 280 110 330 110 375 C110 400 130 405 155 405 C185 405 200 380 208 370 Z" fill="currentColor" />
                  <path d="M312 280 C352 280 402 330 402 375 C402 400 382 405 357 405 C327 405 312 380 304 370 Z" fill="currentColor" />
                  <path d="M256 50 C216 110 196 190 196 310 C196 345 204 370 216 370 L296 370 C308 370 316 345 316 310 C316 190 296 110 256 50 Z" fill="currentColor" />
                  <circle cx="256" cy="170" r="28" fill="#000" />
                  <circle cx="256" cy="170" r="16" fill="currentColor" />
                </g>
              </svg>
            </div>
            <div>
              <h2 class="text-2xl font-black italic tracking-tighter leading-none">N3XT <span class="text-primary">3D</span></h2>
              <p class="text-[8px] font-black text-primary uppercase tracking-[0.3em] mt-1">Manufactura de Precisión</p>
            </div>
          </div>
          <p class="text-[10px] font-bold text-gray-500 uppercase leading-relaxed tracking-wider max-w-[280px]">
            El siguiente nivel de la fabricación digital en Colombia. Coleccionables de alto nivel y soluciones industriales.
          </p>
        </div>

        <!-- COL 2: NAVEGACION -->
        <div class="space-y-8">
          <div class="flex items-center gap-3">
            <div class="w-1 h-4 bg-primary"></div>
            <h4 class="text-[10px] font-black uppercase tracking-[0.3em]">Navegación</h4>
          </div>
          <ul class="space-y-4">
            <li v-for="link in [
              { n: 'Inicio', p: '/' },
              { n: 'Catálogo', p: '/catalog' },
              { n: 'Cotizador', p: '/quote' },
              { n: 'Rastrear', p: '/track' },
              { n: 'Taller', p: '/admin' },
              { n: 'Contacto', p: '/project/init' }
            ]" :key="link.n" class="flex items-center gap-4 group">
              <span class="w-4 h-px bg-gray-800 group-hover:bg-primary transition-colors"></span>
              <router-link :to="link.p" class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-400 hover:text-primary transition-colors">{{ link.n }}</router-link>
            </li>
          </ul>
        </div>

        <!-- COL 3: CONECTA -->
        <div class="space-y-8">
          <div class="flex items-center gap-3">
            <div class="w-1 h-4 bg-primary"></div>
            <h4 class="text-[10px] font-black uppercase tracking-[0.3em]">Conecta</h4>
          </div>
          <div class="space-y-3">
            <a href="#" class="flex items-center gap-4 bg-white/5 border border-white/5 p-4 rounded-2xl hover:bg-white/10 transition-all group">
              <div class="w-8 h-8 flex items-center justify-center">
                <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.06-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.9-.32-1.98-.23-2.81.33-.85.51-1.44 1.43-1.58 2.41-.14.96.13 1.98.81 2.69.8.84 2.01 1.13 3.1.75 1.15-.31 2.03-1.32 2.22-2.5.03-.35.03-.71.02-1.07V.02z"/></svg>
              </div>
              <span class="text-[9px] font-black uppercase tracking-[0.2em] text-gray-400 group-hover:text-white">TikTok @N3XT3D</span>
            </a>
            <a href="#" class="flex items-center gap-4 bg-white/5 border border-white/5 p-4 rounded-2xl hover:bg-white/10 transition-all group">
              <div class="w-8 h-8 flex items-center justify-center">
                <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
              </div>
              <span class="text-[9px] font-black uppercase tracking-[0.2em] text-gray-400 group-hover:text-white">WhatsApp Directo</span>
            </a>
          </div>
        </div>

        <!-- COL 4: SOPORTE & ESTADO -->
        <div class="space-y-4">
          <div class="bg-white/5 border border-white/5 p-8 rounded-[2.5rem] space-y-4">
            <h4 class="text-[9px] font-black uppercase tracking-[0.3em] mb-4">Soporte Elite</h4>
            <div class="flex items-center gap-3">
              <div class="w-1.5 h-1.5 bg-primary rounded-full animate-pulse shadow-[0_0_10px_rgba(var(--primary-rgb),1)]"></div>
              <span class="text-[9px] font-black text-primary uppercase tracking-widest italic">servicion3xt@gmail.com</span>
            </div>
            <div class="flex items-center gap-3">
              <div class="w-1.5 h-1.5 bg-gray-600 rounded-full"></div>
              <span class="text-[9px] font-black text-gray-400 uppercase tracking-widest">+57 3118798416</span>
            </div>
          </div>

          <div class="bg-white/5 border border-white/5 p-8 rounded-[2.5rem] flex items-center justify-between">
            <div>
              <h4 class="text-[7px] font-black uppercase tracking-[0.3em] text-gray-500 mb-2">Estado del Taller</h4>
              <p class="text-[10px] font-black uppercase tracking-[0.2em] text-primary">Operativo 24/7</p>
            </div>
            <div class="w-2.5 h-2.5 bg-primary rounded-full shadow-[0_0_15px_rgba(var(--primary-rgb),0.5)]"></div>
          </div>
        </div>
      </div>

      <!-- FOOTER BOTTOM -->
      <div class="mt-20 pt-10 border-t border-white/5 flex flex-col md:flex-row justify-between items-center gap-6">
        <p class="text-[8px] font-black text-gray-600 uppercase tracking-[0.3em]">
          © 2026 N3XT 3D. Fabricación Avanzada.
        </p>
        <div class="flex gap-8">
          <a href="#" class="text-[8px] font-black text-gray-600 uppercase tracking-[0.3em] hover:text-white transition-colors">Privacidad</a>
          <a href="#" class="text-[8px] font-black text-gray-600 uppercase tracking-[0.3em] hover:text-white transition-colors">Términos</a>
        </div>
      </div>
    </div>
  </footer>
</template>

<style scoped>
/* Grid técnico sutil de fondo */
footer::before {
  content: '';
  position: absolute;
  inset: 0;
  background-image: linear-gradient(to right, rgba(255,255,255,0.02) 1px, transparent 1px), linear-gradient(to bottom, rgba(255,255,255,0.02) 1px, transparent 1px);
  background-size: 40px 40px;
  pointer-events: none;
}
</style>
