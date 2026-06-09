<template>
        <div class="p-0 md:p-8 lg:p-12 animate-in fade-in slide-in-from-bottom-4 duration-500 max-w-[1600px] mx-auto space-y-12 pb-12">
            <!-- Header Seccional Premium -->
            <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 md:gap-8 mb-8 md:mb-16 bg-white/40 dark:bg-gray-950/40 backdrop-blur-xl p-4 md:p-12 rounded-[2rem] md:rounded-[4rem] border border-white/60 dark:border-white/5 shadow-2xl shadow-gray-200/30 dark:shadow-none relative overflow-hidden group">
              <div class="absolute -right-20 -top-20 w-64 h-64 bg-primary/5 rounded-full blur-3xl group-hover:bg-primary/10 transition-all duration-1000"></div>
              <div class="relative z-10">
                <h2 class="text-xl md:text-4xl font-black text-gray-900 dark:text-white tracking-tighter uppercase mb-2 md:mb-4">Configuración <span class="text-primary">Global</span></h2>
                <p class="text-[9px] md:text-sm text-gray-400 font-bold uppercase tracking-[0.2em] md:tracking-[0.3em] flex items-center gap-2 md:gap-3">
                  <span class="w-2 h-2 md:w-3 md:h-3 rounded-full bg-emerald-500 animate-pulse"></span>
                  Motor de Gestión Financiera • N3XT Engine
                </p>
              </div>

              <button class="bg-primary hover:bg-emerald-600 text-white w-full md:w-auto px-6 md:px-12 py-4 md:py-6 rounded-[1.5rem] md:rounded-[2rem] shadow-xl shadow-primary/20 flex items-center justify-center gap-3 md:gap-4 active:scale-95 transition-all duration-200 z-50 cursor-pointer text-sm md:text-lg" @click="() => saveSettings()">
                <span v-if="savingSettings" class="w-4 h-4 md:w-6 md:h-6 border-3 md:border-4 border-white/30 border-t-white rounded-full animate-spin"></span>
                <svg v-else class="w-4 h-4 md:w-6 md:h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/></svg>
                <span class="text-xs md:text-lg font-black uppercase tracking-widest">{{ savingSettings ? 'Guardando...' : 'Sincronizar Cambios' }}</span>
              </button>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-10 lg:gap-16">
              <!-- Columna 1: Identidad y Seguridad -->
              <div class="space-y-12">
                <!-- Branding Card -->
                <div class="bg-gray-900 p-8 md:p-12 rounded-[3rem] md:rounded-[4rem] border border-gray-700/50 shadow-xl shadow-black/30 relative overflow-hidden group">
                  <h3 class="text-xl font-black text-white uppercase tracking-tighter mb-10 border-l-8 border-primary pl-6">Identidad Visual</h3>
                  
                  <div class="space-y-10">
                    <div class="flex flex-col items-center justify-center p-12 bg-gray-800/80 backdrop-blur-sm rounded-[3rem] border-2 border-dashed border-gray-700 group/upload hover:bg-gray-700 hover:border-primary/50 transition-all duration-700 relative shadow-inner">
                      <div v-if="logoUrl && logoUrl !== '/logo.png'" class="relative group/img">
                        <img :src="logoUrl" class="h-32 w-auto object-contain mb-8 drop-shadow-[0_20px_50px_rgba(0,0,0,0.15)] group-hover/upload:scale-110 transition-transform duration-700" @error="(e) => { if (e.target) (e.target as HTMLImageElement).style.display = 'none' }">
                        <div class="absolute inset-0 bg-primary/20 rounded-full blur-3xl opacity-0 group-hover/img:opacity-30 transition-opacity pointer-events-none"></div>
                      </div>
                      <div v-else class="w-32 h-32 bg-gradient-to-br from-primary/80 via-emerald-600 to-teal-800 rounded-[2.5rem] mb-8 flex items-center justify-center relative overflow-hidden group shadow-2xl shadow-primary/20">
                        <div class="absolute inset-0 bg-[radial-gradient(circle_at_30%_25%,rgba(255,255,255,0.2),transparent_70%)]"></div>
                        <div class="absolute w-2.5 h-2.5 bg-white/30 rounded-full top-3 left-3 animate-ping"></div>
                        <div class="absolute inset-0 bg-gradient-to-t from-black/20 to-transparent"></div>
                        <div class="relative z-10 flex flex-col items-center">
                          <span class="text-white font-black italic text-4xl tracking-tighter drop-shadow-[0_4px_8px_rgba(0,0,0,0.4)]">N</span>
                          <span class="text-[8px] font-black text-white/50 uppercase tracking-[0.3em] mt-0.5">N3XT</span>
                        </div>
                        <div class="absolute -bottom-2 -right-2 w-12 h-12 bg-emerald-400/10 rounded-full blur-xl animate-pulse" style="animation-delay: 0.5s"></div>
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
                        @click="($refs.logoInput as HTMLInputElement).click()"
                      >
                        <span v-if="uploadingLogo" class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
                        <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                        {{ uploadingLogo ? 'Sincronizando...' : 'Actualizar Marca' }}
                      </button>
                    </div>

                    <div class="space-y-6">
                      <div class="group/input">
                        <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest ml-5 mb-2 block group-focus-within/input:text-primary transition-colors">Nombre Oficial</label>
                        <input v-model="localSettings.company.name" type="text" class="w-full bg-gray-800 border-2 border-transparent rounded-[2rem] px-8 py-5 text-sm font-bold text-white outline-none focus:bg-gray-700 focus:border-primary/20 focus:shadow-xl transition-all duration-300">
                      </div>
                      <div class="group/input">
                        <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest ml-5 mb-2 block group-focus-within/input:text-primary transition-colors">Slogan Corporativo</label>
                        <input v-model="localSettings.company.slogan" type="text" class="w-full bg-gray-800 border-2 border-transparent rounded-[2rem] px-8 py-5 text-sm font-bold text-white outline-none focus:bg-gray-700 focus:border-primary/20 focus:shadow-xl transition-all duration-300">
                      </div>
                    </div>

                    <!-- Workshop PIN Decommissioned -->
                  </div>
                </div>

                <!-- Danger Zone -->
                <div class="bg-rose-950/30 p-8 md:p-12 rounded-[3rem] border border-rose-900/30 shadow-xl shadow-rose-950/30 relative overflow-hidden group">
                  <div class="relative z-10 flex flex-col items-center">
                    <h3 class="text-xl font-black text-rose-300 uppercase tracking-tighter mb-2 text-center">Zona Crítica</h3>
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
                <div class="bg-gray-900 p-8 md:p-16 rounded-[4rem] border border-gray-700/50 shadow-2xl shadow-black/40 relative group">
                  <div class="flex flex-col md:flex-row md:items-center justify-between gap-8 mb-16">
                    <div class="border-l-8 border-primary pl-8">
                      <h3 class="text-2xl md:text-4xl font-black text-white tracking-tighter uppercase">Matriz de Costos Industriales</h3>
                      <p class="text-[10px] md:text-xs font-bold text-gray-400 uppercase tracking-[0.4em] mt-2">Algoritmo de Cálculo Operativo</p>
                    </div>
                    <div class="flex items-center gap-4 px-6 py-3 bg-emerald-900/30 text-emerald-400 rounded-[2rem] text-[10px] font-black uppercase tracking-widest animate-pulse border border-emerald-700/30 shadow-lg shadow-emerald-500/5">
                      <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 shadow-[0_0_10px_rgba(16,185,129,0.5)]"></span>
                      Motor Financiero Optimizado
                    </div>
                  </div>

                  <div class="grid grid-cols-1 xl:grid-cols-2 gap-12 lg:gap-16">
                    <!-- Infraestructura -->
                    <div class="space-y-10">

                      <div class="flex items-center gap-4 border-b border-gray-700/50 pb-6">
                        <div class="w-12 h-12 rounded-2xl bg-rose-500/10 text-rose-500 flex items-center justify-center shadow-inner">
                          <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
                        </div>
                        <div>
                          <p class="text-[12px] font-black text-gray-300 uppercase tracking-[0.2em]">Gastos de Planta</p>
                          <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Costos fijos operacionales</p>
                        </div>
                      </div>
                      <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <div v-for="(item, key) in { luz_hr: {label: 'Energía / Luz', sug: '926'}, depr_hr: {label: 'Depreciación', sug: '400'}, mant_hr: {label: 'Mantenimiento', sug: '700'}, etiquetas: {label: 'Etiquetas/Empaque', sug: '500'} }" :key="key"
                          class="bg-gray-800/60 p-5 rounded-2xl border border-gray-700/60 hover:border-rose-500/40 hover:bg-gray-800/80 hover:shadow-lg transition-all duration-300 group min-w-0">
                          <p class="text-[10px] font-bold text-rose-400/70 uppercase mb-3 group-hover:text-rose-400 transition-colors truncate" :title="item.label">{{ item.label }}</p>
                          <div class="flex items-baseline gap-1 min-w-0">
                            <span class="text-rose-500/50 font-bold text-base shrink-0">$</span>
                            <input v-model.number="localSettings.infra[key]" type="number" min="0" step="any"
                              class="flex-1 min-w-0 bg-transparent border-none p-0 font-black text-2xl text-white outline-none focus:text-rose-300 transition-colors no-spinner"
                              :placeholder="item.sug">
                          </div>
                          <p class="text-[9px] text-rose-400/40 font-medium mt-2 truncate">{{ defaultSuggestions[key] }}</p>
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
                            <input v-model.number="localSettings.infra.load_factor" type="number" step="0.1" min="0" max="1" class="w-28 bg-transparent border-none p-0 font-black text-5xl text-white outline-none tracking-tighter focus:text-primary transition-all">
                            <div class="flex-1 h-3 bg-white/5 rounded-full overflow-hidden border border-white/5 p-[2px]">
                                <div :style="{ width: (Math.min(Math.max(localSettings.infra.load_factor * 100, 0), 100)) + '%' }" class="h-full bg-primary transition-all duration-1000 shadow-[0_0_20px_rgba(var(--primary),0.8)] rounded-full"></div>
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
                      <div class="flex items-center gap-4 border-b border-gray-700/50 pb-6">
                        <div class="w-12 h-12 rounded-2xl bg-emerald-500/10 text-emerald-500 flex items-center justify-center shadow-inner">
                          <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" /></svg>
                        </div>
                        <div>
                          <p class="text-[12px] font-black text-gray-300 uppercase tracking-[0.2em]">Estrategia de Margen</p>
                          <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Riesgo & Utilidad Neta</p>
                        </div>
                      </div>
                      <div class="space-y-6">
                        <div class="bg-gradient-to-br from-emerald-950/60 to-gray-900 p-7 rounded-2xl border border-emerald-900/40 group shadow-lg shadow-emerald-500/5">
                          <p class="text-[10px] font-bold text-emerald-300 uppercase mb-1">Margen de Utilidad Objetivo</p>
                          <p class="text-[9px] font-medium text-gray-500 mb-4">Retorno sobre costo de producción</p>
                          <div class="flex items-baseline gap-2">
                              <input v-model.number="localSettings.oper.ganancia" type="number" min="0" max="500" step="1" class="w-24 bg-transparent border-none p-0 font-black text-4xl text-white outline-none focus:text-emerald-300 transition-colors no-spinner" placeholder="20">
                              <span class="text-emerald-400/80 font-black text-2xl">%</span>
                          </div>
                          <p class="text-[9px] text-emerald-400/40 font-medium mt-3">{{ defaultSuggestions.ganancia }}</p>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                          <div class="bg-gray-800/60 p-5 rounded-2xl border border-gray-700/60 hover:border-primary/40 hover:bg-gray-800/80 hover:shadow-lg transition-all duration-300 group min-w-0">
                            <p class="text-[10px] font-bold text-gray-400 uppercase mb-3 group-hover:text-primary transition-colors truncate" title="Mano de Obra ($/Hr)">Mano de Obra ($/Hr)</p>
                            <div class="flex items-baseline gap-1 min-w-0">
                              <span class="text-primary/50 font-bold text-base group-hover:text-primary/70 transition-colors shrink-0">$</span>
                              <input v-model.number="localSettings.prep.mano_obra_hr" type="number" min="0" step="any" placeholder="1000" class="flex-1 min-w-0 bg-transparent border-none p-0 font-black text-2xl text-white outline-none no-spinner">
                            </div>
                            <p class="text-[9px] text-primary/40 font-medium mt-2 truncate">{{ defaultSuggestions.mano_obra_hr }}</p>
                          </div>
                          
                          <div class="bg-gray-800/60 p-5 rounded-2xl border border-gray-700/60 hover:border-primary/40 hover:bg-gray-800/80 hover:shadow-lg transition-all duration-300 group min-w-0">
                            <p class="text-[10px] font-bold text-gray-400 uppercase mb-3 group-hover:text-primary transition-colors truncate" title="% Prep / Post">% Prep / Post</p>
                            <div class="flex items-baseline gap-1 min-w-0">
                              <input v-model.number="localSettings.prep.prep_time_pct" type="number" min="0" max="100" placeholder="10" class="flex-1 min-w-0 bg-transparent border-none p-0 font-black text-2xl text-white outline-none text-right no-spinner">
                              <span class="text-primary/50 font-bold text-base group-hover:text-primary/70 transition-colors shrink-0">%</span>
                            </div>
                            <p class="text-[9px] text-primary/40 font-medium mt-2 truncate">{{ defaultSuggestions.prep_time_pct }}</p>
                          </div>

                          <div class="bg-gray-800/60 p-5 rounded-2xl border border-rose-900/40 hover:border-rose-500/40 hover:bg-gray-800/80 hover:shadow-lg transition-all duration-300 group min-w-0">
                            <p class="text-[10px] font-bold text-gray-400 uppercase mb-3 group-hover:text-rose-400 transition-colors truncate" title="Fallas & Scrap">Fallas & Scrap</p>
                            <div class="flex items-baseline gap-1 min-w-0">
                              <input v-model.number="localSettings.oper.fallos" type="number" min="0" max="100" placeholder="5" class="flex-1 min-w-0 bg-transparent border-none p-0 font-black text-2xl text-white outline-none text-right no-spinner">
                              <span class="text-rose-500/50 font-bold text-base group-hover:text-rose-300/70 transition-colors shrink-0">%</span>
                            </div>
                            <p class="text-[9px] text-rose-500/40 font-medium mt-2 truncate">{{ defaultSuggestions.fallos }}</p>
                          </div>

                          <div class="bg-indigo-950/60 p-5 rounded-2xl border border-indigo-900/50 hover:border-indigo-400/50 transition-all duration-300 group min-w-0">
                             <p class="text-[10px] font-bold text-indigo-300 uppercase mb-3 truncate" title="IVA / Fiscal">IVA / Fiscal</p>
                             <div class="flex items-baseline gap-1 min-w-0">
                               <input v-model.number="localSettings.margin.iva" type="number" min="0" max="100" placeholder="19" class="flex-1 min-w-0 bg-transparent border-none p-0 font-black text-2xl text-white outline-none text-right focus:text-indigo-300 transition-colors no-spinner">
                               <span class="text-indigo-400/60 font-bold text-base shrink-0">%</span>
                             </div>
                             <p class="text-[9px] text-indigo-400/40 font-medium mt-2 truncate">{{ defaultSuggestions.iva }}</p>
                          </div>

                          <!-- % Logística -->
                          <div class="bg-cyan-950/60 p-5 rounded-2xl border border-cyan-900/50 hover:border-cyan-400/50 transition-all duration-300 group min-w-0">
                             <p class="text-[10px] font-bold text-cyan-300 uppercase mb-3 truncate" title="% Logística">% Logística</p>
                             <div class="flex items-baseline gap-1 min-w-0">
                               <input v-model.number="localSettings.oper.transporte" type="number" min="0" max="100" step="0.5" placeholder="5" class="flex-1 min-w-0 bg-transparent border-none p-0 font-black text-2xl text-white outline-none text-right focus:text-cyan-300 transition-colors no-spinner">
                               <span class="text-cyan-400/60 font-bold text-base shrink-0">%</span>
                             </div>
                             <p class="text-[9px] text-cyan-400/40 font-medium mt-2 truncate">{{ defaultSuggestions.transporte }}</p>
                          </div>

                          <!-- % Marketing -->
                          <div class="bg-pink-950/60 p-5 rounded-2xl border border-pink-900/50 hover:border-pink-400/50 transition-all duration-300 group min-w-0">
                             <p class="text-[10px] font-bold text-pink-300 uppercase mb-3 truncate" title="% Marketing">% Marketing</p>
                             <div class="flex items-baseline gap-1 min-w-0">
                               <input v-model.number="localSettings.oper.marketing" type="number" min="0" max="100" step="0.5" placeholder="10" class="flex-1 min-w-0 bg-transparent border-none p-0 font-black text-2xl text-white outline-none text-right focus:text-pink-300 transition-colors no-spinner">
                               <span class="text-pink-400/60 font-bold text-base shrink-0">%</span>
                             </div>
                             <p class="text-[9px] text-pink-400/40 font-medium mt-2 truncate">{{ defaultSuggestions.marketing }}</p>
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
                      <input v-model="localSettings.company[key]" :type="key === 'email' ? 'email' : 'text'" class="w-full bg-white/5 border border-white/10 rounded-[2rem] px-8 py-5 text-sm font-bold text-white outline-none focus:bg-white/10 focus:border-indigo-500 focus:shadow-[0_0_30px_rgba(99,102,241,0.1)] transition-all duration-300">
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
import { ref, reactive, watch } from 'vue'

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

// @codebuff unused
const webSubTab = ref('general')

// Valores sugeridos por defecto para orientar al usuario
const defaultSuggestions: Record<string, string> = {
  luz_hr: 'Sugerido: 926',
  depr_hr: 'Sugerido: 400',
  mant_hr: 'Sugerido: 700',
  etiquetas: 'Sugerido: 500',
  mano_obra_hr: 'Sugerido: 1,000',
  prep_time_pct: 'Sugerido: 10',
  ganancia: 'Sugerido: 20-50%',
  fallos: 'Sugerido: 5',
  iva: 'Sugerido: 19',
  transporte: 'Sugerido: 5',
  marketing: 'Sugerido: 10',
}

// Valores sugeridos para rellenar campos vacíos
const suggestedDefaults: Record<string, Record<string, number>> = {
  infra: { luz_hr: 926, depr_hr: 400, mant_hr: 700, etiquetas: 500 },
  prep: { mano_obra_hr: 1000 },
  oper: { transporte: 5, ganancia: 20, marketing: 10, fallos: 5 },
  margin: { iva: 19 },
}

const applyDefaults = (target: any) => {
  Object.keys(suggestedDefaults).forEach(category => {
    if (!target[category]) return
    Object.keys(suggestedDefaults[category]).forEach(key => {
      if (target[category][key] === 0 || target[category][key] === null || target[category][key] === undefined || target[category][key] === '') {
        target[category][key] = suggestedDefaults[category][key]
      }
    })
  })
}

// Create a local editable copy of settings to avoid prop mutations
const localSettings = reactive(JSON.parse(JSON.stringify(props.settings)))
applyDefaults(localSettings)

// Sync parent prop changes back to local copy
watch(() => props.settings, (newVal) => {
  if (newVal) {
    const fresh = JSON.parse(JSON.stringify(newVal))
    Object.keys(fresh).forEach(key => {
      localSettings[key] = fresh[key]
    })
    applyDefaults(localSettings)
  }
}, { deep: true, immediate: true })

const saveSettings = (silent = false) => {
  // Pass the local settings data so the parent can sync
  emit('save-settings', silent, JSON.parse(JSON.stringify(localSettings)))
}

const handleLogoUpload = (event: Event) => {
  emit('logo-upload', event)
}

const _handleCreateCatalogProduct = () => {
  emit('catalog-create')
}

const _handleResetCatalog = () => {
  emit('catalog-reset')
}

const handlePurgeAll = () => {
  emit('purge-all')
}
</script>


<style>
/* Range slider styling for SettingsPanel (not reliant on parent scoped CSS) */
input[type=range].range-slider {
  -webkit-appearance: none;
  appearance: none;
  background: transparent;
}
input[type=range].range-slider:focus {
  outline: none;
}
input[type=range].range-slider::-webkit-slider-runnable-track {
  width: 100%;
  height: 8px;
  cursor: pointer;
  background: #f1f5f9;
  border-radius: 4px;
}
input[type=range].range-slider::-webkit-slider-thumb {
  height: 20px;
  width: 20px;
  border-radius: 50%;
  background: #059669;
  cursor: pointer;
  -webkit-appearance: none;
  appearance: none;
  margin-top: -6px;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 0 12px rgba(5, 150, 105, 0.4);
  border: 2px solid white;
  transition: all 0.2s;
}
input[type=range].range-slider:hover::-webkit-slider-thumb {
  transform: scale(1.2);
}
input[type=range].range-slider::-moz-range-track {
  width: 100%;
  height: 8px;
  cursor: pointer;
  background: #f1f5f9;
  border-radius: 4px;
}
input[type=range].range-slider::-moz-range-thumb {
  height: 20px;
  width: 20px;
  border-radius: 50%;
  background: #059669;
  cursor: pointer;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 0 12px rgba(5, 150, 105, 0.4);
  border: 2px solid white;
}

/* Eliminar spinners de todos los inputs numéricos transparentes del panel */
input[type=number].no-spinner::-webkit-outer-spin-button,
input[type=number].no-spinner::-webkit-inner-spin-button {
  -webkit-appearance: none;
  margin: 0;
}
input[type=number].no-spinner {
  -moz-appearance: textfield;
  appearance: textfield;
}

/* Eliminar spinners de TODOS los inputs de tipo number con fondo transparente */
input[type=number]::-webkit-outer-spin-button,
input[type=number]::-webkit-inner-spin-button {
  -webkit-appearance: none;
  margin: 0;
}
input[type=number] {
  -moz-appearance: textfield;
  appearance: textfield;
}
</style>

