<script setup lang="ts">
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { sanitizeSVG } from '../../utils/sanitize'

const props = defineProps({
  isOpen: { type: Boolean, default: false },
  activeTab: { type: String, default: 'kanban' },
  pendingOrdersCount: { type: Number, default: 0 }
})

const emit = defineEmits(['update:activeTab', 'logout', 'close'])

const router = useRouter()

const navItems = [
  { 
    id: 'kanban', label: 'Producción', 
    icon: '<svg class=\"w-5 h-5\" fill=\"none\" viewBox=\"0 0 24 24\" stroke=\"currentColor\"><path stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"1.5\" d=\"M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2\"/></svg>',
    badge: (): number => props.pendingOrdersCount
  },
  { id: 'inventory', label: 'Inventario', icon: '<svg class=\"w-5 h-5\" fill=\"none\" viewBox=\"0 0 24 24\" stroke=\"currentColor\"><path stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"1.5\" d=\"M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4\"/></svg>' },
  { id: 'accounting', label: 'Contabilidad', icon: '<svg class=\"w-5 h-5\" fill=\"none\" viewBox=\"0 0 24 24\" stroke=\"currentColor\"><path stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"1.5\" d=\"M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z\"/></svg>' },
  { id: 'machines', label: 'Maquinaria', icon: '<svg class=\"w-5 h-5\" fill=\"none\" viewBox=\"0 0 24 24\" stroke=\"currentColor\"><path stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"1.5\" d=\"M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z\"/><path stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"1.5\" d=\"M15 12a3 3 0 11-6 0 3 3 0 016 0z\"/></svg>' },
  { id: 'orders', label: 'Historial', icon: '<svg class=\"w-5 h-5\" fill=\"none\" viewBox=\"0 0 24 24\" stroke=\"currentColor\"><path stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"1.5\" d=\"M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01\"/></svg>' },
  { id: 'purchases', label: 'Compras', icon: '<svg class=\"w-5 h-5\" fill=\"none\" viewBox=\"0 0 24 24\" stroke=\"currentColor\"><path stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"1.5\" d=\"M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z\"/></svg>' },
  { id: 'contacts', label: 'Iniciar Proyecto 3D', icon: '<svg class=\"w-5 h-5\" fill=\"none\" viewBox=\"0 0 24 24\" stroke=\"currentColor\"><path stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"1.5\" d=\"M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z\"/></svg>' },
  { id: 'discounts', label: 'Descuentos', icon: '<svg class=\"w-5 h-5\" fill=\"none\" viewBox=\"0 0 24 24\" stroke=\"currentColor\"><path stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"1.5\" d=\"M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z\"/></svg>' },
  { id: 'web', label: 'Sitio Web', icon: '<svg class=\"w-5 h-5\" fill=\"none\" viewBox=\"0 0 24 24\" stroke=\"currentColor\"><path stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"1.5\" d=\"M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9\"/></svg>' }
]

const handleNavClick = (tabId: any) => {
  emit('update:activeTab', tabId)
  emit('close')
}

const logout = () => {
  emit('logout')
}
</script>

<template>
  <!-- N3XT PREMIUM SIDEBAR -->
  <aside 
    :class="[
      'fixed inset-y-0 left-0 z-50 w-72 bg-white/80 dark:bg-black/60 backdrop-blur-3xl text-gray-900 dark:text-white transition-all duration-700 ease-[cubic-bezier(0.19,1,0.22,1)] transform md:static md:block md:translate-x-0 border-r border-gray-100 dark:border-white/5 animate-slide-up',
      isOpen ? 'translate-x-0' : '-translate-x-full'
    ]"
  >
    <!-- Glow Effect Sidebar -->
    <div class="absolute -top-24 -left-24 w-64 h-64 bg-primary/20 rounded-full blur-[100px] pointer-events-none opacity-50"></div>
    
    <div class="flex flex-col h-full relative z-10">
      <!-- Sidebar Brand -->
      <div class="p-8 pb-12 flex flex-col items-center cursor-pointer group/brand relative" @click="$router.push('/')">
          <div class="absolute inset-0 bg-primary/5 opacity-0 group-hover/brand:opacity-100 transition-opacity rounded-[3rem] -m-4"></div>
          <div class="relative w-16 h-16 bg-gradient-to-br from-primary to-emerald-500 rounded-[1.5rem] flex items-center justify-center shadow-2xl shadow-primary/30 mb-4 transition-transform group-hover/brand:scale-110 group-hover/brand:rotate-6 duration-500">
              <span class="text-white font-black text-3xl italic">N</span>
          </div>
          <h1 class="relative text-2xl font-black tracking-tighter uppercase leading-none text-gray-900 dark:text-white group-hover/brand:text-primary transition-colors">N3XT<span class="text-gray-400 ml-1">3D</span></h1>
          <span class="relative text-[8px] font-black text-primary/60 uppercase tracking-[0.4em] mt-2">Industrial OS v3.2</span>
      </div>

      <!-- Navigation Links -->
      <nav class="flex-1 px-4 space-y-1 overflow-y-auto no-scrollbar">
        <button 
          v-for="item in navItems" :key="item.id"
          :class="[
            'w-full flex items-center gap-5 px-6 py-4 rounded-[1.5rem] transition-all duration-500 font-black text-xs uppercase tracking-[0.2em] group relative overflow-hidden',
            activeTab === item.id
              ? 'bg-primary/10 dark:bg-primary/20 text-primary shadow-sm'
              : 'text-gray-400 dark:text-gray-600 hover:text-gray-700 dark:hover:text-gray-300 hover:bg-gray-50 dark:hover:bg-white/5'
          ]"
          @click="handleNavClick(item.id)"
        >
          <span v-if="activeTab === item.id" class="absolute left-0 top-1/2 -translate-y-1/2 w-1 h-8 bg-primary rounded-full shadow-[0_0_10px_rgba(16,185,129,0.5)]"></span>
          <span :innerHTML="sanitizeSVG(item.icon)" class="w-5 h-5 flex-shrink-0" :class="activeTab === item.id ? 'text-primary' : ''"></span>
          <span class="relative z-10">{{ item.label }}</span>
          
          <!-- Notification badge for orders -->
          <span 
            v-if="typeof item.badge === 'function' ? item.badge() > 0 : (item.badge as any) > 0"
            class="ml-auto px-3 py-1 bg-rose-500 text-white text-[8px] font-black rounded-full shadow-lg shadow-rose-500/30"
          >
            {{ typeof item.badge === 'function' ? (item.badge() > 99 ? '99+' : item.badge()) : item.badge }}
          </span>
        </button>
      </nav>

      <!-- Sidebar Footer -->
      <div class="p-6 border-t border-gray-100 dark:border-white/5 space-y-3">
        <button class="w-full flex items-center gap-4 px-6 py-4 rounded-[1.5rem] text-gray-400 dark:text-gray-600 hover:text-rose-500 hover:bg-rose-500/5 transition-all font-black text-[10px] uppercase tracking-[0.2em]" @click="logout">
          <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
          Cerrar Sesión
        </button>
        
        <div class="px-6 pt-2">
          <div class="h-px bg-gradient-to-r from-transparent via-primary/20 to-transparent"></div>
          <p class="text-[7px] font-black text-gray-300 dark:text-gray-700 uppercase tracking-[0.5em] text-center mt-4">Sistema de Gestión N3XT</p>
        </div>
      </div>
    </div>
  </aside>
</template>

<style scoped>
.animate-slide-up {
  animation: slideUp 0.8s cubic-bezier(0.19, 1, 0.22, 1) forwards;
}

@keyframes slideUp {
  from { opacity: 0; transform: translateY(20px); }
  to { opacity: 1; transform: translateY(0); }
}
</style>
