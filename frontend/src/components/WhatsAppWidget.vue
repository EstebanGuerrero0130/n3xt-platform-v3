<script setup lang="ts">
import { ref, watch, nextTick, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '../services/api'
import logger from '../utils/logger'
import { DEFAULT_WHATSAPP_NUMBER, parseWhatsAppNumber, whatsappLink } from '../utils/contact'

const router = useRouter()

const isOpen = ref(false)
const userMessage = ref('')
const whatsappNumber = ref(DEFAULT_WHATSAPP_NUMBER) // Fallback; se sobrescribe con el número configurado en admin

interface QuickReply { text: string; action?: 'track'; msg?: string }

const quickReplies: QuickReply[] = [
    { text: 'Seguir mi pedido', action: 'track' },
    { text: 'Cotizar un proyecto', msg: 'Hola N3XT! Quiero cotizar un proyecto 3D personalizado.' },
    { text: 'Dudas de materiales', msg: 'Hola! Tengo una consulta técnica sobre los materiales de impresión.' }
]

const loadWhatsApp = async () => {
    try {
        const data = await api.get('/settings')
        const num = parseWhatsAppNumber(data?.web?.social?.whatsapp)
        if (num) whatsappNumber.value = num
    } catch (err) {
        logger.error('Error fetching WhatsApp settings:', err)
    }
}

const handleQuickReply = (reply: QuickReply) => {
    if (reply.action === 'track') {
        router.push('/track')
        isOpen.value = false
    } else {
        sendMessage(reply.msg)
    }
}

const sendMessage = (msg?: string) => {
    const text = (msg || userMessage.value).trim()
    if (!text) return

    window.open(whatsappLink(whatsappNumber.value, text), '_blank', 'noopener,noreferrer')
    isOpen.value = false
    userMessage.value = ''
}

const onKeydown = (e: KeyboardEvent) => {
    if (e.key === 'Escape' && isOpen.value) isOpen.value = false
}

// Al abrir, foco dentro del panel; al cerrar, de vuelta al botón flotante
const panel = ref<HTMLElement | null>(null)
const toggle = ref<HTMLButtonElement | null>(null)
watch(isOpen, (open) => {
    if (open) nextTick(() => panel.value?.querySelector<HTMLElement>('textarea, input, button')?.focus())
    else if (!document.activeElement || document.activeElement === document.body || panel.value?.contains(document.activeElement)) toggle.value?.focus()
})

onMounted(() => {
    loadWhatsApp()
    window.addEventListener('keydown', onKeydown)
})

onUnmounted(() => {
    window.removeEventListener('keydown', onKeydown)
})
</script>

<template>
  <div class="fixed bottom-4 right-4 sm:bottom-6 sm:right-6 z-[200] flex flex-col items-end">
    <transition
      enter-active-class="transition duration-250 ease-out"
      enter-from-class="opacity-0 translate-y-3"
      enter-to-class="opacity-100 translate-y-0"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="opacity-100 translate-y-0"
      leave-to-class="opacity-0 translate-y-3"
    >
      <div v-if="isOpen" ref="panel" role="dialog" aria-labelledby="wa-widget-title" class="absolute bottom-[4.25rem] right-0 w-[calc(100vw-2rem)] max-w-[320px] max-h-[calc(100dvh-6rem)] overflow-y-auto bg-surface rounded-[var(--radius-card)] shadow-[0_24px_48px_-12px_rgba(0,0,0,0.45)] p-4">
        <div class="flex items-start justify-between gap-3">
          <div>
            <h3 id="wa-widget-title" class="text-base font-extrabold text-ink">Escríbenos por WhatsApp</h3>
            <p class="mt-0.5 text-sm text-muted">Te responde una persona del taller.</p>
          </div>
          <button
            class="-mr-1 -mt-1 w-9 h-9 shrink-0 rounded-full flex items-center justify-center text-muted hover:text-ink hover:bg-surface-2 transition-colors"
            aria-label="Cerrar chat"
            @click="isOpen = false"
          >
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
          </button>
        </div>

        <ul class="mt-3 flex flex-wrap gap-2" aria-label="Temas rápidos">
          <li v-for="reply in quickReplies" :key="reply.text">
            <button
              class="h-9 px-3 rounded-full bg-surface-2 text-sm font-medium text-ink hover:bg-primary hover:text-white transition-colors"
              @click="handleQuickReply(reply)"
            >{{ reply.text }}</button>
          </li>
        </ul>

        <label for="wa-widget-message" class="sr-only">Tu mensaje</label>
        <textarea
                id="wa-widget-message"
                v-model="userMessage"
                placeholder="Cuéntanos qué quieres imprimir"
                class="mt-3 block w-full h-[4.5rem] resize-none rounded-[var(--radius-control)] border border-line bg-canvas px-3 py-2.5 text-[0.9375rem] text-ink placeholder:text-muted/70 focus:outline-none focus:border-primary focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-[var(--brand-text)] transition-colors"
                @keydown.enter.exact.prevent="sendMessage()"
              ></textarea>
        <button class="btn-primary w-full mt-2" @click="sendMessage()">
          <svg class="w-4 h-4" aria-hidden="true" fill="currentColor" viewBox="0 0 362 362"><path d="M307.546 52.566C273.709 18.684 228.706.017 180.756 0 81.951 0 1.538 80.404 1.504 179.235c-.017 31.594 8.242 62.432 23.928 89.609L0 361.736l95.024-24.925c26.179 14.285 55.659 21.805 85.655 21.814h.077c98.788 0 179.21-80.413 179.244-179.244.017-47.898-18.608-92.926-52.454-126.807v-.008Zm-126.79 275.788h-.06c-26.73-.008-52.952-7.194-75.831-20.765l-5.44-3.231-56.391 14.791 15.05-54.981-3.542-5.638c-14.912-23.721-22.793-51.139-22.776-79.286.035-82.14 66.867-148.973 149.051-148.973 39.793.017 77.198 15.53 105.328 43.695 28.131 28.157 43.61 65.596 43.593 105.398-.035 82.149-66.867 148.982-148.982 148.982v.008Zm81.719-111.577c-4.478-2.243-26.497-13.073-30.606-14.568-4.108-1.496-7.09-2.243-10.073 2.243-2.982 4.487-11.568 14.577-14.181 17.559-2.613 2.991-5.226 3.361-9.704 1.117-4.477-2.243-18.908-6.97-36.02-22.226-13.313-11.878-22.304-26.54-24.916-31.027-2.613-4.486-.275-6.91 1.959-9.136 2.011-2.011 4.478-5.234 6.721-7.847 2.244-2.613 2.983-4.486 4.478-7.469 1.496-2.991.748-5.603-.369-7.847-1.118-2.243-10.073-24.289-13.812-33.253-3.636-8.732-7.331-7.546-10.073-7.692-2.613-.13-5.595-.155-8.586-.155-2.991 0-7.839 1.118-11.947 5.604-4.108 4.486-15.677 15.324-15.677 37.361s16.047 43.344 18.29 46.335c2.243 2.991 31.585 48.225 76.51 67.632 10.684 4.615 19.029 7.374 25.535 9.437 10.727 3.412 20.49 2.931 28.208 1.779 8.604-1.289 26.498-10.838 30.228-21.298 3.73-10.46 3.73-19.433 2.613-21.298-1.117-1.865-4.108-2.991-8.586-5.234l.008-.017Z"/></svg>
          Abrir WhatsApp
        </button>
      </div>
    </transition>

    <button
      ref="toggle"
      class="w-14 h-14 rounded-full flex items-center justify-center text-white bg-[#0f7a40] hover:bg-[#0c6634] shadow-[0_10px_24px_-6px_rgba(0,0,0,0.4)] transition-colors"
      :aria-label="isOpen ? 'Cerrar chat de WhatsApp' : 'Abrir chat de WhatsApp'"
      :aria-expanded="isOpen"
      @click="isOpen = !isOpen"
    >
      <svg v-if="isOpen" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" /></svg>
      <span v-else class="[&>svg]:w-7 [&>svg]:h-7"><svg class="w-4 h-4" aria-hidden="true" fill="currentColor" viewBox="0 0 362 362"><path d="M307.546 52.566C273.709 18.684 228.706.017 180.756 0 81.951 0 1.538 80.404 1.504 179.235c-.017 31.594 8.242 62.432 23.928 89.609L0 361.736l95.024-24.925c26.179 14.285 55.659 21.805 85.655 21.814h.077c98.788 0 179.21-80.413 179.244-179.244.017-47.898-18.608-92.926-52.454-126.807v-.008Zm-126.79 275.788h-.06c-26.73-.008-52.952-7.194-75.831-20.765l-5.44-3.231-56.391 14.791 15.05-54.981-3.542-5.638c-14.912-23.721-22.793-51.139-22.776-79.286.035-82.14 66.867-148.973 149.051-148.973 39.793.017 77.198 15.53 105.328 43.695 28.131 28.157 43.61 65.596 43.593 105.398-.035 82.149-66.867 148.982-148.982 148.982v.008Zm81.719-111.577c-4.478-2.243-26.497-13.073-30.606-14.568-4.108-1.496-7.09-2.243-10.073 2.243-2.982 4.487-11.568 14.577-14.181 17.559-2.613 2.991-5.226 3.361-9.704 1.117-4.477-2.243-18.908-6.97-36.02-22.226-13.313-11.878-22.304-26.54-24.916-31.027-2.613-4.486-.275-6.91 1.959-9.136 2.011-2.011 4.478-5.234 6.721-7.847 2.244-2.613 2.983-4.486 4.478-7.469 1.496-2.991.748-5.603-.369-7.847-1.118-2.243-10.073-24.289-13.812-33.253-3.636-8.732-7.331-7.546-10.073-7.692-2.613-.13-5.595-.155-8.586-.155-2.991 0-7.839 1.118-11.947 5.604-4.108 4.486-15.677 15.324-15.677 37.361s16.047 43.344 18.29 46.335c2.243 2.991 31.585 48.225 76.51 67.632 10.684 4.615 19.029 7.374 25.535 9.437 10.727 3.412 20.49 2.931 28.208 1.779 8.604-1.289 26.498-10.838 30.228-21.298 3.73-10.46 3.73-19.433 2.613-21.298-1.117-1.865-4.108-2.991-8.586-5.234l.008-.017Z"/></svg></span>
    </button>
  </div>
</template>
