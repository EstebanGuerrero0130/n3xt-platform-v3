<template>
        <div class="p-0 md:p-8 lg:p-12 animate-in fade-in slide-in-from-bottom-4 duration-500 max-w-[1600px] mx-auto space-y-12 pb-12">
            <!-- Header Seccional Premium -->
            <div class="flex flex-col md:flex-row md:items-end justify-between gap-8 mb-16 bg-white/40 dark:bg-gray-950/40 backdrop-blur-xl p-8 md:p-12 rounded-[3rem] md:rounded-[4rem] border border-white/60 dark:border-white/5 shadow-2xl shadow-gray-200/30 dark:shadow-none relative overflow-hidden group">
              <div class="absolute -right-20 -top-20 w-64 h-64 bg-primary/5 rounded-full blur-3xl group-hover:bg-primary/10 transition-all duration-1000"></div>
              <div class="relative z-10">
                <h2 class="text-2xl md:text-4xl font-black text-gray-900 dark:text-white tracking-tighter uppercase mb-4">Configuración <span class="text-primary">Global</span></h2>
                <p class="text-[10px] md:text-sm text-gray-400 font-bold uppercase tracking-[0.3em] flex items-center gap-3">
                  <span class="w-3 h-3 rounded-full bg-emerald-500 animate-pulse"></span>
                  Motor de Gestión Financiera • N3XT Engine
                </p>
              </div>

              <button class="bg-primary hover:bg-emerald-600 text-white w-full md:w-auto px-12 py-6 rounded-[2rem] shadow-xl shadow-primary/20 flex items-center justify-center gap-4 active:scale-95 transition-all duration-200 z-50 cursor-pointer" @click="saveSettings">
                <span v-if="savingSettings" class="w-6 h-6 border-4 border-white/30 border-t-white rounded-full animate-spin"></span>
                <svg v-else class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/></svg>
                <span class="text-lg font-black uppercase tracking-widest">{{ savingSettings ? 'Guardando...' : 'Sincronizar Cambios' }}</span>
              </button>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-10 lg:gap-16">
              <!-- Columna 1: Identidad y Seguridad -->
              <div class="space-y-12">
                <!-- Branding Card -->
                <div class="bg-white dark:bg-gray-900 p-8 md:p-12 rounded-[3rem] md:rounded-[4rem] border border-gray-100 dark:border-white/5 shadow-xl shadow-gray-100/50 relative overflow-hidden group">
                  <h3 class="text-xl font-black text-gray-900 dark:text-white uppercase tracking-tighter mb-10 border-l-8 border-primary pl-6">Identidad Visual</h3>
                  
                  <div class="space-y-10">
                    <div class="flex flex-col items-center justify-center p-12 bg-gray-50/80 dark:bg-white/5 backdrop-blur-sm rounded-[3rem] border-2 border-dashed border-gray-200 dark:border-white/10 group/upload hover:bg-white dark:hover:bg-white/10 hover:border-primary/50 transition-all duration-700 relative shadow-inner">
                      <div v-if="props.settings.company_logo" class="relative group/img">
                        <img :src="logoUrl" class="h-32 w-auto object-contain mb-8 drop-shadow-[0_20px_50px_rgba(0,0,0,0.15)] group-hover/upload:scale-110 transition-transform duration-700" @error="(e) => e.target.src = '/logo.png'">
                        <div class="absolute inset-0 bg-primary/20 rounded-full blur-3xl opacity-0 group-hover/img:opacity-30 transition-opacity pointer-events-none"></div>
                      </div>
                      <div v-else class="w-32 h-32 bg-gray-200/50 dark:bg-white/5 rounded-[2.5rem] mb-8 flex items-center justify-center text-gray-400 border border-gray-200 dark:border-white/10">
                        <svg class="w-12 h-12 opacity-20" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                      </div>
                      
                      <input 
                        ref="logoInput"
                        type="file" 
                        class="hidden" 
                        accept="image/*" 
                        @change="handleLogoUpload"
                      >
                      
                      <button 
                        :disabled="uploadingLogo"
                        class="bg-gray-900 dark:bg-primary text-white px-12 py-5 rounded-2xl shadow-2xl text-[10px] font-black uppercase tracking-[0.2em] hover:scale-105 active:scale-95 transition-all duration-300 disabled:opacity-50 disabled:cursor-wait flex items-center gap-3"
                        @click="$refs.logoInput.click()"
                      >
                        <span v-if="uploadingLogo" class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
                        <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                        {{ uploadingLogo ? 'Sincronizando...' : 'Actualizar Marca' }}
                      </button>
                    </div>

                    <div class="space-y-6">
                      <div class="group/input">
                        <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest ml-5 mb-2 block group-focus-within/input:text-primary transition-colors">Nombre Oficial</label>
                        <input v-model="props.settings.company.name" type="text" class="w-full bg-gray-50 dark:bg-gray-800 border-2 border-transparent rounded-[2rem] px-8 py-5 text-sm font-bold text-gray-900 dark:text-white outline-none focus:bg-white dark:focus:bg-gray-700 focus:border-primary/20 focus:shadow-xl transition-all duration-300">
                      </div>
                      <div class="group/input">
                        <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest ml-5 mb-2 block group-focus-within/input:text-primary transition-colors">Slogan Corporativo</label>
                        <input v-model="props.settings.company.slogan" type="text" class="w-full bg-gray-50 dark:bg-gray-800 border-2 border-transparent rounded-[2rem] px-8 py-5 text-sm font-bold text-gray-900 dark:text-white outline-none focus:bg-white dark:focus:bg-gray-700 focus:border-primary/20 focus:shadow-xl transition-all duration-300">
                      </div>
                    </div>

                    <!-- Workshop PIN Decommissioned -->
                  </div>
                </div>

                <!-- Danger Zone -->
                <div class="bg-rose-50/50 p-8 md:p-12 rounded-[3rem] border border-rose-100 shadow-xl shadow-rose-100/20 relative overflow-hidden group">
                  <div class="relative z-10 flex flex-col items-center">
                    <h3 class="text-xl font-black text-rose-900 uppercase tracking-tighter mb-2 text-center">Zona Crítica</h3>
                    <p class="text-[10px] text-rose-400 font-bold uppercase mb-8 tracking-widest text-center">Purga y mantenimiento profundo</p>

                    <button 
                        class="px-8 py-4 bg-rose-500 hover:bg-rose-600 text-white rounded-2xl font-black uppercase tracking-widest text-xs transition-all duration-300 shadow-lg shadow-rose-500/30 hover:shadow-rose-500/50 active:scale-95 flex items-center gap-3 z-20 relative"
                        @click="handlePurgeAll"
                    >
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                        Ejecutar Purga Total
                    </button>

                    <p class="text-[10px] text-rose-300 text-center mt-8 font-bold uppercase tracking-[0.3em] opacity-80">N3XT OS v3.2.4 Premium • Build Stable</p>
                  </div>
                  <div class="absolute -bottom-12 -right-12 opacity-[0.03] text-rose-900 group-hover:scale-110 transition-transform duration-1000">
                    <svg class="w-56 h-56" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.47 2 2 6.47 2 12s4.47 10 10 10 10-4.47 10-10S17.53 2 12 2zm5 13.59L15.59 17 12 13.41 8.41 17 7 15.59 10.59 12 7 8.41 8.41 7 12 10.59 15.59 7 17 8.41 13.41 12 17 15.59z"/></svg>
                  </div>
                </div>
              </div>

              <!-- Columna 2 y 3: Configuración Maestra -->
              <div class="lg:col-span-2 space-y-12">
                <!-- Master Costs Card -->
                <div class="bg-white p-8 md:p-16 rounded-[4rem] border border-gray-100 shadow-2xl shadow-gray-200/40 relative group">
                  <div class="flex flex-col md:flex-row md:items-center justify-between gap-8 mb-16">
                    <div class="border-l-8 border-primary pl-8">
                      <h3 class="text-2xl md:text-4xl font-black text-gray-900 dark:text-white tracking-tighter uppercase">Matriz de Costos Industriales</h3>
                      <p class="text-[10px] md:text-xs font-bold text-gray-400 uppercase tracking-[0.4em] mt-2">Algoritmo de Cálculo Operativo</p>
                    </div>
                    <div class="flex items-center gap-4 px-6 py-3 bg-emerald-50 text-emerald-600 rounded-[2rem] text-[10px] font-black uppercase tracking-widest animate-pulse border border-emerald-100/50 shadow-lg shadow-emerald-500/5">
                      <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 shadow-[0_0_10px_rgba(16,185,129,0.5)]"></span>
                      Motor Financiero Optimizado
                    </div>
                  </div>

                  <div class="grid grid-cols-1 xl:grid-cols-2 gap-12 lg:gap-16">
                    <!-- Infraestructura -->
                    <div class="space-y-10">

                      <div class="flex items-center gap-4 border-b border-gray-100 pb-6">
                        <div class="w-12 h-12 rounded-2xl bg-rose-500/10 text-rose-500 flex items-center justify-center shadow-inner">
                          <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
                        </div>
                        <div>
                          <p class="text-[12px] font-black text-gray-900 uppercase tracking-[0.2em]">Gastos de Planta</p>
                          <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Costos fijos operacionales</p>
                        </div>
                      </div>
                      <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <div v-for="(label, key) in { luz_hr: 'Energía / Luz', depr_hr: 'Depreciación', mant_hr: 'Mantenimiento', etiquetas: 'Etiquetas/Empaque' }" :key="key" class="bg-gray-50/50 p-8 rounded-[2.5rem] border border-gray-100 hover:border-rose-500/30 hover:bg-white hover:shadow-2xl hover:shadow-rose-500/10 transition-all duration-500 group relative overflow-hidden">
                          <div class="absolute -right-6 -top-6 w-20 h-20 bg-rose-500/5 rounded-full blur-2xl group-hover:bg-rose-500/10 transition-all duration-700"></div>
                          <p class="text-[10px] font-black text-gray-400 uppercase mb-4 group-hover:text-rose-500 transition-colors tracking-widest">{{ label }}</p>
                          <div class="flex items-end gap-3">
                            <span class="text-gray-300 font-black text-2xl leading-none mb-1 group-hover:text-rose-300 transition-colors">$</span>
                            <input v-model.number="props.settings.infra[key]" type="number" min="0" step="any" class="w-full bg-transparent border-none p-0 font-black text-4xl text-gray-900 outline-none tracking-tighter focus:text-rose-600 transition-colors">
                          </div>
                        </div>
                        
                        <!-- Factor de Carga con Explicación Premium -->
                        <div class="col-span-1 sm:col-span-2 bg-gray-900 p-8 md:p-10 rounded-[3rem] border border-gray-800 hover:border-primary transition-all duration-700 group relative overflow-hidden shadow-2xl shadow-black/40">
                          <div class="absolute -right-10 -top-10 w-40 h-40 bg-primary/10 rounded-full blur-[80px] group-hover:bg-primary/20 transition-all duration-1000"></div>
                          <div class="flex justify-between items-start mb-6 relative z-10">
                            <div>
                              <p class="text-[10px] font-black text-gray-400 uppercase tracking-[0.3em] group-hover:text-primary transition-colors">Factor de Carga Operativa</p>
                              <p class="text-[8px] font-bold text-gray-600 uppercase tracking-widest mt-1">Eficiencia Real vs Teórica</p>
                            </div>
                            <span class="text-[10px] font-black bg-primary/20 text-primary px-4 py-1.5 rounded-full border border-primary/20">Modo Alta Precisión</span>
                          </div>
                          <div class="flex items-center gap-8 relative z-10">
                            <input v-model.number="props.settings.infra.load_factor" type="number" step="0.1" min="0" max="1" class="w-28 bg-transparent border-none p-0 font-black text-5xl text-white outline-none tracking-tighter focus:text-primary transition-all">
                            <div class="flex-1 h-3 bg-white/5 rounded-full overflow-hidden border border-white/5 p-[2px]">
                                <div :style="{ width: (Math.min(Math.max(settings.infra.load_factor * 100, 0), 100)) + '%' }" class="h-full bg-primary transition-all duration-1000 shadow-[0_0_20px_rgba(var(--primary),0.8)] rounded-full"></div>
                            </div>
                          </div>
                          <p class="text-[10px] font-medium text-gray-500 mt-6 leading-relaxed uppercase tracking-widest opacity-80">
                            Ajuste de consumo para compensar tiempos muertos. <br>
                            <span class="text-primary font-black">Estándar N3XT: 0.4 (40%)</span>
                          </p>
                        </div>
                      </div>
                    </div>

                    <!-- Operativos -->
                    <div class="space-y-10">
                      <div class="flex items-center gap-4 border-b border-gray-100 pb-6">
                        <div class="w-12 h-12 rounded-2xl bg-emerald-500/10 text-emerald-500 flex items-center justify-center shadow-inner">
                          <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" /></svg>
                        </div>
                        <div>
                          <p class="text-[12px] font-black text-gray-900 uppercase tracking-[0.2em]">Estrategia de Margen</p>
                          <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Riesgo & Utilidad Neta</p>
                        </div>
                      </div>
                      <div class="space-y-6">
                        <div class="bg-gradient-to-br from-emerald-50/50 to-white p-10 rounded-[3rem] border border-emerald-100 group relative overflow-hidden shadow-xl shadow-emerald-500/5">
                          <div class="absolute -right-12 -bottom-12 w-48 h-48 bg-emerald-500/10 rounded-full blur-3xl group-hover:scale-125 transition-transform duration-1000"></div>
                          <div class="flex justify-between items-center mb-8 relative z-10">
                            <div>
                                <p class="text-[12px] font-black text-emerald-700 uppercase tracking-[0.2em]">Margen de Utilidad Objetivo</p>
                                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mt-1">Retorno sobre costo de producción</p>
                            </div>
                            <div class="px-8 py-4 bg-emerald-500 text-white rounded-[2rem] font-black text-3xl shadow-2xl shadow-emerald-500/40 border border-emerald-400/30 transform group-hover:scale-110 transition-all duration-500">
                                {{ settings.oper.ganancia }}%
                            </div>
                          </div>
                          <input v-model.number="props.settings.oper.ganancia" type="range" min="0" max="500" class="w-full h-3 bg-emerald-100 rounded-full appearance-none cursor-pointer accent-emerald-500 shadow-inner">
                        </div>
                        
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                          <div class="bg-gray-50/50 p-8 rounded-[2.5rem] border border-gray-100 hover:border-primary/30 hover:bg-white hover:shadow-2xl transition-all duration-500 group">
                            <p class="text-[10px] font-black text-gray-400 uppercase mb-4 group-hover:text-primary transition-colors tracking-widest">Mano de Obra ($/Hr)</p>
                            <div class="flex items-end gap-3">
                              <span class="text-gray-300 font-black text-2xl leading-none mb-1 group-hover:text-primary/50 transition-colors">$</span>
                              <input v-model.number="props.settings.prep.mano_obra_hr" type="number" min="0" step="any" class="w-full bg-transparent border-none p-0 font-black text-3xl text-gray-900 outline-none tracking-tighter">
                            </div>
                          </div>
                          
                          <div class="bg-gray-50/50 p-8 rounded-[2.5rem] border border-gray-100 hover:border-primary/30 hover:bg-white hover:shadow-2xl transition-all duration-500 group">
                            <p class="text-[10px] font-black text-gray-400 uppercase mb-4 group-hover:text-primary transition-colors tracking-widest">% Prep / Post</p>
                            <div class="flex items-end gap-3">
                              <input v-model.number="props.settings.prep.prep_time_pct" type="number" min="0" max="100" class="w-full bg-transparent border-none p-0 font-black text-3xl text-gray-900 outline-none tracking-tighter text-right">
                              <span class="text-gray-300 font-black text-2xl leading-none mb-1 group-hover:text-primary/50 transition-colors">%</span>
                            </div>
                          </div>

                          <div class="bg-gray-50/50 p-8 rounded-[2.5rem] border border-gray-100 hover:border-rose-500/30 hover:bg-white hover:shadow-2xl transition-all duration-500 group">
                            <p class="text-[10px] font-black text-gray-400 uppercase mb-4 group-hover:text-rose-500 transition-colors tracking-widest">Fallas & Scrap</p>
                            <div class="flex items-end gap-3">
                              <input v-model.number="props.settings.oper.fallos" type="number" min="0" max="100" class="w-full bg-transparent border-none p-0 font-black text-3xl text-gray-900 outline-none tracking-tighter text-right">
                              <span class="text-gray-300 font-black text-2xl leading-none mb-1 group-hover:text-rose-300 transition-colors">%</span>
                            </div>
                          </div>

                          <div class="bg-indigo-950 p-8 rounded-[2.5rem] border border-indigo-900 hover:border-indigo-400/50 transition-all duration-700 group relative overflow-hidden shadow-xl shadow-indigo-900/20">
                             <div class="absolute -right-6 -top-6 w-24 h-24 bg-white/5 rounded-full blur-2xl group-hover:bg-white/10 transition-all duration-1000"></div>
                             <p class="text-[10px] font-black text-indigo-300 uppercase mb-4 tracking-widest">IVA / Fiscal</p>
                             <div class="flex items-end gap-3">
                               <input v-model.number="props.settings.margin.iva" type="number" min="0" max="100" class="w-full bg-transparent border-none p-0 font-black text-3xl text-white outline-none tracking-tighter text-right focus:text-indigo-400">
                               <span class="text-indigo-500 font-black text-2xl leading-none mb-1">%</span>
                             </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div> <!-- Cierre Grid Principal de Costos (2087) -->
                </div> <!-- Cierre Master Costs Card (2079) -->
              </div> <!-- Cierre Columna 2 y 3 (2077) -->


              <!-- Facturación y Fiscal Card -->

              <div class="lg:col-span-2 bg-gray-900 p-8 md:p-16 rounded-[4rem] shadow-2xl relative overflow-hidden h-fit border border-white/5">
                <div class="absolute -right-20 -bottom-20 w-96 h-96 bg-indigo-500/5 rounded-full blur-[120px] pointer-events-none"></div>
                <div class="relative z-10">
                  <h3 class="text-2xl md:text-4xl font-black text-white uppercase tracking-tighter mb-12 border-l-8 border-indigo-500 pl-8">Parámetros Fiscales & Despacho</h3>
                  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-8">

                    <div v-for="(label, key) in { nit: 'NIT / Identificación', phone: 'WhatsApp / Tel', email: 'Email de Ventas', address: 'Dirección de Taller', website: 'Website / Portafolio' }" :key="key" class="space-y-3 group/fiscal">
                      <label class="text-[10px] font-black text-gray-500 uppercase tracking-[0.2em] ml-6 group-focus-within/fiscal:text-indigo-400 transition-colors">{{ label }}</label>
                      <input v-model="props.settings.company[key]" :type="key === 'email' ? 'email' : 'text'" class="w-full bg-white/5 border border-white/10 rounded-[2rem] px-8 py-5 text-sm font-bold text-white outline-none focus:bg-white/10 focus:border-indigo-500 focus:shadow-[0_0_30px_rgba(99,102,241,0.1)] transition-all duration-300">
                    </div>
                  </div>
                </div>
                <div class="absolute -top-20 -right-20 opacity-[0.03] pointer-events-none transform rotate-12">
                  <svg class="w-96 h-96 text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M19 3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-5 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z"/></svg>
                </div>
              </div> <!-- Cierre lg:col-span-2 -->



            </div> <!-- Cierre grid Principal de Ajustes -->
        </div> <!-- Cierre wrapper principal -->
</template>

<script setup lang="ts">
import { ref } from 'vue'

const props = defineProps({
  settings: { type: Object, required: true },
  savingSettings: { type: Boolean, default: false },
  uploadingLogo: { type: Boolean, default: false },
  logoUrl: { type: String, default: '/logo.png' },
  logoError: { type: Boolean, default: false },
  isLocalhost: { type: Boolean, default: false },
  showNotify: { type: Function, default: null },
  askConfirm: { type: Function, default: null },
})

const emit = defineEmits([
  'save-settings',
  'logo-upload',
  'purge-all',
  'catalog-create',
  'catalog-reset',
  'scroll-catalog-item',
])

const webSubTab = ref('general')

const saveSettings = (silent = false) => {
  // Delegate to parent which handles API call, SEO optimization, and confirm dialog
  emit('save-settings', silent)
}

const handleLogoUpload = (event) => {
  emit('logo-upload', event)
}

const handleCreateCatalogProduct = () => {
  emit('catalog-create')
}

const handleResetCatalog = () => {
  emit('catalog-reset')
}

const handlePurgeAll = () => {
  emit('purge-all')
}
</script>
