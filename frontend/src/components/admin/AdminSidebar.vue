<script setup lang="ts">
// computed and useRouter not needed — using $emit and $router directly in template
import { sanitizeSVG } from '../../utils/sanitize'

const props = defineProps({
  isOpen: { type: Boolean, default: false },
  activeTab: { type: String, default: 'kanban' },
  pendingOrdersCount: { type: Number, default: 0 }
})

const emit = defineEmits(['update:activeTab', 'logout', 'close'])

const navItems = [
  { 
    id: 'kanban', label: 'Producción', 
    icon: '<svg class=\"w-5 h-5\" fill=\"none\" viewBox=\"0 0 24 24\" stroke=\"currentColor\"><path stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"1.5\" d=\"M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2\"/></svg>',
    badge: (): number => props.pendingOrdersCount
  },
  { id: 'inventory', label: 'Inventario', icon: '<svg class=\"w-5 h-5\" fill=\"none\" viewBox=\"0 0 24 24\" stroke=\"currentColor\"><path stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"1.5\" d=\"M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4\"/></svg>' },
  { id: 'accounting', label: 'Contabilidad', icon: '<svg class=\"w-5 h-5\" fill=\"none\" viewBox=\"0 0 24 24\" stroke=\"currentColor\"><path stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"1.5\" d=\"M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z\"/></svg>' },
  { id: 'machines', label: 'Maquinaria', icon: '<svg class=\"w-5 h-5\" fill=\"none\" viewBox=\"0 0 24 24\" stroke=\"currentColor\"><path stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"1.5\" d=\"M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z\"/><path stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"1.5\" d=\"M15 12a3 3 0 11-6 0 3 3 0 016 0z\"/></svg>' },
  { id: 'history', label: 'Historial', icon: '<svg class=\"w-5 h-5\" fill=\"none\" viewBox=\"0 0 24 24\" stroke=\"currentColor\"><path stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"1.5\" d=\"M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01\"/></svg>' },
  { id: 'purchases', label: 'Compras', icon: '<svg class=\"w-5 h-5\" fill=\"none\" viewBox=\"0 0 24 24\" stroke=\"currentColor\"><path stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"1.5\" d=\"M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z\"/></svg>' },
  { id: 'contacts', label: 'Contactos', icon: '<svg class=\"w-5 h-5\" fill=\"none\" viewBox=\"0 0 24 24\" stroke=\"currentColor\"><path stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"1.5\" d=\"M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z\"/></svg>' },
  { id: 'discounts', label: 'Descuentos', icon: '<svg class=\"w-5 h-5\" fill=\"none\" viewBox=\"0 0 24 24\" stroke=\"currentColor\"><path stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"1.5\" d=\"M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z\"/></svg>' },
  { id: 'settings', label: 'Parámetros', icon: '<svg class=\"w-5 h-5\" fill=\"none\" viewBox=\"0 0 24 24\" stroke=\"currentColor\"><path stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"1.5\" d=\"M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z\"/><path stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"1.5\" d=\"M15 12a3 3 0 11-6 0 3 3 0 016 0z\"/></svg>' },
  { id: 'web', label: 'Sitio web', icon: '<svg class=\"w-5 h-5\" fill=\"none\" viewBox=\"0 0 24 24\" stroke=\"currentColor\"><path stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"1.5\" d=\"M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9\"/></svg>' }
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
  <aside
    :class="[
      'fixed inset-y-0 left-0 z-50 w-72 max-w-[85vw] bg-surface text-ink transform transition-transform duration-300 ease-[cubic-bezier(0.22,1,0.36,1)] md:static md:translate-x-0',
      isOpen ? 'translate-x-0' : '-translate-x-full'
    ]"
    aria-label="Menú del panel"
  >
    <div class="flex flex-col h-full">
      <!-- Marca -->
      <div class="px-6 pt-7 pb-6">
        <router-link to="/" class="inline-flex items-baseline gap-2 font-display text-2xl font-black tracking-tight [font-stretch:118%] text-ink" title="Ver el sitio">
          N3XT<span class="text-brand">3D</span>
        </router-link>
        <p class="mt-1 text-sm text-muted">Panel del taller</p>
      </div>

      <!-- Navegación -->
      <nav class="flex-1 px-3 overflow-y-auto no-scrollbar" aria-label="Secciones">
        <ul class="space-y-1">
          <li v-for="item in navItems" :key="item.id">
            <button
              type="button"
              :aria-current="activeTab === item.id ? 'page' : undefined"
              :class="[
                'w-full flex items-center gap-3 h-11 px-3 rounded-[var(--radius-control)] text-[0.9375rem] font-medium transition-colors',
                activeTab === item.id ? 'bg-primary/10 text-brand font-semibold' : 'text-muted hover:text-ink hover:bg-surface-2'
              ]"
              @click="handleNavClick(item.id)"
            >
              <span :innerHTML="sanitizeSVG(item.icon)" class="w-5 h-5 shrink-0" aria-hidden="true"></span>
              <span>{{ item.label }}</span>
              <span
                v-if="typeof item.badge === 'function' && item.badge() > 0"
                class="ml-auto min-w-6 h-6 px-2 inline-flex items-center justify-center rounded-full bg-primary text-white text-xs font-bold tabular-nums"
                :aria-label="`${item.badge()} pendientes`"
              >{{ item.badge() > 99 ? '99+' : item.badge() }}</span>
            </button>
          </li>
        </ul>
      </nav>

      <!-- Pie -->
      <div class="p-3 space-y-1 border-t border-line">
        <router-link to="/" class="w-full flex items-center gap-3 h-11 px-3 rounded-[var(--radius-control)] text-[0.9375rem] font-medium text-muted hover:text-ink hover:bg-surface-2 transition-colors">
          <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
          Ver el sitio
        </router-link>
        <button type="button" class="w-full flex items-center gap-3 h-11 px-3 rounded-[var(--radius-control)] text-[0.9375rem] font-medium text-muted hover:text-danger hover:bg-danger/5 transition-colors" @click="logout">
          <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
          Cerrar sesión
        </button>
      </div>
    </div>
  </aside>
</template>
