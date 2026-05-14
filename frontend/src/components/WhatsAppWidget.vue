<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '../services/api'

const router = useRouter()

const isOpen = ref(false)
const userMessage = ref('')
const whatsappNumber = ref('573118796416') // Cargado de parámetros oficiales

const quickReplies = [
    { text: 'Seguir mi pedido', action: 'track' },
    { text: 'Cotización personalizada', msg: 'Hola N3XT! Quiero cotizar un proyecto 3D personalizado.' },
    { text: 'Duda sobre materiales', msg: 'Hola! Tengo una consulta técnica sobre los materiales de impresión.' }
]

const fetchSettings = async () => {
    try {
        const data = await api.get('/settings')
        if (data.web?.social?.whatsapp) {
            // Extraer solo números si es un link de wa.me
            const val = data.web.social.whatsapp
            if (val.includes('wa.me/')) {
                whatsappNumber.value = val.split('wa.me/')[1].split('?')[0]
            } else {
                whatsappNumber.value = val.replace(/\D/g, '')
            }
        }
    } catch (err) {
        console.error('Error fetching WhatsApp settings:', err)
    }
}

const handleQuickReply = (reply) => {
    if (reply.action === 'track') {
        router.push('/track')
        isOpen.value = false
    } else {
        sendMessage(reply.msg)
    }
}

const sendMessage = (msg) => {
    const text = msg || userMessage.value
    if (!text) return
    
    const url = `https://wa.me/${whatsappNumber.value}?text=${encodeURIComponent(text)}`
    window.open(url, '_blank')
    isOpen.value = false
    userMessage.value = ''
}
</script>

<template>
  <div class="fixed bottom-8 right-8 z-[200] font-sans">
    <!-- Chat Window -->
    <transition
      enter-active-class="transition duration-500 ease-[cubic-bezier(0.19,1,0.22,1)]"
      enter-from-class="opacity-0 translate-y-20 scale-90"
      enter-to-class="opacity-100 translate-y-0 scale-100"
      leave-active-class="transition duration-400 ease-[cubic-bezier(0.19,1,0.22,1)]"
      leave-from-class="opacity-100 translate-y-0 scale-100"
      leave-to-class="opacity-0 translate-y-20 scale-90"
    >
      <div v-if="isOpen" class="absolute bottom-24 right-0 w-[380px] bg-white/90 dark:bg-gray-950/90 backdrop-blur-3xl rounded-[3rem] shadow-[0_40px_80px_rgba(0,0,0,0.5)] overflow-hidden border border-white/20 dark:border-white/10 flex flex-col animate-in slide-in-from-bottom-10 duration-700">
        <!-- Header: Industrial Gradient -->
        <div class="bg-gradient-to-br from-[#1e3a34] to-[#0a1f1a] p-10 text-white relative overflow-hidden border-b border-white/10">
            <div class="absolute -right-16 -top-16 w-48 h-48 bg-emerald-500/20 rounded-full blur-[60px] animate-pulse"></div>
            <div class="relative z-10">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></div>
                    <span class="text-[9px] font-black uppercase tracking-[0.4em] text-emerald-400/80">Soporte Técnico Especializado</span>
                </div>
                <h3 class="text-3xl font-black uppercase tracking-tighter italic leading-none mb-2">Chatea con <span class="text-emerald-400">N3XT</span></h3>
                <p class="text-[11px] text-white/60 leading-relaxed font-bold uppercase tracking-wider">Fabricación digital de alta precisión</p>
            </div>
        </div>

        <!-- Body / Input -->
        <div class="p-8 space-y-8">
            <div class="relative group">
                <div class="absolute -inset-1 bg-gradient-to-r from-emerald-500/20 to-primary/20 rounded-3xl blur opacity-0 group-focus-within:opacity-100 transition duration-1000"></div>
                <textarea 
                    v-model="userMessage"
                    placeholder="Describe tu proyecto..."
                    class="relative w-full bg-gray-50 dark:bg-white/5 border border-gray-100 dark:border-white/10 rounded-[2rem] p-6 pr-16 text-xs font-bold text-gray-900 dark:text-white outline-none focus:bg-white dark:focus:bg-white/10 transition-all resize-none h-28 shadow-inner"
                    @keyup.enter="sendMessage()"
                ></textarea>
                <button 
                    @click="sendMessage()"
                    class="absolute right-4 bottom-4 w-12 h-12 bg-emerald-500 text-white rounded-2xl flex items-center justify-center hover:scale-110 hover:shadow-[0_0_20px_rgba(16,185,129,0.4)] transition-all shadow-lg active:scale-95 group/send"
                >
                    <svg class="w-6 h-6 group-hover:translate-x-1 group-hover:-translate-y-1 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                </button>
            </div>

            <div class="space-y-5">
                <div class="flex items-center gap-4">
                    <div class="h-px flex-1 bg-gradient-to-r from-transparent via-gray-200 dark:via-white/10 to-transparent"></div>
                    <p class="text-[8px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-[0.4em]">Protocolos Rápidos</p>
                    <div class="h-px flex-1 bg-gradient-to-r from-transparent via-gray-200 dark:via-white/10 to-transparent"></div>
                </div>
                <div class="grid gap-3">
                    <button 
                        v-for="reply in quickReplies" 
                        :key="reply.text"
                        @click="handleQuickReply(reply)"
                        class="w-full p-5 bg-white dark:bg-white/5 border border-gray-100 dark:border-white/10 rounded-2xl text-[10px] font-black text-gray-700 dark:text-white uppercase tracking-[0.2em] hover:bg-emerald-50 dark:hover:bg-emerald-500/10 hover:border-emerald-500/40 transition-all text-left flex items-center justify-between group/item"
                    >
                        {{ reply.text }}
                        <div class="w-6 h-6 rounded-lg bg-gray-50 dark:bg-white/5 flex items-center justify-center group-hover/item:bg-emerald-500 group-hover/item:text-white transition-colors">
                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M9 5l7 7-7 7" /></svg>
                        </div>
                    </button>
                </div>
            </div>
        </div>

        <!-- Footer: Industrial ID -->
        <div class="p-6 bg-gray-50/50 dark:bg-white/5 border-t border-gray-100 dark:border-white/10 text-center relative overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-r from-transparent via-emerald-500/5 to-transparent"></div>
            <p class="text-[9px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-[0.6em] relative z-10 animate-pulse">N3XT 3D ASSISTANT ACTIVE</p>
        </div>
      </div>
    </transition>

    <!-- Toggle Button -->
    <button 
        @click="isOpen = !isOpen"
        :class="isOpen ? 'bg-gray-900 rotate-180 scale-90 border-white/20' : 'bg-[#1e3a34] shadow-[0_20px_40px_rgba(30,58,52,0.6)] hover:scale-110 border-emerald-500/30'"
        class="w-16 h-16 rounded-full flex items-center justify-center text-white transition-all duration-700 active:scale-95 relative border-2 backdrop-blur-xl group/fab overflow-hidden"
    >
        <!-- Industrial Background Glow -->
        <div class="absolute inset-0 bg-gradient-to-tr from-emerald-500/20 to-transparent opacity-0 group-hover/fab:opacity-100 transition-opacity"></div>
        
        <transition mode="out-in" enter-active-class="transition duration-500 ease-out" enter-from-class="opacity-0 rotate-90 scale-50" leave-active-class="transition duration-400 ease-in" leave-to-class="opacity-0 -rotate-90 scale-50">
            <!-- Deluxe Technical Icon -->
            <div v-if="!isOpen" key="open" class="relative">
                <svg class="w-8 h-8 relative z-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    <circle cx="12" cy="12" r="9" stroke-width="1.5" stroke-dasharray="4 4" class="opacity-30" />
                </svg>
                <div class="absolute inset-0 bg-white blur-lg opacity-20 animate-pulse"></div>
            </div>
            <svg v-else key="close" class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </transition>
        
        <!-- Pulse Indicator -->
        <div v-if="!isOpen" class="absolute inset-0 rounded-full bg-emerald-400 animate-ping opacity-10 pointer-events-none"></div>
    </button>
  </div>
</template>

<style scoped>
textarea::placeholder {
    text-transform: uppercase;
    letter-spacing: 0.1em;
    font-size: 10px;
    opacity: 0.5;
}
</style>
