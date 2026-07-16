<script setup lang="ts">
import { ref, nextTick } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '../services/api'
import logger from '../utils/logger'

const router = useRouter()

const isOpen = ref(false)
const userMessage = ref('')
const whatsappNumber = ref('573118796416')
const isTyping = ref(false)
const messagesRef = ref<HTMLElement | null>(null)

interface Message {
  id: number
  from: 'bot' | 'user'
  text: string
  time: string
}

const messages = ref<Message[]>([
  {
    id: 1,
    from: 'bot',
    text: '¡Hola! 👋 Soy el asistente N3XT. ¿En qué te puedo ayudar con tu proyecto de impresión 3D?',
    time: now()
  }
])

function now() {
  return new Date().toLocaleTimeString('es-CO', { hour: '2-digit', minute: '2-digit' })
}

interface QuickReply { text: string; icon: string; action?: string; msg?: string }

const quickReplies: QuickReply[] = [
  { text: 'Seguir mi pedido', icon: '📦', action: 'track' },
  { text: 'Cotización personalizada', icon: '🖨️', msg: 'Hola N3XT! Quiero cotizar un proyecto de impresión 3D personalizado.' },
  { text: 'Duda sobre materiales', icon: '🧪', msg: 'Hola! Tengo una consulta técnica sobre materiales. ¿Qué material me recomiendan para mi proyecto?' },
  { text: 'Ver catálogo', icon: '📋', action: 'catalog' },
]

const _loadWhatsApp = async () => {
  try {
    const data = await api.get('/settings')
    if (data.web?.social?.whatsapp) {
      const val = data.web.social.whatsapp
      if (val.includes('wa.me/')) {
        whatsappNumber.value = val.split('wa.me/')[1].split('?')[0]
      } else {
        whatsappNumber.value = val.replace(/\D/g, '')
      }
    }
  } catch (err) {
    logger.error('Error fetching WhatsApp settings:', err)
  }
}

const scrollToBottom = async () => {
  await nextTick()
  if (messagesRef.value) {
    messagesRef.value.scrollTop = messagesRef.value.scrollHeight
  }
}

const addBotTyping = async (replyText: string) => {
  isTyping.value = true
  await scrollToBottom()
  await new Promise(r => setTimeout(r, 1200))
  isTyping.value = false
  messages.value.push({
    id: Date.now(),
    from: 'bot',
    text: replyText,
    time: now()
  })
  await scrollToBottom()
}

const handleQuickReply = async (reply: QuickReply) => {
  if (reply.action === 'track') {
    messages.value.push({ id: Date.now(), from: 'user', text: reply.text, time: now() })
    await addBotTyping('Te redirigimos al rastreador de pedidos. ¡Ingresa tu número de orden o email! 🔍')
    await new Promise(r => setTimeout(r, 800))
    router.push('/track')
    isOpen.value = false
    return
  }
  if (reply.action === 'catalog') {
    messages.value.push({ id: Date.now(), from: 'user', text: reply.text, time: now() })
    await addBotTyping('¡Perfecto! Te llevamos al catálogo de productos N3XT 3D 🎨')
    await new Promise(r => setTimeout(r, 800))
    router.push('/catalog')
    isOpen.value = false
    return
  }
  messages.value.push({ id: Date.now(), from: 'user', text: reply.text, time: now() })
  await addBotTyping('¡Perfecto! Te conectamos con nuestro especialista técnico por WhatsApp 🚀')
  await new Promise(r => setTimeout(r, 600))
  sendToWhatsApp(reply.msg || reply.text)
}

const handleSend = async () => {
  const text = userMessage.value.trim()
  if (!text) return
  messages.value.push({ id: Date.now(), from: 'user', text, time: now() })
  userMessage.value = ''
  await scrollToBottom()
  await addBotTyping('Recibido ✓ Te vamos a conectar con nuestro equipo en WhatsApp para darte la mejor asesoría técnica. 💬')
  await new Promise(r => setTimeout(r, 500))
  sendToWhatsApp(text)
}

const sendToWhatsApp = (msg: string) => {
  const url = `https://wa.me/${whatsappNumber.value}?text=${encodeURIComponent(msg)}`
  window.open(url, '_blank')
}

const openWidget = () => {
  isOpen.value = true
  scrollToBottom()
}
</script>

<template>
  <div class="fixed bottom-4 right-4 sm:bottom-8 sm:right-8 z-[200] font-sans flex flex-col items-end">

    <!-- Chat Window -->
    <transition
      enter-active-class="transition duration-500 ease-[cubic-bezier(0.19,1,0.22,1)]"
      enter-from-class="opacity-0 translate-y-8 scale-90"
      enter-to-class="opacity-100 translate-y-0 scale-100"
      leave-active-class="transition duration-300 ease-[cubic-bezier(0.19,1,0.22,1)]"
      leave-from-class="opacity-100 translate-y-0 scale-100"
      leave-to-class="opacity-0 translate-y-8 scale-90"
    >
      <div
        v-if="isOpen"
        class="absolute bottom-20 right-0 w-[calc(100vw-2rem)] sm:w-[390px] bg-[#0d1117] rounded-[2rem] overflow-hidden border border-white/10 flex flex-col"
        style="max-height: min(calc(100vh - 7rem), 680px); box-shadow: 0 30px 80px rgba(0,0,0,0.7), 0 0 0 1px rgba(255,255,255,0.05);"
      >
        <!-- Header -->
        <div class="bg-gradient-to-br from-[#0d2b1f] via-[#0f2318] to-[#091410] p-6 relative overflow-hidden flex-shrink-0 border-b border-white/5">
          <div class="absolute -right-10 -top-10 w-40 h-40 bg-emerald-500/15 rounded-full blur-[50px] pointer-events-none"></div>
          <div class="absolute -left-10 -bottom-10 w-32 h-32 bg-green-400/10 rounded-full blur-[50px] pointer-events-none"></div>

          <div class="flex items-center gap-4 relative z-10">
            <!-- Avatar -->
            <div class="relative shrink-0">
              <div class="w-12 h-12 bg-gradient-to-br from-emerald-400 to-green-600 rounded-[1.25rem] flex items-center justify-center text-xl font-black text-white shadow-lg">
                N
              </div>
              <div class="absolute -bottom-0.5 -right-0.5 w-3.5 h-3.5 bg-emerald-400 rounded-full border-2 border-[#0d1117] animate-pulse"></div>
            </div>

            <!-- Info -->
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2">
                <p class="text-white font-black text-sm tracking-tight truncate">N3XT 3D Assistant</p>
                <span class="px-2 py-0.5 bg-emerald-500/20 text-emerald-400 text-[8px] font-black uppercase tracking-widest rounded-full border border-emerald-500/30">LIVE</span>
              </div>
              <p class="text-white/40 text-[10px] font-medium mt-0.5">Soporte técnico · Responde en segundos</p>
            </div>

            <!-- Close Button -->
            <button
              class="w-8 h-8 rounded-[12px] bg-white/5 hover:bg-white/10 border border-white/10 flex items-center justify-center text-white/60 hover:text-white transition-all active:scale-95 shrink-0"
              aria-label="Cerrar chat"
              @click="isOpen = false"
            >
              <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
        </div>

        <!-- Messages Area -->
        <div
          ref="messagesRef"
          class="flex-1 overflow-y-auto p-5 space-y-4 scrollbar-thin"
          style="background: linear-gradient(180deg, #0d1117 0%, #0a0f14 100%);"
        >
          <!-- Messages -->
          <div
            v-for="msg in messages"
            :key="msg.id"
            :class="['flex gap-3', msg.from === 'user' ? 'flex-row-reverse' : 'flex-row']"
          >
            <!-- Bot avatar -->
            <div v-if="msg.from === 'bot'" class="w-7 h-7 bg-gradient-to-br from-emerald-500 to-green-600 rounded-[10px] flex items-center justify-center text-[10px] font-black text-white shrink-0 mt-auto">
              N
            </div>

            <!-- Bubble -->
            <div
              :class="[
                'max-w-[80%] px-4 py-3 rounded-[1.25rem] text-[12px] font-medium leading-relaxed relative',
                msg.from === 'bot'
                  ? 'bg-[#1a2332] text-white/90 rounded-tl-[6px] border border-white/5'
                  : 'bg-gradient-to-br from-emerald-600 to-green-700 text-white rounded-tr-[6px]'
              ]"
            >
              {{ msg.text }}
              <span class="block text-[8px] mt-1.5 opacity-40 font-bold tracking-wider">{{ msg.time }}</span>
            </div>
          </div>

          <!-- Typing Indicator -->
          <transition
            enter-active-class="transition duration-300 ease-out"
            enter-from-class="opacity-0 translate-y-2"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition duration-200 ease-in"
            leave-to-class="opacity-0"
          >
            <div v-if="isTyping" class="flex gap-3 items-end">
              <div class="w-7 h-7 bg-gradient-to-br from-emerald-500 to-green-600 rounded-[10px] flex items-center justify-center text-[10px] font-black text-white shrink-0">
                N
              </div>
              <div class="bg-[#1a2332] border border-white/5 px-4 py-3 rounded-[1.25rem] rounded-tl-[6px] flex items-center gap-1.5">
                <span class="w-1.5 h-1.5 bg-emerald-400 rounded-full animate-bounce" style="animation-delay:0ms"></span>
                <span class="w-1.5 h-1.5 bg-emerald-400 rounded-full animate-bounce" style="animation-delay:150ms"></span>
                <span class="w-1.5 h-1.5 bg-emerald-400 rounded-full animate-bounce" style="animation-delay:300ms"></span>
              </div>
            </div>
          </transition>
        </div>

        <!-- Quick Replies -->
        <div class="px-4 pb-3 flex-shrink-0 border-t border-white/5 pt-3" style="background:#0a0f14;">
          <p class="text-[8px] font-black text-white/25 uppercase tracking-[0.4em] mb-2.5 pl-1">Accesos Rápidos</p>
          <div class="grid grid-cols-2 gap-2">
            <button
              v-for="reply in quickReplies"
              :key="reply.text"
              class="flex items-center gap-2 p-3 bg-[#151c26] border border-white/5 rounded-[14px] text-[10px] font-bold text-white/70 hover:text-white hover:bg-[#1e2d3d] hover:border-emerald-500/30 transition-all active:scale-95 text-left group"
              @click="handleQuickReply(reply)"
            >
              <span class="text-base shrink-0 group-hover:scale-110 transition-transform">{{ reply.icon }}</span>
              <span class="truncate leading-tight">{{ reply.text }}</span>
            </button>
          </div>
        </div>

        <!-- Input Area -->
        <div class="p-4 flex-shrink-0 border-t border-white/5" style="background:#080d11;">
          <div class="flex gap-3 items-end">
            <div class="flex-1 relative">
              <textarea
                v-model="userMessage"
                placeholder="Escribe tu consulta..."
                rows="1"
                class="w-full bg-[#151c26] border border-white/8 rounded-[1rem] px-4 py-3 text-xs font-medium text-white outline-none focus:border-emerald-500/40 focus:bg-[#1a2332] transition-all resize-none"
                style="max-height: 80px;"
                @keydown.enter.exact.prevent="handleSend"
              ></textarea>
            </div>
            <button
              class="w-10 h-10 bg-gradient-to-br from-emerald-500 to-green-600 text-white rounded-[14px] flex items-center justify-center hover:from-emerald-400 hover:to-green-500 transition-all active:scale-90 shrink-0 shadow-lg shadow-emerald-500/20"
              @click="handleSend"
            >
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
              </svg>
            </button>
          </div>
          <p class="text-[8px] text-white/20 font-bold uppercase tracking-widest text-center mt-2.5">
            Conectado vía WhatsApp · N3XT 3D
          </p>
        </div>
      </div>
    </transition>

    <!-- FAB Toggle Button -->
    <button
      :class="[
        'w-14 h-14 sm:w-16 sm:h-16 rounded-[1.25rem] flex items-center justify-center text-white transition-all duration-500 active:scale-90 relative overflow-hidden border',
        isOpen
          ? 'bg-[#1a2332] border-white/10 rotate-12'
          : 'bg-gradient-to-br from-emerald-500 to-green-600 border-emerald-400/30 hover:scale-105'
      ]"
      style="box-shadow: 0 8px 30px rgba(16,185,129,0.35)"
      @click="isOpen ? (isOpen = false) : openWidget()"
    >
      <div v-if="!isOpen" class="absolute inset-0 bg-gradient-to-tr from-transparent via-white/10 to-transparent pointer-events-none"></div>

      <transition
        mode="out-in"
        enter-active-class="transition duration-300 ease-out"
        enter-from-class="opacity-0 rotate-90 scale-50"
        leave-active-class="transition duration-200 ease-in"
        leave-to-class="opacity-0 -rotate-90 scale-50"
      >
        <svg v-if="!isOpen" key="open" class="w-6 h-6 sm:w-7 sm:h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
        </svg>
        <svg v-else key="close" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
        </svg>
      </transition>

      <!-- Pulse ring when closed -->
      <div v-if="!isOpen" class="absolute -inset-1 rounded-[1.5rem] bg-emerald-500/20 animate-ping pointer-events-none"></div>
    </button>
  </div>
</template>

<style scoped>
textarea::placeholder {
  color: rgba(255,255,255,0.25);
  font-size: 11px;
}
.scrollbar-thin::-webkit-scrollbar { width: 4px; }
.scrollbar-thin::-webkit-scrollbar-track { background: transparent; }
.scrollbar-thin::-webkit-scrollbar-thumb { background: rgba(16,185,129,0.15); border-radius: 10px; }
.scrollbar-thin::-webkit-scrollbar-thumb:hover { background: rgba(16,185,129,0.3); }

.animate-bounce {
  animation: bounce 0.8s infinite;
}
@keyframes bounce {
  0%, 80%, 100% { transform: translateY(0); }
  40% { transform: translateY(-5px); }
}
</style>
