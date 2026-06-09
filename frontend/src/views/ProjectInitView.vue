<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import AppNavbar from '../components/AppNavbar.vue'
import { sanitizeSVG } from '../utils/sanitize'
import { api } from '../services/api'
import { useRevealAnim } from '../composables/useRevealAnim'
import logger from '../utils/logger'
import { useSplitTitle } from '../composables/useSplitTitle'
import { useSplitButton } from '../composables/useSplitButton'
import { useParticles } from '../composables/useParticles'
import { usePageMeta } from '../composables/usePageMeta'

useSplitTitle()
useSplitButton()

usePageMeta({
  title: 'Iniciar Proyecto 3D | N3XT 3D',
  description: 'Configura tu proyecto de impresion 3D. Selecciona tipo, tecnologia, acabado y urgencia.',
  image: '/assets/n3xt_og_project.png',
})

const { particlesRef: heroParticlesRef } = useParticles({
  count: 30,
  zIndex: 1,
})

// ─── SEO via @unhead/vue (usePageMeta at setup) ───

useRevealAnim({ delay: 200 })

// --- CONFIGURACIÓN DINÁMICA ---
const whatsappNumber = ref('')

const fetchSettings = async () => {
  try {
    const data = await api.get('/settings')
    if (data.web && data.web.social && data.web.social.whatsapp) {
      // Limpiamos el número de caracteres especiales por si acaso
      whatsappNumber.value = data.web.social.whatsapp.replace(/\D/g, '')
    }
  } catch (err) {
    logger.error('Error fetching settings for WhatsApp:', err)
  }
}

onMounted(() => {
  fetchSettings()
})

onUnmounted(() => {
})

// --- DATA DE CONFIGURACIÓN ---
const steps = [
  {
    id: 'type',
    label: 'Tipo de Proyecto',
    options: [
      { id: 'figura', label: 'Figura / Arte', icon: `<svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"/></svg>`, desc: 'Miniaturas, bustos o esculturas' },
      { id: 'tecnica', label: 'Pieza Técnica', icon: `<svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>`, desc: 'Engranajes, soportes o repuestos' },
      { id: 'prototipo', label: 'Prototipo', icon: `<svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/></svg>`, desc: 'Modelos de prueba e ingeniería' },
      { id: 'joyeria', label: 'Joyería', icon: `<svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/></svg>`, desc: 'Moldes de alta precisión' }
    ]
  },
  {
    id: 'tech',
    label: 'Tecnología Sugerida',
    options: [
      { id: 'resina', label: 'Resina 8K', icon: `<svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/></svg>`, desc: 'Máximo detalle y superficie lisa' },
      { id: 'filamento', label: 'Filamento (FDM)', icon: `<svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"/></svg>`, desc: 'Resistencia mecánica y funcional' }
    ]
  },
  {
    id: 'finish',
    label: 'Acabado Final',
    options: [
      { id: 'bruto', label: 'Bruto Industrial', icon: `<svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>`, desc: 'Sin post-procesado (más económico)' },
      { id: 'pulido', label: 'Pulido / Curado', icon: `<svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-7.714 2.143L11 21l-2.286-6.857L1 12l7.714-2.143L11 3z"/></svg>`, desc: 'Superficie suave y lista para usar' },
      { id: 'premium', label: 'Premium Paint', icon: `<svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>`, desc: 'Lijado y pintura artesanal detallada' }
    ]
  },
  {
    id: 'priority',
    label: 'Urgencia del Proyecto',
    options: [
      { id: 'normal', label: 'Estándar', icon: `<svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>`, desc: 'Tiempo de entrega regular' },
      { id: 'express', label: 'Express', icon: `<svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>`, desc: 'Prioridad máxima en granja' }
    ]
  }
]

const customerName = ref('')
const selections = ref({
  type: 'figura',
  tech: 'resina',
  finish: 'bruto',
  priority: 'normal'
})

const getLabel = (stepId: any, optionId: any) => {
  const step = steps.find(s => s.id === stepId)
  return step?.options.find(o => o.id === optionId)?.label || ''
}

const buildWhatsappUrl = () => {
  const phone = whatsappNumber.value || '573219468945'
  const nameIntro = customerName.value ? `Hola N3XT, soy *${customerName.value.toUpperCase()}*` : 'Hola N3XT'
  
  const text = `${nameIntro}! Deseo iniciar un proyecto 3D con ustedes.
 
*DETALLES DE CONFIGURACION:*
- *Proyecto:* ${getLabel('type', selections.value.type)}
- *Tecnología:* ${getLabel('tech', selections.value.tech)}
- *Acabado:* ${getLabel('finish', selections.value.finish)}
- *Urgencia:* ${getLabel('priority', selections.value.priority)}
 
¿Podrían asesorarme con el análisis técnico y cotización de mi idea? Adjunto referencia...`
 
  return `https://wa.me/${phone}?text=${encodeURIComponent(text)}`
}

const sendToWhatsapp = () => {
  window.open(buildWhatsappUrl(), '_blank')
}
</script>

<template>
  <div class="min-h-screen bg-[#f8fafc] dark:bg-[#0a0f14] text-slate-900 dark:text-white transition-colors duration-500 overflow-x-hidden selection:bg-emerald-500/20 font-inter">
    <AppNavbar active-tab="contact" subtext="Centro de Precisión Industrial" />

    <main class="max-w-7xl mx-auto px-6 py-20 relative">
      <div class="absolute inset-0 technical-grid opacity-20 dark:opacity-10 pointer-events-none"></div>
      <div class="absolute top-40 -left-40 w-96 h-96 bg-emerald-500/5 dark:bg-emerald-500/10 rounded-full blur-[120px] pointer-events-none"></div>

      <div class="relative z-10 flex flex-col lg:flex-row gap-16">
        <div class="flex-1 space-y-16">
          <div class="space-y-6">
            <div class="relative inline-block overflow-hidden">
              <div ref="heroParticlesRef" class="project-hero-particles" aria-hidden="true"></div>
              <!-- Glow background -->
              <div class="absolute -inset-20 bg-gradient-to-r from-emerald-500/5 via-primary/5 to-emerald-500/5 rounded-full blur-[100px] animate-pulse pointer-events-none"></div>
              <h1 class="text-5xl md:text-7xl lg:text-8xl font-black tracking-tighter uppercase leading-[0.85] text-slate-900 dark:text-white animate-fade-in">
                INICIAR <br/>
                <span class="text-transparent bg-clip-text bg-gradient-to-r from-emerald-500 via-primary to-emerald-300 dark:from-emerald-400 dark:via-primary dark:to-emerald-300 drop-shadow-[0_0_15px_rgba(16,185,129,0.3)]">PROYECTO 3D.</span>
              </h1>
            </div>
            <div class="space-y-3 max-w-md">
              <label class="text-[9px] font-black text-emerald-600 dark:text-emerald-400 uppercase tracking-[0.4em] block ml-1">Identificación del Cliente</label>
              <input v-model="customerName" type="text" placeholder="Escribe tu nombre aqui..." class="w-full bg-white dark:bg-white/5 border border-slate-200 dark:border-white/10 rounded-2xl px-6 py-4 text-sm font-black text-slate-900 dark:text-white uppercase outline-none focus:ring-2 focus:ring-emerald-500/20 transition-all shadow-sm">
            </div>
            <p class="text-slate-400 dark:text-gray-400 font-bold uppercase tracking-[0.4em] text-xs">Asistente de Configuración N3XT</p>
          </div>

          <div v-for="step in steps" :key="step.id" class="space-y-6">
            <div class="flex items-center gap-4">
              <span class="w-8 h-px bg-emerald-500/30 dark:bg-emerald-500/50"></span>
              <h3 class="text-xs font-black uppercase tracking-[0.3em] text-emerald-600 dark:text-emerald-400">{{ step.label }}</h3>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
              <button 
                v-for="opt in step.options" 
                :key="opt.id"
                :class="selections[step.id] === opt.id 
                  ? 'border-emerald-500 bg-emerald-50 dark:bg-emerald-500/10 shadow-[0_10px_30px_rgba(16,185,129,0.1)] dark:shadow-[0_0_20px_rgba(16,185,129,0.2)]' 
                  : 'border-slate-200 dark:border-white/10 bg-white dark:bg-white/5 hover:border-emerald-200 dark:hover:border-white/20'"
                class="group aspect-square p-6 rounded-[2.5rem] border text-center transition-all duration-500 relative overflow-hidden flex flex-col items-center justify-center gap-4"
                @click="selections[step.id] = opt.id"
              >
                <div 
                  :innerHTML="sanitizeSVG(opt.icon)" 
                  :class="selections[step.id] === opt.id ? 'text-emerald-500 scale-110' : 'text-slate-400 dark:text-gray-500'"
                  class="group-hover:scale-110 group-hover:text-emerald-500 transition-all duration-500"
                ></div>
                <div class="space-y-1">
                  <p :class="selections[step.id] === opt.id ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-800 dark:text-white'" class="font-black uppercase tracking-tighter text-sm leading-tight italic">{{ opt.label }}</p>
                  <p class="text-[8px] text-slate-400 dark:text-gray-500 font-bold uppercase tracking-widest hidden md:block">{{ opt.desc }}</p>
                </div>
                <div v-if="selections[step.id] === opt.id" class="absolute top-4 right-4">
                  <div class="w-1.5 h-1.5 bg-emerald-500 rounded-full animate-pulse shadow-[0_0_10px_rgba(16,185,129,1)]"></div>
                </div>
              </button>
            </div>
          </div>
        </div>

        <aside class="w-full lg:w-[450px] space-y-8">
          <div class="sticky top-32">
            <div class="p-10 bg-white dark:bg-white/[0.03] backdrop-blur-3xl rounded-[3rem] border border-slate-200 dark:border-white/10 shadow-[0_30px_60px_-15px_rgba(0,0,0,0.05)] dark:shadow-2xl space-y-10 relative overflow-hidden">
              <div class="absolute -right-20 -top-20 w-40 h-40 bg-emerald-500/10 dark:bg-emerald-500/20 rounded-full blur-3xl"></div>
              <div class="space-y-2 relative z-10 border-b border-slate-100 dark:border-white/5 pb-6">
                <p class="text-[10px] font-black text-emerald-600 dark:text-emerald-400 uppercase tracking-[0.5em]">Resumen del Proyecto</p>
                <h3 class="text-2xl font-black uppercase tracking-tighter italic text-slate-800 dark:text-white">FLUJO DE TRABAJO N3XT</h3>
              </div>
              <div class="space-y-6 font-mono relative z-10">
                <div v-for="step in steps" :key="step.id" class="space-y-2">
                  <p class="text-[8px] text-slate-400 dark:text-gray-500 uppercase tracking-widest">{{ step.label }}</p>
                  <div class="flex items-center justify-between bg-slate-50 dark:bg-black/40 p-4 rounded-xl border border-slate-100 dark:border-white/5">
                    <span class="text-xs text-emerald-600 dark:text-emerald-500 font-bold">> {{ getLabel(step.id, selections[step.id]) }}</span>
                  </div>
                </div>
              </div>
              <div class="space-y-6 relative z-10 pt-6">
                <button 
                  :disabled="!customerName"
                  :class="!customerName ? 'opacity-40 cursor-not-allowed bg-slate-500' : 'bg-emerald-500 shadow-[0_20px_50px_-15px_rgba(16,185,129,0.4)] hover:scale-[1.02] active:scale-95'"
                  class="w-full py-8 text-white rounded-[2rem] font-black text-sm uppercase tracking-[0.4em] transition-all flex items-center justify-center gap-4 group"
                  @click="sendToWhatsapp"
                >
                  <svg class="w-6 h-6 fill-current" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                  <span class="split-btn">SOLICITAR ANÁLISIS</span>
                </button>
              </div>
            </div>
          </div>
        </aside>
      </div>
    </main>
  </div>
</template>

<style scoped>
.project-hero-particles {
  position: absolute;
  inset: 0;
  overflow: hidden;
  pointer-events: none;
  z-index: -1;
}

.technical-grid {
  background-size: 50px 50px;
  background-image: linear-gradient(to right, rgba(16, 185, 129, 0.05) 1px, transparent 1px), linear-gradient(to bottom, rgba(16, 185, 129, 0.05) 1px, transparent 1px);
}


/* --- Scroll Reveal --- */
.reveal {
  opacity: 0;
  transform: translateY(30px);
  transition: opacity 0.8s cubic-bezier(0.22, 1, 0.36, 1),
              transform 0.8s cubic-bezier(0.22, 1, 0.36, 1);
}
.reveal.revealed {
  opacity: 1;
  transform: translateY(0);
}
</style>
