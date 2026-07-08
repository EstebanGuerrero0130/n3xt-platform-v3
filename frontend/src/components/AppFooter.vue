<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { api } from '../services/api'
import LegalModal from './LegalModal.vue'

const webSettings = ref<any>({ social: { tiktok: 'https://tiktok.com/@n3xt3d', instagram: 'https://instagram.com/n3xt3d' } })
const companySettings = ref<any>({ email: 'servicion3xt@gmail.com', phone: '+57 311 879 6416' })
const legalModalRef = ref<any>(null)

const openLegal = (type: string) => {
  if (legalModalRef.value) legalModalRef.value.open(type)
}

onMounted(async () => {
  try {
    const data = await api.get('/settings')
    if (data?.web) webSettings.value = { ...webSettings.value, ...data.web }
    if (data?.company) companySettings.value = { ...companySettings.value, ...data.company }
  } catch (e) {
    // Silently use defaults
  }
})
</script>

<template>
  <footer class="px-4 md:px-6 pb-12 pt-16 relative overflow-hidden">
    <div class="max-w-7xl mx-auto relative bg-gradient-to-br from-[#05080b] via-[#0a1218] to-[#0a1510] rounded-[2.5rem] md:rounded-[3.5rem] border border-emerald-500/10 shadow-[0_30px_80px_-15px_rgba(16,185,129,0.12)] overflow-hidden">
      <!-- Grid overlay -->
      <div class="absolute inset-0 opacity-[0.02] bg-grid pointer-events-none"></div>
      <!-- Glow -->
      <div class="absolute -top-32 -right-32 w-[350px] h-[350px] bg-emerald-500/5 rounded-full blur-[150px] pointer-events-none"></div>
      <div class="absolute -bottom-32 -left-32 w-[350px] h-[350px] bg-emerald-500/5 rounded-full blur-[150px] pointer-events-none"></div>

      <div class="relative z-10 px-6 pt-10 pb-6 md:px-12 lg:px-16 md:pt-14 md:pb-10">
        <!-- Top: Brand + Tagline -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-6 mb-12 pb-8 border-b border-emerald-500/10">
          <div class="flex items-center gap-4">
            <div class="w-14 h-14 md:w-16 md:h-16 bg-gradient-to-br from-emerald-500 to-emerald-700 rounded-full flex items-center justify-center text-white font-black italic text-2xl md:text-3xl shadow-[0_0_25px_rgba(16,185,129,0.3)]">N</div>
            <div>
              <h3 class="text-2xl md:text-3xl font-black text-white uppercase tracking-tight leading-none">N3XT <span class="text-emerald-400">3D</span></h3>
              <p class="text-[7px] md:text-[8px] font-black text-emerald-500/50 uppercase tracking-[0.2em] mt-1">Manufactura de Precisión</p>
            </div>
          </div>
          <p class="text-[9px] md:text-[10px] text-[#8b949e] font-bold uppercase leading-relaxed tracking-[0.15em] max-w-[320px] text-left md:text-right">
            El siguiente nivel de la fabricación digital en Colombia. Coleccionables de alto nivel y soluciones industriales.
          </p>
        </div>

        <!-- Content Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8 lg:gap-10 mb-12">
          <!-- NAVEGACIÓN -->
          <div class="space-y-5">
            <h4 class="text-xl md:text-2xl font-black uppercase text-white tracking-wide italic">Navegación</h4>
            <ul class="space-y-3">
              <li v-for="link in [{n:'Inicio', p:'/'}, {n:'Catálogo', p:'/catalog'}, {n:'Cotizador', p:'/quote'}, {n:'Rastrear', p:'/track'}, {n:'Taller', p:'/admin/login'}, {n:'Contacto', p:'/project/init'}]" :key="link.n">
                <router-link :to="link.p" class="flex items-center gap-3 text-[10px] font-black text-[#8b949e] hover:text-emerald-400 uppercase tracking-[0.2em] transition-all duration-300">
                  <span class="w-3 h-[1.5px] bg-emerald-500/30"></span>
                  {{ link.n }}
                </router-link>
              </li>
            </ul>
          </div>

          <!-- CONECTA -->
          <div class="space-y-5">
            <h4 class="text-xl md:text-2xl font-black uppercase text-white tracking-wide italic">Conecta</h4>
            <div class="flex flex-col gap-3">
              <a :href="webSettings.social.tiktok" target="_blank" class="flex items-center gap-4 px-5 py-3.5 rounded-2xl border border-[#21262d] hover:border-emerald-500/30 transition-all group">
                <svg class="w-4 h-4 text-[#8b949e] group-hover:text-white transition-colors shrink-0" fill="currentColor" viewBox="0 0 384 382"><path d="M137.17 156.98v-15.56c-5.34-.73-10.76-1.18-16.29-1.18C54.23 140.24 0 194.47 0 261.13c0 40.9 20.43 77.09 51.61 98.97-20.12-21.6-32.46-50.53-32.46-82.31 0-65.7 52.69-119.28 118.03-120.81Z"/><path d="M140.02 333c29.74 0 54-23.66 55.1-53.13l.11-263.2h48.08c-1-5.41-1.55-10.97-1.55-16.67h-65.67l-.11 263.2c-1.1 29.47-25.36 53.13-55.1 53.13-9.24 0-17.95-2.31-25.61-6.34C105.3 323.9 121.6 333 140.02 333ZM333.13 106V91.37c-18.34 0-35.43-5.45-49.76-14.8 12.76 14.65 30.09 25.22 49.76 29.43Z"/><path d="M283.38 76.57c-13.98-16.05-22.47-37-22.47-59.91h-17.59c4.63 25.02 19.48 46.49 40.06 59.91ZM120.88 205.92c-30.44 0-55.21 24.77-55.21 55.21 0 21.2 12.03 39.62 29.6 48.86-6.55-9.08-10.45-20.18-10.45-32.2 0-30.44 24.77-55.21 55.21-55.21 5.68 0 11.13.94 16.29 2.55v-67.05c-5.34-.73-10.76-1.18-16.29-1.18-.96 0-1.9.05-2.85.07v51.49c-5.16-1.61-10.61-2.55-16.29-2.55Z"/><path d="M333.13 106v51.04c-34.05 0-65.61-10.89-91.37-29.38v133.47c0 66.66-54.23 120.88-120.88 120.88-25.76 0-49.64-8.12-69.28-21.91 22.08 23.71 53.54 38.57 88.42 38.57 66.66 0 120.88-54.23 120.88-120.88V144.33c25.76 18.49 57.32 29.38 91.37 29.38v-65.68c-6.57 0-12.97-.71-19.14-2.03Z"/></svg>
                <div>
                  <p class="text-[9px] font-black text-white uppercase tracking-[0.15em]">TikTok</p>
                  <p class="text-[8px] font-bold text-[#8b949e] uppercase tracking-widest">@N3XT3D</p>
                </div>
              </a>
              <a :href="'https://wa.me/' + (companySettings.phone || '573118796416').replace(/\+/g, '').replace(/\s/g, '')" target="_blank" class="flex items-center gap-4 px-5 py-3.5 rounded-2xl border border-[#21262d] hover:border-emerald-500/30 transition-all group">
                <svg class="w-4 h-4 text-[#8b949e] group-hover:text-white transition-colors shrink-0" fill="currentColor" viewBox="0 0 362 362"><path d="M307.546 52.566C273.709 18.684 228.706.017 180.756 0 81.951 0 1.538 80.404 1.504 179.235c-.017 31.594 8.242 62.432 23.928 89.609L0 361.736l95.024-24.925c26.179 14.285 55.659 21.805 85.655 21.814h.077c98.788 0 179.21-80.413 179.244-179.244.017-47.898-18.608-92.926-52.454-126.807v-.008Zm-126.79 275.788h-.06c-26.73-.008-52.952-7.194-75.831-20.765l-5.44-3.231-56.391 14.791 15.05-54.981-3.542-5.638c-14.912-23.721-22.793-51.139-22.776-79.286.035-82.14 66.867-148.973 149.051-148.973 39.793.017 77.198 15.53 105.328 43.695 28.131 28.157 43.61 65.596 43.593 105.398-.035 82.149-66.867 148.982-148.982 148.982v.008Zm81.719-111.577c-4.478-2.243-26.497-13.073-30.606-14.568-4.108-1.496-7.09-2.243-10.073 2.243-2.982 4.487-11.568 14.577-14.181 17.559-2.613 2.991-5.226 3.361-9.704 1.117-4.477-2.243-18.908-6.97-36.02-22.226-13.313-11.878-22.304-26.54-24.916-31.027-2.613-4.486-.275-6.91 1.959-9.136 2.011-2.011 4.478-5.234 6.721-7.847 2.244-2.613 2.983-4.486 4.478-7.469 1.496-2.991.748-5.603-.369-7.847-1.118-2.243-10.073-24.289-13.812-33.253-3.636-8.732-7.331-7.546-10.073-7.692-2.613-.13-5.595-.155-8.586-.155-2.991 0-7.839 1.118-11.947 5.604-4.108 4.486-15.677 15.324-15.677 37.361s16.047 43.344 18.29 46.335c2.243 2.991 31.585 48.225 76.51 67.632 10.684 4.615 19.029 7.374 25.535 9.437 10.727 3.412 20.49 2.931 28.208 1.779 8.604-1.289 26.498-10.838 30.228-21.298 3.73-10.46 3.73-19.433 2.613-21.298-1.117-1.865-4.108-2.991-8.586-5.234l.008-.017Z"/></svg>
                <div>
                  <p class="text-[9px] font-black text-white uppercase tracking-[0.15em]">WhatsApp</p>
                  <p class="text-[8px] font-bold text-[#8b949e] uppercase tracking-widest">Directo</p>
                </div>
              </a>
            </div>
          </div>

          <!-- SOPORTE -->
          <div class="space-y-5">
            <h4 class="text-xl md:text-2xl font-black uppercase text-white tracking-wide italic">Soporte</h4>
            <div class="border border-[#21262d] rounded-2xl p-5 flex flex-col gap-5">
              <div class="flex items-start gap-3">
                <div class="w-8 h-8 rounded-lg bg-emerald-500/10 flex items-center justify-center shrink-0 mt-0.5">
                  <svg class="w-3.5 h-3.5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                </div>
                <div class="min-w-0">
                  <p class="text-[8px] font-black text-[#8b949e] uppercase tracking-[0.2em] mb-1">Email</p>
                  <p class="text-[9px] font-black text-white uppercase tracking-wider break-all">{{ companySettings.email || 'servicion3xt@gmail.com' }}</p>
                </div>
              </div>
              <div class="flex items-start gap-3">
                <div class="w-8 h-8 rounded-lg bg-emerald-500/10 flex items-center justify-center shrink-0 mt-0.5">
                  <svg class="w-3.5 h-3.5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                </div>
                <div class="min-w-0">
                  <p class="text-[8px] font-black text-[#8b949e] uppercase tracking-[0.2em] mb-1">Teléfono</p>
                  <p class="text-[9px] font-black text-white uppercase tracking-wider">{{ companySettings.phone || '+57 311 879 6416' }}</p>
                </div>
              </div>
            </div>
          </div>

          <!-- ESTADO -->
          <div class="space-y-5">
            <h4 class="text-xl md:text-2xl font-black uppercase text-white tracking-wide italic">Estado</h4>
            <div class="border border-[#21262d] rounded-2xl p-5 flex flex-col justify-between gap-5 min-h-[140px]">
              <div class="flex items-start justify-between">
                <div>
                  <p class="text-[8px] font-black text-[#8b949e] uppercase tracking-[0.2em] mb-2">Taller</p>
                  <p class="text-[10px] font-black text-white uppercase tracking-widest leading-relaxed">Operativo<br/>24/7</p>
                </div>
                <div class="w-3 h-3 bg-emerald-500 rounded-full animate-pulse shadow-[0_0_12px_rgba(16,185,129,0.6)]"></div>
              </div>
              <router-link to="/project/init" class="flex items-center justify-between text-[9px] font-black text-emerald-400 hover:text-white uppercase tracking-[0.15em] transition-colors pt-4 border-t border-[#21262d]">
                Iniciar Proyecto
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
              </router-link>
            </div>
          </div>
        </div>

        <!-- Bottom Bar -->
        <div class="border-t border-emerald-500/10 pt-6 flex flex-col md:flex-row justify-between items-center gap-4">
          <p class="text-[8px] font-black text-[#8b949e] uppercase tracking-[0.15em]">&copy; 2026 N3XT 3D. Fabricación Avanzada.</p>
          <div class="flex gap-6">
            <button class="text-[8px] font-black text-[#8b949e] hover:text-emerald-400 uppercase tracking-[0.15em] transition-colors" @click="openLegal('privacy')">Privacidad</button>
            <button class="text-[8px] font-black text-[#8b949e] hover:text-emerald-400 uppercase tracking-[0.15em] transition-colors" @click="openLegal('terms')">Términos</button>
          </div>
        </div>
      </div>
    </div>
    
    <LegalModal ref="legalModalRef" />
  </footer>
</template>

<style scoped>
.bg-grid {
  background-image: linear-gradient(rgba(255,255,255,0.5) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,0.5) 1px, transparent 1px);
  background-size: 32px 32px;
}
</style>
