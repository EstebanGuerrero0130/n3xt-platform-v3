<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import AppNavbar from '../components/AppNavbar.vue'
import AppFooter from '../components/AppFooter.vue'
import { DEFAULT_WHATSAPP_NUMBER, parseWhatsAppNumber, whatsappLink } from '../utils/contact'
import { sanitizeSVG } from '../utils/sanitize'
import { api } from '../services/api'
import logger from '../utils/logger'
import { usePageMeta } from '../composables/usePageMeta'

usePageMeta({
  title: 'Iniciar Proyecto 3D | N3XT 3D',
  description: 'Cuéntanos tu proyecto de impresión 3D y te asesoramos con la tecnología, el material y el acabado.',
})

// ─── SEO via @unhead/vue (usePageMeta at setup) ───

// --- CONFIGURACIÓN DINÁMICA ---
const whatsappNumber = ref('')

const fetchSettings = async () => {
  try {
    const data = await api.get('/settings')
    // Extrae solo los dígitos (acepta número plano o link wa.me)
    const num = parseWhatsAppNumber(data?.web?.social?.whatsapp)
    if (num) whatsappNumber.value = num
  } catch (err) {
    logger.error('Error fetching settings for WhatsApp:', err)
  }
}

onMounted(() => {
  fetchSettings()
})

// --- DATA DE CONFIGURACIÓN ---
type StepId = 'type' | 'tech' | 'finish' | 'priority'
interface StepOption { id: string; label: string; icon: string; desc: string }

const steps: { id: StepId; label: string; options: StepOption[] }[] = [
  {
    id: 'type',
    label: 'Tipo de proyecto',
    options: [
      { id: 'figura', label: 'Figura o arte', icon: `<svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"/></svg>`, desc: 'Miniaturas, bustos o esculturas' },
      { id: 'tecnica', label: 'Pieza técnica', icon: `<svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>`, desc: 'Engranajes, soportes o repuestos' },
      { id: 'prototipo', label: 'Prototipo', icon: `<svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/></svg>`, desc: 'Modelos de prueba y validación' },
      { id: 'joyeria', label: 'Joyería', icon: `<svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/></svg>`, desc: 'Moldes de alta precisión' }
    ]
  },
  {
    id: 'tech',
    label: 'Tecnología',
    options: [
      { id: 'resina', label: 'Resina 8K', icon: `<svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/></svg>`, desc: 'Máximo detalle y superficie lisa' },
      { id: 'filamento', label: 'Filamento (FDM)', icon: `<svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"/></svg>`, desc: 'Resistencia mecánica y funcional' }
    ]
  },
  {
    id: 'finish',
    label: 'Acabado',
    options: [
      { id: 'bruto', label: 'Sin acabado', icon: `<svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>`, desc: 'Sin post-procesado, la opción más económica' },
      { id: 'pulido', label: 'Pulido y curado', icon: `<svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-7.714 2.143L11 21l-2.286-6.857L1 12l7.714-2.143L11 3z"/></svg>`, desc: 'Superficie suave y lista para usar' },
      { id: 'premium', label: 'Pintura premium', icon: `<svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>`, desc: 'Lijado y pintura artesanal detallada' }
    ]
  },
  {
    id: 'priority',
    label: 'Urgencia',
    options: [
      { id: 'normal', label: 'Estándar', icon: `<svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>`, desc: 'Tiempo de entrega regular' },
      { id: 'express', label: 'Express', icon: `<svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>`, desc: 'Tu pieza pasa primero en la cola' }
    ]
  }
]

const customerName = ref('')
const selections = ref<Record<StepId, string>>({
  type: 'figura',
  tech: 'resina',
  finish: 'bruto',
  priority: 'normal'
})

const getLabel = (stepId: string, optionId: string) => {
  const step = steps.find(s => s.id === stepId)
  return step?.options.find(o => o.id === optionId)?.label || ''
}

const buildWhatsappUrl = () => {
  const phone = whatsappNumber.value || DEFAULT_WHATSAPP_NUMBER
  const name = customerName.value.trim()
  const nameIntro = name ? `Hola N3XT, soy *${name.toUpperCase()}*` : 'Hola N3XT'
  
  const text = `${nameIntro}! Deseo iniciar un proyecto 3D con ustedes.
 
*DETALLES DE CONFIGURACION:*
- *Proyecto:* ${getLabel('type', selections.value.type)}
- *Tecnología:* ${getLabel('tech', selections.value.tech)}
- *Acabado:* ${getLabel('finish', selections.value.finish)}
- *Urgencia:* ${getLabel('priority', selections.value.priority)}
 
¿Podrían asesorarme con el análisis técnico y cotización de mi idea? Adjunto referencia...`
 
  return whatsappLink(phone, text)
}

const hasName = computed(() => customerName.value.trim().length > 0)

const sendToWhatsapp = () => {
  if (!hasName.value) return
  window.open(buildWhatsappUrl(), '_blank', 'noopener,noreferrer')
}
</script>

<template>
  <div class="min-h-screen flex flex-col bg-canvas text-ink pb-24 lg:pb-0">
    <AppNavbar active-tab="contact" />

    <main id="main" class="flex-1 w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 md:py-16">
      <div class="flex flex-col lg:flex-row gap-10 lg:gap-12">
        <div class="flex-1 min-w-0 space-y-10">
          <header>
            <h1 class="text-4xl sm:text-5xl lg:text-6xl font-extrabold tracking-tight">Cuéntanos tu proyecto</h1>
            <p class="mt-4 text-lg md:text-xl text-muted max-w-2xl">Elige las opciones que más se acercan a tu idea y te respondemos por WhatsApp con una cotización.</p>
          </header>

          <div class="max-w-md">
            <label for="project-customer-name" class="block text-sm font-medium text-ink mb-2">Tu nombre</label>
            <input id="project-customer-name" v-model="customerName" type="text" autocomplete="name" placeholder="Ej: Laura Gómez" aria-describedby="project-name-hint" class="w-full h-11 px-4 rounded-[var(--radius-control)] border border-line bg-canvas text-ink placeholder:text-muted/70 focus:outline-none focus:border-primary focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-[var(--brand-text)]">
            <p id="project-name-hint" class="mt-1.5 text-sm text-muted">Lo necesitamos para enviar tu solicitud por WhatsApp.</p>
          </div>

          <fieldset v-for="(step, stepIndex) in steps" :key="step.id" class="min-w-0">
            <legend class="text-lg md:text-xl font-extrabold tracking-tight mb-4">
              <span class="text-muted font-semibold">{{ stepIndex + 1 }}.</span> {{ step.label }}
            </legend>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <button
                v-for="opt in step.options"
                :key="opt.id"
                type="button"
                :aria-pressed="selections[step.id] === opt.id"
                :class="selections[step.id] === opt.id
                  ? 'border-primary bg-primary/5'
                  : 'border-line bg-surface hover:border-muted/60'"
                class="relative flex items-start gap-3 p-4 text-left rounded-[var(--radius-card)] border transition-colors"
                @click="selections[step.id] = opt.id"
              >
                <span
                  :innerHTML="sanitizeSVG(opt.icon)"
                  :class="selections[step.id] === opt.id ? 'text-brand' : 'text-muted'"
                  class="shrink-0 mt-0.5"
                  aria-hidden="true"
                ></span>
                <span class="flex-1 min-w-0 pr-6">
                  <span class="block font-semibold text-ink">{{ opt.label }}</span>
                  <span class="block mt-0.5 text-sm text-muted">{{ opt.desc }}</span>
                </span>
                <span
                  :class="selections[step.id] === opt.id ? 'bg-primary border-primary text-white' : 'border-line'"
                  class="absolute top-4 right-4 w-5 h-5 rounded-full border flex items-center justify-center"
                  aria-hidden="true"
                >
                  <svg v-if="selections[step.id] === opt.id" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg>
                </span>
              </button>
            </div>
          </fieldset>
        </div>

        <!-- Resumen: columna lateral en desktop -->
        <aside class="hidden lg:block w-[360px] shrink-0">
          <div class="sticky top-24 bg-surface border border-line rounded-[var(--radius-panel)] p-6 space-y-6">
            <div>
              <h2 class="text-lg md:text-xl font-extrabold tracking-tight">Resumen</h2>
              <p class="mt-1 text-sm text-muted">Esto es lo que te enviaremos en el mensaje.</p>
            </div>
            <dl class="divide-y divide-line border-y border-line">
              <div v-for="step in steps" :key="step.id" class="flex items-baseline justify-between gap-4 py-3">
                <dt class="text-sm text-muted">{{ step.label }}</dt>
                <dd class="text-sm font-medium text-right">{{ getLabel(step.id, selections[step.id]) }}</dd>
              </div>
            </dl>
            <div>
              <button type="button" :disabled="!hasName" class="btn-primary btn-lg w-full" @click="sendToWhatsapp">
                <svg class="w-5 h-5 fill-current" viewBox="0 0 24 24" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                Enviar por WhatsApp
              </button>
              <p v-if="!hasName" class="mt-2 text-sm text-muted">Escribe tu nombre para continuar.</p>
            </div>
          </div>
        </aside>
      </div>
    </main>

    <!-- Resumen: barra fija en móvil (el widget de WhatsApp se oculta en esta ruta, ver App.vue) -->
    <div class="lg:hidden fixed inset-x-0 bottom-0 z-40 bg-surface border-t border-line">
      <div class="flex items-center gap-3 px-4 sm:px-6 py-3">
        <div class="hidden sm:block flex-1 min-w-0">
          <p class="text-sm font-medium truncate">{{ getLabel('type', selections.type) }}, {{ getLabel('tech', selections.tech) }}</p>
          <p class="text-xs text-muted truncate">{{ hasName ? `${getLabel('finish', selections.finish)}, ${getLabel('priority', selections.priority)}` : 'Escribe tu nombre para continuar' }}</p>
        </div>
        <button type="button" :disabled="!hasName" class="btn-primary flex-1 sm:flex-none" @click="sendToWhatsapp">
          Enviar por WhatsApp
        </button>
      </div>
    </div>

    <AppFooter />
  </div>
</template>
