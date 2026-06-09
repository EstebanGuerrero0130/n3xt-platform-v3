<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { api } from '../../services/api'

const props = defineProps<{
  activeTab: string
  loading: boolean
  isSidebarOpen: boolean
  settings: any
  uploadingLogo?: boolean
  logoInput?: any
}>()

const emit = defineEmits<{
  (e: 'toggle-sidebar'): void
  (e: 'sync'): void
  (e: 'logout'): void
  (e: 'update:isSidebarOpen', val: boolean): void
}>()

const logoError = ref(false)

const logoUrl = computed(() => {
  if (!props.settings?.company_logo) return '/logo.png'
  if (props.settings.company_logo.startsWith('http')) return props.settings.company_logo
  return `${api.storageUrl}/${props.settings.company_logo}`
})

// Reset error state when logo URL changes (e.g. after upload)
watch(logoUrl, () => { logoError.value = false })
</script>

<template>
  <header class="h-20 md:h-24 bg-white/70 dark:bg-black/40 backdrop-blur-3xl border-b border-gray-100/50 dark:border-white/5 flex items-center justify-between px-4 md:px-12 relative z-40 shadow-sm">
    <div class="flex items-center gap-3 md:gap-6 min-w-0">
      <!-- Mobile Toggle -->
      <button 
        class="md:hidden w-10 h-10 flex items-center justify-center bg-gray-900 dark:bg-white dark:text-gray-900 text-white rounded-2xl shrink-0" 
        @click="emit('toggle-sidebar')"
      >
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 6h16M4 12h16M4 18h16"/>
        </svg>
      </button>

      <div class="flex flex-col min-w-0">
        <div class="flex items-center gap-2 md:gap-3">
          <div class="w-2 md:w-2.5 h-2 md:h-2.5 bg-primary rounded-full animate-pulse shadow-[0_0_8px_#1e3a34] shrink-0"></div>
          <h2 class="text-sm md:text-2xl font-black text-slate-900 dark:text-white tracking-tighter uppercase italic truncate">
            {{ activeTab === 'kanban' ? 'PRODUCCIÓN' : activeTab.toUpperCase() }}
          </h2>
        </div>
        <span class="hidden sm:block text-[8px] md:text-[9px] font-black text-gray-400 dark:text-gray-300 uppercase tracking-[0.4em] mt-1 ml-4 md:ml-5 truncate">Real-time Operations</span>
      </div>
    </div>

    <div class="flex items-center gap-2 md:gap-8">
      <!-- System Status Desktop -->
      <div class="hidden lg:flex flex-col items-end">
        <span class="text-[9px] font-black text-gray-900 dark:text-white uppercase tracking-widest flex items-center gap-2">
          N3XT OS PREMIUM
          <span class="w-1.5 h-1.5 bg-emerald-500 rounded-full"></span>
        </span>
        <span class="text-[8px] font-bold text-gray-400 dark:text-gray-400 uppercase tracking-widest mt-0.5">Modo Central Activo</span>
      </div>

      <div class="hidden md:block h-10 w-px bg-gray-100 dark:bg-white/10"></div>

      <!-- Action Group -->
      <div class="flex items-center gap-2 md:gap-4">
        <!-- Sync Button -->
        <button 
          :disabled="loading" 
          class="group w-10 h-10 md:w-12 md:h-12 bg-[var(--bg-surface)] hover:bg-primary hover:text-white text-gray-400 rounded-xl md:rounded-2xl flex items-center justify-center transition-all duration-500 active:scale-90 border border-[var(--border-main)]"
          title="Sincronizar Datos"
          @click="emit('sync')"
        >
          <svg :class="['w-5 h-5', loading ? 'animate-spin' : 'group-hover:rotate-180 transition-transform duration-700']" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
          </svg>
        </button>

        <!-- Logout Button in Header -->
        <button 
          class="hidden md:flex w-10 h-10 md:w-12 md:h-12 bg-rose-50 dark:bg-rose-500/10 hover:bg-rose-500 hover:text-white text-rose-500 rounded-xl md:rounded-2xl items-center justify-center transition-all duration-300 active:scale-90 shadow-sm border border-rose-500/20" 
          title="Cerrar Sesión"
          @click="emit('logout')"
        >
          <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
          </svg>
        </button>

        <div class="hidden sm:block w-10 h-10 md:w-12 md:h-12 rounded-xl md:rounded-2xl overflow-hidden border-2 border-primary/20 p-0.5 shadow-xl shadow-primary/10">
          <img
            v-if="!logoError"
            :src="logoUrl"
            class="w-full h-full object-contain bg-white"
            @error="logoError = true"
          />
          <div v-else class="w-full h-full bg-gradient-to-br from-primary via-emerald-500 to-primary flex items-center justify-center relative overflow-hidden group">
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_30%_30%,rgba(255,255,255,0.2),transparent_60%)]"></div>
            <div class="absolute -inset-2 bg-gradient-to-tr from-emerald-300/20 to-transparent animate-pulse rounded-full blur-sm"></div>
            <div class="absolute w-2 h-2 bg-white/30 rounded-full top-1 left-1 animate-ping"></div>
            <div class="absolute w-2 h-2 bg-white/20 rounded-full bottom-1 right-1 animate-ping" style="animation-delay: 1.5s"></div>
            <span class="relative z-10 text-white font-black italic text-lg tracking-tighter drop-shadow-[0_2px_4px_rgba(0,0,0,0.3)]">N</span>
          </div>
        </div>
      </div>
    </div>
  </header>
</template>
