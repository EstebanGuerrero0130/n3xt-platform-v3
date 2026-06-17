<script setup lang="ts">
</script>

<template>
 <footer class="px-6 pb-20 relative overflow-hidden">
 <div class="max-w-7xl mx-auto relative">
 <!-- Main Footer Box -->
 <div class="relative bg-gradient-to-br from-[#05080b] via-[#0a1520] to-[#0f1a12] rounded-[4rem] border border-emerald-500/10 -[0_40px_100px_-20px_rgba(16,185,129,0.2)] overflow-hidden">
 <!-- Premium noise/grid overlay -->
 <div class="absolute inset-0 opacity-[0.03]" style="background-image: radial-gradient(circle at 1px 1px, rgba(255,255,255,0.3) 1px, transparent 1px); background-size: 24px 24px; pointer-events: none;"></div>
 
 <!-- Glow accents more dramatic -->
 <div class="absolute -top-48 -right-48 w-[500px] h-[500px] bg-emerald-500/6 rounded-[60px] blur-[180px] pointer-events-none"></div>
 <div class="absolute -bottom-48 -left-48 w-[500px] h-[500px] bg-emerald-500/6 rounded-[60px] blur-[180px] pointer-events-none"></div>
 <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[300px] bg-emerald-500/3 rounded-[60px] blur-[200px] pointer-events-none"></div>

 <!-- Top decorative line -->
 <div class="relative h-[2px] w-3/4 mx-auto bg-gradient-to-r from-transparent via-emerald-500/30 to-transparent"></div>

 <div class="relative z-10 px-6 pt-12 pb-8 md:px-12 lg:px-20 md:pt-16 md:pb-12">
 <!-- Top Row: Brand -->
 <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-6 mb-14 pb-10 border-b border-emerald-500/10">
 <div class="flex items-center gap-5">
 <div class="w-16 h-16 md:w-20 md:h-20 bg-gradient-to-br from-emerald-500 to-emerald-700 rounded-[1.5rem] md:rounded-[2rem] flex items-center justify-center text-white font-black italic text-3xl md:text-4xl -emerald-500/20 ring-2 ring-emerald-400/20 ring-offset-2 ring-offset-[#0a0f14]">
 N
 </div>
 <div>
 <h2 class="text-3xl md:text-5xl font-black text-white uppercase tracking-tighter leading-none">N3XT <span class="text-emerald-400">3D</span></h2>
 <p class="text-[9px] md:text-[10px] font-black text-emerald-500/60 uppercase tracking-[0.4em] md:tracking-[0.5em] mt-2 md:mt-3">Manufactura de Precisión</p>
 </div>
 </div>
 <p class="text-[10px] md:text-[12px] text-[#a4aea6] font-bold uppercase leading-relaxed tracking-wider max-w-md text-left md:text-right">
 El siguiente nivel de la fabricación digital en Colombia. Coleccionables de alto nivel y soluciones industriales.
 </p>
 </div>

 <!-- Content Grid -->
 <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-10 md:gap-12 lg:gap-16">
 <!-- COL 1: Navegación -->
 <div class="space-y-6 md:space-y-8">
 <div class="flex items-center gap-3">
 <div class="w-6 h-[2px] bg-emerald-500/60 rounded-[60px]"></div>
 <h5 class="text-[10px] font-black text-white/80 uppercase tracking-[0.4em]">Navegación</h5>
 </div>
 <ul class="space-y-4 md:space-y-5">
 <li v-for="link in [{n:'Inicio', p:'/'}, {n:'Galería', p:'/galeria'}, {n:'Catálogo', p:'/catalog'}, {n:'Cotizador', p:'/quote'}, {n:'Rastrear', p:'/track'}, {n:'Taller', p:'/admin'}, {n:'Contacto', p:'/project/init'}]" :key="link.n">
 <router-link :to="link.p" class="group flex items-center gap-4 text-[11px] font-black text-[#c3c4c5] hover:text-emerald-400 uppercase tracking-[0.25em] transition-all duration-300">
 <span class="w-5 h-px bg-gray-700 group-hover:w-10 group-hover:bg-emerald-500 transition-all duration-500"></span>
 <span class="group-hover:tracking-[0.35em] transition-all duration-300">{{ link.n }}</span>
 </router-link>
 </li>
 </ul>
 </div>

 <!-- COL 2: Conecta -->
 <div class="space-y-6 md:space-y-8">
 <div class="flex items-center gap-3">
 <div class="w-6 h-[2px] bg-emerald-500/60 rounded-[60px]"></div>
 <h5 class="text-[10px] font-black text-white/80 uppercase tracking-[0.4em]">Conecta</h5>
 </div>
 <div class="space-y-3 md:space-y-4">
 <a href="#" class="group flex items-center gap-5 p-5 bg-[#151a22]/[0.02] hover:bg-[#151a22]/[0.05] border border-white/[0.05] hover:border-emerald-500/30 rounded-[24px] transition-all duration-500">
 <div class="w-10 h-10 flex items-center justify-center bg-[#151a22]/5 rounded-[6px] group-hover:bg-black group-hover:text-white transition-all duration-500">
 <svg class="w-4 h-4 text-[#c3c4c5] group-hover:text-white" fill="currentColor" viewBox="0 0 384 382"><path d="M137.17 156.98v-15.56c-5.34-.73-10.76-1.18-16.29-1.18C54.23 140.24 0 194.47 0 261.13c0 40.9 20.43 77.09 51.61 98.97-20.12-21.6-32.46-50.53-32.46-82.31 0-65.7 52.69-119.28 118.03-120.81Z"/><path d="M140.02 333c29.74 0 54-23.66 55.1-53.13l.11-263.2h48.08c-1-5.41-1.55-10.97-1.55-16.67h-65.67l-.11 263.2c-1.1 29.47-25.36 53.13-55.1 53.13-9.24 0-17.95-2.31-25.61-6.34C105.3 323.9 121.6 333 140.02 333ZM333.13 106V91.37c-18.34 0-35.43-5.45-49.76-14.8 12.76 14.65 30.09 25.22 49.76 29.43Z"/><path d="M283.38 76.57c-13.98-16.05-22.47-37-22.47-59.91h-17.59c4.63 25.02 19.48 46.49 40.06 59.91ZM120.88 205.92c-30.44 0-55.21 24.77-55.21 55.21 0 21.2 12.03 39.62 29.6 48.86-6.55-9.08-10.45-20.18-10.45-32.2 0-30.44 24.77-55.21 55.21-55.21 5.68 0 11.13.94 16.29 2.55v-67.05c-5.34-.73-10.76-1.18-16.29-1.18-.96 0-1.9.05-2.85.07v51.49c-5.16-1.61-10.61-2.55-16.29-2.55Z"/><path d="M333.13 106v51.04c-34.05 0-65.61-10.89-91.37-29.38v133.47c0 66.66-54.23 120.88-120.88 120.88-25.76 0-49.64-8.12-69.28-21.91 22.08 23.71 53.54 38.57 88.42 38.57 66.66 0 120.88-54.23 120.88-120.88V144.33c25.76 18.49 57.32 29.38 91.37 29.38v-65.68c-6.57 0-12.97-.71-19.14-2.03Z"/></svg>
 </div>
 <div>
 <p class="text-[10px] font-black text-[#c3c4c5] group-hover:text-white uppercase tracking-widest transition-colors duration-300">TikTok</p>
 <p class="text-[7px] font-bold text-[#a4aea6] uppercase tracking-[0.25em]">@N3XT3D</p>
 </div>
 </a>
 <a href="https://wa.me/573118796416" target="_blank" class="group flex items-center gap-5 p-5 bg-[#151a22]/[0.02] hover:bg-[#151a22]/[0.05] border border-white/[0.05] hover:border-emerald-500/30 rounded-[24px] transition-all duration-500">
 <div class="w-10 h-10 flex items-center justify-center bg-[#151a22]/5 rounded-[6px] group-hover:bg-emerald-500 group-hover:text-white transition-all duration-500">
 <svg class="w-4 h-4 text-[#c3c4c5] group-hover:text-white" fill="currentColor" viewBox="0 0 362 362"><path d="M307.546 52.566C273.709 18.684 228.706.017 180.756 0 81.951 0 1.538 80.404 1.504 179.235c-.017 31.594 8.242 62.432 23.928 89.609L0 361.736l95.024-24.925c26.179 14.285 55.659 21.805 85.655 21.814h.077c98.788 0 179.21-80.413 179.244-179.244.017-47.898-18.608-92.926-52.454-126.807v-.008Zm-126.79 275.788h-.06c-26.73-.008-52.952-7.194-75.831-20.765l-5.44-3.231-56.391 14.791 15.05-54.981-3.542-5.638c-14.912-23.721-22.793-51.139-22.776-79.286.035-82.14 66.867-148.973 149.051-148.973 39.793.017 77.198 15.53 105.328 43.695 28.131 28.157 43.61 65.596 43.593 105.398-.035 82.149-66.867 148.982-148.982 148.982v.008Zm81.719-111.577c-4.478-2.243-26.497-13.073-30.606-14.568-4.108-1.496-7.09-2.243-10.073 2.243-2.982 4.487-11.568 14.577-14.181 17.559-2.613 2.991-5.226 3.361-9.704 1.117-4.477-2.243-18.908-6.97-36.02-22.226-13.313-11.878-22.304-26.54-24.916-31.027-2.613-4.486-.275-6.91 1.959-9.136 2.011-2.011 4.478-5.234 6.721-7.847 2.244-2.613 2.983-4.486 4.478-7.469 1.496-2.991.748-5.603-.369-7.847-1.118-2.243-10.073-24.289-13.812-33.253-3.636-8.732-7.331-7.546-10.073-7.692-2.613-.13-5.595-.155-8.586-.155-2.991 0-7.839 1.118-11.947 5.604-4.108 4.486-15.677 15.324-15.677 37.361s16.047 43.344 18.29 46.335c2.243 2.991 31.585 48.225 76.51 67.632 10.684 4.615 19.029 7.374 25.535 9.437 10.727 3.412 20.49 2.931 28.208 1.779 8.604-1.289 26.498-10.838 30.228-21.298 3.73-10.46 3.73-19.433 2.613-21.298-1.117-1.865-4.108-2.991-8.586-5.234l.008-.017Z"/></svg>
 </div>
 <div>
 <p class="text-[10px] font-black text-[#c3c4c5] group-hover:text-white uppercase tracking-widest transition-colors duration-300">WhatsApp</p>
 <p class="text-[7px] font-bold text-[#a4aea6] uppercase tracking-[0.25em]">Directo</p>
 </div>
 </a>
 </div>
 </div>

 <!-- COL 3: Soporte -->
 <div class="space-y-6 md:space-y-8">
 <div class="flex items-center gap-3">
 <div class="w-6 h-[2px] bg-emerald-500/60 rounded-[60px]"></div>
 <h5 class="text-[10px] font-black text-white/80 uppercase tracking-[0.4em]">Soporte</h5>
 </div>
 <div class="bg-[#151a22]/[0.02] border border-white/[0.05] rounded-[2.5rem] p-6 space-y-5">
 <div class="flex items-center gap-5">
 <div class="w-10 h-10 bg-emerald-500/10 rounded-[6px] flex items-center justify-center">
 <svg class="w-4 h-4 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
 </div>
 <div>
 <p class="text-[10px] font-black text-emerald-400 uppercase tracking-[0.3em]">Email</p>
 <p class="text-[10px] font-black text-[#a4aea6] uppercase tracking-[0.15em]">servicion3xt@gmail.com</p>
 </div>
 </div>
 <div class="flex items-center gap-5">
 <div class="w-10 h-10 bg-[#151a22]/5 rounded-[6px] flex items-center justify-center">
 <svg class="w-4 h-4 text-[#c3c4c5]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
 </div>
 <div>
 <p class="text-[10px] font-black text-[#c3c4c5] uppercase tracking-[0.3em]">Teléfono</p>
 <p class="text-[10px] font-black text-[#a4aea6] uppercase tracking-[0.15em]">+57 311 879 6416</p>
 </div>
 </div>
 </div>
 </div>

 <!-- COL 4: Estado -->
 <div class="space-y-6 md:space-y-8">
 <div class="flex items-center gap-3">
 <div class="w-6 h-[2px] bg-emerald-500/60 rounded-[60px]"></div>
 <h5 class="text-[10px] font-black text-white/80 uppercase tracking-[0.4em]">Estado</h5>
 </div>
 <div class="bg-emerald-500/[0.02] border border-emerald-500/10 rounded-[2.5rem] p-6 space-y-6">
 <div class="flex items-center justify-between">
 <div>
 <p class="text-[8px] font-black text-[#a4aea6] uppercase tracking-[0.3em] mb-3">Taller</p>
 <p class="text-[13px] font-black text-emerald-400 uppercase tracking-[0.25em]">Operativo 24/7</p>
 </div>
 <span class="relative flex h-4 w-4">
 <span class="animate-ping absolute inline-flex h-full w-full rounded-[60px] bg-emerald-400 opacity-75"></span>
 <span class="relative inline-flex rounded-[60px] h-4 w-4 bg-emerald-500"></span>
 </span>
 </div>
 <div class="pt-6 border-t border-emerald-500/10">
 <router-link to="/project/init" class="group flex items-center justify-between w-full py-2">
 <span class="text-[10px] font-black text-[#c3c4c5] group-hover:text-emerald-400 uppercase tracking-[0.35em] transition-all duration-300">Iniciar Proyecto</span>
 <svg class="w-5 h-5 text-emerald-500 group-hover:translate-x-1.5 transition-transform duration-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
 </router-link>
 </div>
 </div>
 </div>
 </div>
 </div>

 <!-- Bottom Bar -->
 <div class="relative border-t border-emerald-500/10 px-6 md:px-12 lg:px-20 py-6 flex flex-col md:flex-row justify-between items-center gap-5">
 <p class="text-[8px] md:text-[9px] font-black text-[#a4aea6] uppercase tracking-[0.4em] md:tracking-[0.45em]">&copy; 2026 N3XT 3D. Fabricación Avanzada.</p>
 <div class="flex gap-8">
 <router-link to="/privacy" class="text-[8px] md:text-[9px] font-black text-[#a4aea6] hover:text-emerald-400 uppercase tracking-[0.3em] md:tracking-[0.35em] transition-all duration-300 hover:tracking-[0.4em] md:hover:tracking-[0.45em]">Privacidad</router-link>
 <router-link to="/terms" class="text-[8px] md:text-[9px] font-black text-[#a4aea6] hover:text-emerald-400 uppercase tracking-[0.3em] md:tracking-[0.35em] transition-all duration-300 hover:tracking-[0.4em] md:hover:tracking-[0.45em]">Términos</router-link>
 </div>
 </div>
 </div>
 </div>
 </footer>
</template>

<style scoped>
footer::before {
 content: '';
 position: absolute;
 inset: 0;
 background-image: linear-gradient(to right, rgba(16, 185, 129, 0.03) 1px, transparent 1px),
 linear-gradient(to bottom, rgba(16, 185, 129, 0.03) 1px, transparent 1px);
 background-size: 40px 40px;
 pointer-events: none;
}
</style>
