<script setup lang="ts">
import { computed, type PropType } from 'vue'

const props = defineProps({
  printers: { type: Array as PropType<any[]>, required: true },
  loading: { type: Boolean, default: false }
})

defineEmits(['sync', 'add-printer', 'update-printer-status', 'maintenance-complete', 'edit-printer', 'delete-printer'])

const getStatusLabel = (status: any) => {
  const labels = {
    'idle': 'Libre / Lista',
    'printing': 'OCUPADA',
    'maintenance': 'Mantenimiento',
    'offline': 'Desconectada'
  }
  return labels[status] || status
}

const getStatusColor = (status: any) => {
  const colors = {
    'idle': 'bg-emerald-500',
    'printing': 'bg-amber-500',
    'maintenance': 'bg-rose-500',
    'offline': 'bg-slate-400'
  }
  return colors[status] || 'bg-gray-400'
}

const getMaintenanceProgress = (printer: any) => {
  const run = printer.total_hours_run || 0
  const interval = printer.maintenance_interval_h || 200
  return Math.min((run / interval) * 100, 100)
}

const isMaintenanceNear = (printer: any) => {
  const run = printer.total_hours_run || 0
  const interval = printer.maintenance_interval_h || 200
  return (run / interval) >= 0.8
}

const isMaintenanceOverdue = (printer: any) => {
  if (!printer.next_maintenance) return false
  const today = new Date().toISOString().split('T')[0]
  return printer.next_maintenance <= today
}

const overduePrintersCount = computed(() => {
  return props.printers.filter((p: any) => isMaintenanceOverdue(p)).length
})
</script>

<template>
  <div class="animate-fade-in">
    <!-- Main Header -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-end gap-4 md:gap-6 mb-8 md:mb-12 lg:mb-20 px-4 md:px-8 lg:px-10">
      <div>
        <h2 class="text-2xl md:text-5xl font-black text-gray-900 dark:text-white tracking-tighter uppercase mb-1 md:mb-2">Monitor de Granja</h2>
        <div class="flex items-center gap-2 md:gap-3">
          <div class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></div>
          <p class="text-gray-400 dark:text-gray-300 font-bold uppercase tracking-widest text-[9px] md:text-[10px]">Base de Datos Unificada</p>
        </div>
      </div>
      <div class="flex gap-3 md:gap-4 w-full md:w-auto">
        <button :disabled="loading" class="p-3 md:p-5 bg-white dark:bg-[#151a22] rounded-2xl md:rounded-[24px] border border-gray-100 dark:border-[#21262d] shadow-xl text-gray-400 dark:text-gray-300" @click="$emit('sync')">
          <svg xmlns="http://www.w3.org/2000/svg" :class="`h-4 md:h-6 w-4 md:w-6 ${loading ? 'animate-spin' : ''}`" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
        </button>
        <button class="btn-primary flex-1 md:flex-none px-4 md:px-10 py-3 md:py-5" @click="$emit('add-printer')">
          <svg class="w-4 h-4 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg> <span class="hidden sm:inline">Registrar Maquinaria</span><span class="sm:hidden">Añadir</span>
        </button>
      </div>
    </div>
    
    <!-- Emergency Maintenance Alert -->
    <div
v-if="overduePrintersCount > 0" 
         class="mx-4 md:mx-8 lg:mx-10 mb-8 md:mb-16 p-4 md:p-8 bg-rose-50 dark:bg-rose-500/10 border-2 border-rose-100 dark:border-rose-500/20 rounded-[20px] md:rounded-[24px] flex flex-col md:flex-row items-center justify-between gap-4 md:gap-6 animate-zoom-in">            <div class="flex items-center gap-4 md:gap-8">
                <div class="w-10 h-10 md:w-12 md:h-12 bg-rose-500 text-white rounded-xl md:rounded-2xl flex items-center justify-center shadow-lg shadow-rose-500/20 shrink-0">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            </div>
            <div>
                <h3 class="text-lg md:text-2xl font-black text-rose-900 dark:text-rose-100 uppercase tracking-tighter mb-1">Alerta de Mantenimiento</h3>
                <p class="text-[9px] md:text-[10px] font-black text-rose-400 dark:text-rose-300/60 uppercase tracking-[0.2em]">Hay {{ overduePrintersCount }} impresoras que requieren revisión.</p>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <div class="flex -space-x-4">
                <div
v-for="p in printers.filter(isMaintenanceOverdue).slice(0, 3)" :key="p.id" 
                     class="w-12 h-12 rounded-full border-4 border-white dark:border-gray-900 bg-rose-100 dark:bg-rose-500/20 flex items-center justify-center text-xs font-black text-rose-500">
                    {{ p.name.substring(0,2) }}
                </div>
            </div>
            <p v-if="overduePrintersCount > 3" class="text-[10px] font-black text-rose-300 uppercase pl-2">+{{ overduePrintersCount - 3 }} más</p>
        </div>
    </div>

    <!-- Skeleton Loading: mientras carga maquinaria -->
    <div v-if="loading" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8 lg:gap-12 px-6 lg:px-8 pb-16 animate-pulse">
      <div v-for="i in 6" :key="'skel-'+i" class="bg-gray-100 dark:bg-[#1a1f2e] p-8 lg:p-12 rounded-[24px] border border-gray-200 dark:border-[#252b3a] flex flex-col">
        <div class="flex items-start justify-between mb-8 w-full">
          <div class="flex gap-6">
            <div class="w-20 h-20 md:w-28 md:h-28 bg-gray-200 dark:bg-[#2a3040] rounded-[2.5rem] md:rounded-[3.5rem]"></div>
            <div>
              <div class="h-5 bg-gray-200 dark:bg-[#2a3040] rounded-full w-24 mb-2"></div>
              <div class="h-3 bg-gray-200 dark:bg-[#2a3040] rounded-full w-16"></div>
            </div>
          </div>
          <div class="h-8 bg-gray-200 dark:bg-[#2a3040] rounded-2xl w-24"></div>
        </div>
        <div class="flex-1 space-y-4 mb-6">
          <div class="bg-gray-200/50 dark:bg-[#2a3040]/30 p-6 md:p-8 rounded-[24px] space-y-4">
            <div class="h-3 bg-gray-200 dark:bg-[#2a3040] rounded-full w-32"></div>
            <div class="h-3 bg-gray-200 dark:bg-[#2a3040] rounded-full"></div>
            <div class="h-8 bg-gray-200 dark:bg-[#2a3040] rounded-xl"></div>
          </div>
        </div>
        <div class="h-12 bg-gray-200 dark:bg-[#2a3040] rounded-2xl mb-3"></div>
        <div class="flex gap-3">
          <div class="flex-1 h-12 bg-gray-200 dark:bg-[#2a3040] rounded-2xl"></div>
          <div class="w-12 h-12 bg-gray-200 dark:bg-[#2a3040] rounded-2xl"></div>
        </div>
      </div>
    </div>

    <!-- Machines Grid -->
    <div v-if="!loading && printers.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 md:gap-6 lg:gap-12 px-4 md:px-6 lg:px-8 pb-16">
      <div
v-for="(printer, index) in printers" :key="printer.id" 
        :class="['bg-white dark:bg-[#151a22] p-4 md:p-8 lg:p-12 rounded-[20px] md:rounded-[24px] border border-gray-100 dark:border-[#21262d] shadow-sm group relative overflow-hidden flex flex-col w-full animate-slide-up transition-all hover:shadow-2xl hover:border-primary/30 dark:hover:border-primary/20', 'animate-stagger-' + (index % 4 + 1)]"
      >
        <!-- Maintenance Alert Pulse -->
        <div v-if="isMaintenanceNear(printer)" class="absolute top-0 left-0 w-full h-1.5 bg-rose-500 animate-pulse z-10"></div>
        
        <!-- Header Section -->          <div class="flex items-start justify-between mb-8 md:mb-10 w-full">
          <div class="flex gap-6">
            <div :class="['w-20 h-20 md:w-28 md:h-28 rounded-[2.5rem] md:rounded-[3.5rem] flex items-center justify-center shadow-2xl relative overflow-hidden', printer.technology === 'FDM' ? 'bg-gradient-to-br from-emerald-400 to-emerald-600 shadow-emerald-500/20' : 'bg-gradient-to-br from-indigo-500 to-indigo-700 shadow-indigo-500/20']">
              <svg v-if="printer.technology === 'FDM'" class="w-10 h-10 md:w-16 md:h-16 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
              </svg>
              <svg v-else class="w-10 h-10 md:w-16 md:h-16 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.618.309a6 6 0 01-3.86.517l-2.387-.477a2 2 0 00-1.022.547l-1.16 1.16a2 2 0 00.442 3.102l1.644.822a6 6 0 005.366 0l1.644-.822a2 2 0 00.442-3.102l-1.16-1.16zM15.75 9a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z" />
              </svg>
            </div>
            <div>
              <h3 class="text-xl md:text-3xl font-black text-gray-900 dark:text-white tracking-tighter leading-none mb-1">{{ printer.name }}</h3>
              <p class="text-[10px] md:text-[11px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-widest mb-3">{{ printer.model }}</p>
              <div v-if="isMaintenanceNear(printer)" class="inline-flex items-center gap-2 px-3 py-1.5 bg-rose-500 text-white rounded-xl text-[8px] md:text-[10px] font-black uppercase tracking-widest animate-pulse shadow-lg shadow-rose-500/20">
                Revision Urgente
              </div>
            </div>
          </div>
          <div class="flex flex-col items-end shrink-0">
            <span :class="['px-6 py-3 rounded-2xl text-[10px] font-black uppercase tracking-[0.2em] text-white shadow-2xl transition-all duration-500', getStatusColor(printer.status), printer.status === 'printing' ? 'animate-pulse' : '']">
              {{ getStatusLabel(printer.status) }}
            </span>
          </div>
        </div>

        <!-- Middle Section: Glassmorphism Info -->          <div class="flex-1 space-y-4 md:space-y-6 mb-4 md:mb-10 min-h-0">
          <div class="bg-gray-50/80 dark:bg-[#0d1117]/60 backdrop-blur-sm p-4 md:p-8 rounded-[20px] md:rounded-[24px] border border-gray-100/50 dark:border-[#21262d] space-y-4 md:space-y-6">
                <div class="space-y-4">
                    <div class="flex justify-between items-center px-1">
                        <span class="text-[10px] font-black text-gray-400 dark:text-gray-300 uppercase tracking-widest">Vida Útil: {{ Math.round(printer.total_hours_run || 0) }}h</span>
                        <span :class="isMaintenanceNear(printer) ? 'text-rose-500 animate-pulse' : 'text-emerald-500'" class="text-xs font-black uppercase">
                            {{ Math.round(getMaintenanceProgress(printer)) }}%
                        </span>
                    </div>
                    <div class="h-3 bg-white dark:bg-[#0d1117] rounded-full overflow-hidden shadow-inner p-0.5 border border-gray-100 dark:border-[#21262d] relative">
                        <div
:class="[
                            getMaintenanceProgress(printer) >= 100 ? 'bg-rose-600' : 
                            getMaintenanceProgress(printer) >= 80 ? 'bg-amber-500' : 'bg-emerald-500'
                        ]" class="h-full rounded-full" :style="{ width: getMaintenanceProgress(printer) + '%' }"></div>
                    </div>
                    <p class="text-[10px] font-bold text-gray-400 dark:text-gray-500 uppercase text-right tracking-widest mt-1 italic">Calendario: {{ printer.next_maintenance || 'Sin fecha' }}</p>
                </div>

            <!-- Manual Control Panel -->
            <div class="pt-4 space-y-4">
                <p class="text-[8px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-widest text-center">Protocolo Operativo Manual</p>                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-1.5 p-1.5 bg-white dark:bg-[#0d1117] rounded-[1.5rem] shadow-sm border border-gray-100 dark:border-[#21262d]">
                    <button 
                        :class="[printer.status === 'idle' ? 'bg-emerald-500 text-white shadow-lg shadow-emerald-500/30' : 'text-gray-400 dark:text-gray-300 hover:bg-emerald-500/10 hover:text-emerald-600 dark:hover:text-emerald-400']"
                        class="py-3 md:py-3 rounded-2xl text-[9px] md:text-[8px] font-black uppercase tracking-widest transition-all duration-300 ease-out"
                        @click="$emit('update-printer-status', { id: printer.id, status: 'idle' })"
                    >
                        Libre
                    </button>
                    <button 
                        :class="[printer.status === 'printing' ? 'bg-amber-500 text-white shadow-lg shadow-amber-500/30' : 'text-gray-400 dark:text-gray-300 hover:bg-amber-500/10 hover:text-amber-600 dark:hover:text-amber-400']"
                        class="py-3 md:py-3 rounded-2xl text-[9px] md:text-[8px] font-black uppercase tracking-widest transition-all duration-300 ease-out"
                        @click="$emit('update-printer-status', { id: printer.id, status: 'printing' })"
                    >
                        Ocupada
                    </button>
                    <button 
                        :class="[printer.status === 'maintenance' ? 'bg-rose-500 text-white shadow-lg shadow-rose-500/30' : 'text-gray-400 dark:text-gray-300 hover:bg-rose-500/10 hover:text-rose-600 dark:hover:text-rose-400']"
                        class="py-3 md:py-3 rounded-2xl text-[9px] md:text-[8px] font-black uppercase tracking-widest transition-all duration-300 ease-out"
                        @click="$emit('update-printer-status', { id: printer.id, status: 'maintenance' })"
                    >
                        MANT.
                    </button>
                    <button 
                        :class="[printer.status === 'offline' ? 'bg-slate-400 text-white shadow-lg shadow-slate-400/30' : 'text-gray-400 dark:text-gray-300 hover:bg-slate-400/10 hover:text-slate-600 dark:hover:text-slate-400']"
                        class="py-3 md:py-3 rounded-2xl text-[9px] md:text-[8px] font-black uppercase tracking-widest transition-all duration-300 ease-out"
                        @click="$emit('update-printer-status', { id: printer.id, status: 'offline' })"
                    >
                        OFF
                    </button>
                </div>
            </div>
          </div>
        </div>

        <!-- Footer Actions -->
        <div class="flex flex-col gap-3 pt-6 border-t border-gray-50 dark:border-[#21262d] mt-auto">
          <button 
            class="w-full py-4 bg-emerald-50 dark:bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 rounded-2xl text-[10px] font-black uppercase tracking-widest hover:bg-emerald-500 hover:text-white transition-all flex items-center justify-center gap-2 group" 
            @click="$emit('maintenance-complete', printer.id)"
          >
            <svg class="w-4 h-4 group-hover:rotate-12 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg> Resetear Ciclo Vida
          </button>
          
          <div class="flex gap-3">
            <button class="btn-primary flex-1 py-4" @click="$emit('edit-printer', printer)">
              Configurar
            </button>
            <button class="icon-btn icon-btn-danger w-12 h-12" @click="$emit('delete-printer', printer.id)">
               <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else class="flex flex-col items-center justify-center py-20 md:py-32 px-6 animate-zoom-in">
        <div class="w-24 h-24 md:w-32 md:h-32 bg-gray-100 dark:bg-[#151a22] rounded-[24px] flex items-center justify-center text-5xl md:text-6xl mb-8 md:mb-10 shadow-inner">
            <svg class="w-12 h-12 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
        </div>
        <h3 class="text-3xl md:text-4xl font-black text-gray-900 dark:text-white uppercase tracking-tighter mb-4 text-center">Taller sin maquinaria</h3>
        <p class="text-[10px] md:text-xs font-black text-gray-400 dark:text-gray-500 uppercase tracking-[0.3em] text-center max-w-md leading-relaxed mb-10">
            No hay impresoras 3D registradas en el sistema. Inicia tu granja de producción agregando tu primera máquina FDM o Resina.
        </p>
        <button class="btn-primary px-12 py-5 shadow-2xl shadow-primary/20 hover:scale-105 active:scale-95 transition-all" @click="$emit('add-printer')">
            Registrar Primera Máquina
        </button>
    </div>
  </div>
</template>

<style scoped>
@keyframes shimmer {
  0% { transform: translateX(-100%); }
  100% { transform: translateX(100%); }
}
.animate-shimmer {
  position: relative;
  overflow: hidden;
}
.animate-shimmer::after {
  content: "";
  position: absolute;
  top: 0; left: 0; width: 100%; height: 100%;
  background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
  animation: shimmer 2s infinite;
}
</style>


