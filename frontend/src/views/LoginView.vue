<script setup>
import { ref, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '../services/api'

const mode = ref('password') // 'password' or 'register'
const isDark = ref(localStorage.getItem('n3xt_theme') === 'dark')

const toggleDarkMode = () => {
  isDark.value = !isDark.value
  const theme = isDark.value ? 'dark' : 'light'
  localStorage.setItem('n3xt_theme', theme)
  document.documentElement.classList.toggle('dark', isDark.value)
}

// --- CAPTCHA PROTOCOL ---
const captchaChallenge = ref({ a: 0, b: 0, result: 0 })
const captchaAnswer = ref('')
const generateCaptcha = () => {
    captchaChallenge.value.a = Math.floor(Math.random() * 10) + 1
    captchaChallenge.value.b = Math.floor(Math.random() * 10) + 1
    captchaChallenge.value.result = captchaChallenge.value.a + captchaChallenge.value.b
    captchaAnswer.value = ''
}

watch(mode, (newMode) => {
    if (newMode === 'register') generateCaptcha()
})

const email = ref('')
const password = ref('')
const password_confirmation = ref('')
const name = ref('')
const rememberMe = ref(false)
const error = ref('')
const loading = ref(false)
const router = useRouter()
const showPassword = ref(false)
const honeypot = ref('')
const lastAttemptTime = ref(0)
const selectedRole = ref(window.location.pathname.includes('admin') ? 'admin' : 'customer')

onMounted(() => {
  // Redirigir si ya hay sesión activa
  const adminToken = localStorage.getItem('n3xt_admin_token')
  
  if (adminToken) {
    router.push('/admin')
    return
  }

  const savedEmail = localStorage.getItem('n3xt_remember_email')
  if (savedEmail) {
    email.value = savedEmail
    rememberMe.value = true
  }
})



const login = async () => {
  // 1. Honeypot check (Bot trap)
  if (honeypot.value) return;

  // 2. Brute-force protection
  const now = Date.now();
  if (now - lastAttemptTime.value < 2000) {
    error.value = "Demasiados intentos. Espera un momento.";
    return;
  }
  lastAttemptTime.value = now;

  error.value = ''
  loading.value = true
  try {
    const data = await api.post('/auth-master-industrial-access', {
        email: email.value,
        password: password.value
    })
    
    // El servidor nos dirá si somos Admin o Customer
    console.log("Respuesta de login:", data);
    
    if (data.role === 'admin' || email.value.includes('admin@n3xt3d.com')) {
        localStorage.setItem('n3xt_admin_token', data.token)
        if (rememberMe.value) localStorage.setItem('n3xt_remember_email', email.value)
        else localStorage.removeItem('n3xt_remember_email')
        
        // Forzar redirección limpia usando window.location para resetear el estado de vue-router
        window.location.href = '/admin'
    } else {
        localStorage.setItem('n3xt_customer_token', data.token)
        window.location.href = '/customer/dashboard'
    }
  } catch (err) {
    error.value = err.response?.data?.message || err.message
  } finally {
    loading.value = false
  }
}

const register = async () => {
  // 1. Honeypot check (Bot trap)
  if (honeypot.value) return;

  // 2. Brute-force protection
  const now = Date.now();
  if (now - lastAttemptTime.value < 5000) {
    error.value = "Espera un momento antes de registrarte de nuevo.";
    return;
  }

  if (parseInt(captchaAnswer.value) !== captchaChallenge.value.result) {
    error.value = "Error de Verificación: Resuelve el reto matemático correctamente."
    generateCaptcha()
    return
  }
  
  lastAttemptTime.value = now;
  error.value = ''
  loading.value = true
  try {
    const data = await api.post('/auth/unified-register', {
        name: name.value,
        email: email.value,
        password: password.value,
        password_confirmation: password_confirmation.value
    })
    
    localStorage.setItem('n3xt_customer_token', data.token)
    router.push('/customer/dashboard')
  } catch (err) {
    error.value = err.response?.data?.message || err.message
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="min-h-screen bg-[#f8fafc] dark:bg-black flex flex-col items-center justify-center p-6 md:p-12 overflow-hidden relative transition-colors duration-500">
    <!-- Technical Background -->
    <div class="absolute inset-0 technical-grid opacity-30 dark:opacity-20 pointer-events-none"></div>
    <div class="absolute top-[-10%] left-[-10%] w-[60%] h-[60%] bg-primary/5 rounded-full blur-[140px] pointer-events-none"></div>
    <div class="absolute bottom-[-10%] right-[-10%] w-[60%] h-[60%] bg-emerald-500/5 rounded-full blur-[140px] pointer-events-none"></div>

    <!-- Layout Container -->
    <div class="w-full max-w-7xl mx-auto flex flex-col items-center relative min-h-[inherit] justify-center">
        
        <!-- Theme & Back Button Group -->
        <div class="md:absolute top-0 left-0 right-0 flex justify-between items-center mb-8 md:mb-0 px-0 md:px-8 z-50 w-full">
            <router-link to="/" class="group flex items-center gap-3 px-6 py-3 bg-white dark:bg-gray-900 border border-gray-100 dark:border-white/5 rounded-full shadow-sm hover:shadow-xl transition-all hover:-translate-x-1">
                <svg class="w-4 h-4 text-gray-400 group-hover:text-primary transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M10 19l-7-7m0 0l7 7m7-7"/></svg>
                <span class="text-[9px] font-black text-gray-400 uppercase tracking-[0.2em] group-hover:text-primary">Volver al Inicio</span>
            </router-link>

            <button 
                @click="toggleDarkMode" 
                class="w-12 h-12 bg-white dark:bg-gray-900 border border-gray-100 dark:border-white/5 text-gray-400 hover:text-primary rounded-full flex items-center justify-center transition-all shadow-sm hover:shadow-xl"
            >
                <svg v-if="isDark" class="w-6 h-6 animate-in zoom-in spin-in-90 duration-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 9H3m15.364-6.364l-.707.707M6.343 17.657l-.707.707m12.728 0l-.707-.707M6.343 6.343l-.707-.707M12 8a4 4 0 100 8 4 4 0 000-8z"/></svg>
                <svg v-else class="w-6 h-6 animate-in zoom-in spin-in-90 duration-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/></svg>
            </button>
        </div>

        <div class="w-full max-w-xl animate-in fade-in zoom-in duration-1000 relative z-10">
          
          <!-- Main Container -->
          <div class="bg-white/90 dark:bg-gray-950/90 backdrop-blur-3xl border border-white dark:border-white/5 p-8 md:p-16 rounded-[4.5rem] shadow-[0_60px_120px_-30px_rgba(0,0,0,0.08)] relative overflow-hidden">
            
            <!-- Header Unificado -->
            
            <!-- Honeypot (Bot Trap) -->
            <div style="opacity: 0; position: absolute; top: 0; left: 0; height: 0; width: 0; z-index: -1; overflow: hidden;">
                <input type="text" v-model="honeypot" tabindex="-1" autocomplete="off">
            </div>

            <transition name="fade-slide" mode="out-in">
                <div :key="mode" class="text-center mb-12">
                    <div class="space-y-4">
                        <div class="w-20 h-20 bg-gray-900 dark:bg-white dark:text-gray-900 rounded-[2rem] mx-auto flex items-center justify-center shadow-2xl shadow-black/20 mb-6">
                            <svg v-if="selectedRole === 'admin'" class="w-8 h-8 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                            <svg v-else class="w-8 h-8 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        </div>
                        <h2 class="text-3xl font-black text-gray-900 dark:text-white tracking-tighter uppercase italic">
                            Acceso <span class="text-primary">N3XT</span>
                        </h2>
                        
                        <!-- Role Selector Tabs -->
                        <div class="flex bg-gray-100 dark:bg-white/5 p-1.5 rounded-[1.5rem] max-w-[320px] mx-auto mt-8 border border-gray-200 dark:border-white/5">
                            <button 
                                @click="selectedRole = 'customer'"
                                :class="['flex-1 py-3 px-6 rounded-2xl text-[9px] font-black uppercase tracking-widest transition-all', selectedRole === 'customer' ? 'bg-white dark:bg-gray-800 text-primary shadow-xl' : 'text-gray-400 hover:text-gray-600 dark:hover:text-white']"
                            >
                                Cliente
                            </button>
                            <button 
                                @click="selectedRole = 'admin'"
                                :class="['flex-1 py-3 px-6 rounded-2xl text-[9px] font-black uppercase tracking-widest transition-all', selectedRole === 'admin' ? 'bg-white dark:bg-gray-800 text-primary shadow-xl' : 'text-gray-400 hover:text-gray-600 dark:hover:text-white']"
                            >
                                Administrador
                            </button>
                        </div>
                    </div>
                </div>
            </transition>

            <!-- Forms -->
            <transition name="fade-slide" mode="out-in">
                <div :key="mode" class="space-y-8">
                    
                    <div class="space-y-6">
                        <!-- Mode Switcher (Solo para Login) -->


                        <!-- PASSWORD LOGIN (Unified) -->
                        <form v-if="mode === 'password'" @submit.prevent="login" class="space-y-6">
                            <div v-if="error" class="bg-rose-50 dark:bg-rose-500/10 border border-rose-100 dark:border-rose-500/20 text-rose-500 dark:text-rose-400 p-5 rounded-2xl text-[10px] font-black uppercase tracking-widest text-center">{{ error }}</div>
                            <div class="space-y-3">
                                <label class="text-[10px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-widest ml-5">Correo Electrónico</label>
                                <input type="email" v-model="email" required placeholder="tu@ejemplo.com" class="w-full bg-gray-50/50 dark:bg-gray-900/50 border border-gray-100 dark:border-white/10 rounded-3xl p-6 text-gray-900 dark:text-white font-bold text-base outline-none focus:ring-4 focus:ring-primary/5 focus:border-primary/20 transition-all">
                            </div>
                            <div class="space-y-3">
                                <label class="text-[10px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-widest ml-5">Contraseña</label>
                                <div class="relative group">
                                    <input :type="showPassword ? 'text' : 'password'" v-model="password" required placeholder="••••••••" class="w-full bg-gray-50/50 dark:bg-gray-900/50 border border-gray-100 dark:border-white/10 rounded-3xl p-6 text-gray-900 dark:text-white font-bold text-base outline-none focus:ring-4 focus:ring-primary/5 focus:border-primary/20 transition-all pr-16">
                                    <button type="button" @click="showPassword = !showPassword" class="absolute right-6 top-1/2 -translate-y-1/2 text-gray-400 hover:text-primary transition-colors p-2">
                                        <svg v-if="!showPassword" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                        <svg v-else class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7 1.274-4.057-5.064-7-9.542-7-4.477 0-8.268-2.943-9.542-7zM15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3l18 18"/></svg>
                                    </button>
                                </div>
                            </div>
                            <button type="submit" :disabled="loading" class="w-full bg-gray-900 dark:bg-white text-white dark:text-gray-900 font-black py-6 rounded-3xl shadow-2xl hover:bg-primary dark:hover:bg-primary dark:hover:text-white transition-all active:scale-95 text-[10px] uppercase tracking-[0.2em] mt-4">
                                {{ loading ? 'AUTENTICANDO...' : 'INICIAR SESIÓN' }}
                            </button>

                            <p class="text-center text-[10px] font-black text-gray-400 uppercase tracking-[0.2em] mt-6">
                                ¿ERES NUEVO? <button type="button" @click="mode = 'register'" class="text-primary hover:underline ml-2">REGÍSTRATE AQUÍ</button>
                            </p>
                        </form>



                        <!-- REGISTER FORM -->
                        <form v-else-if="mode === 'register'" @submit.prevent="register" class="space-y-6 animate-in slide-in-from-right-10 duration-500">
                            <div v-if="error" class="bg-rose-50 dark:bg-rose-500/10 border border-rose-100 dark:border-rose-500/20 text-rose-500 dark:text-rose-400 p-5 rounded-2xl text-[10px] font-black uppercase tracking-widest text-center">{{ error }}</div>
                            <div class="space-y-3">
                                <label class="text-[10px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-widest ml-5">Nombre Completo</label>
                                <input type="text" v-model="name" required placeholder="Juan Pérez" class="w-full bg-gray-50/50 dark:bg-gray-900/50 border border-gray-100 dark:border-white/10 rounded-3xl p-6 text-gray-900 dark:text-white font-bold text-base outline-none focus:ring-4 focus:ring-primary/5 focus:border-primary/20 transition-all">
                            </div>
                            <div class="space-y-3">
                                <label class="text-[10px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-widest ml-5">Email Principal</label>
                                <input type="email" v-model="email" required placeholder="tu@correo.com" class="w-full bg-gray-50/50 dark:bg-gray-900/50 border border-gray-100 dark:border-white/10 rounded-3xl p-6 text-gray-900 dark:text-white font-bold text-base outline-none focus:ring-4 focus:ring-primary/5 focus:border-primary/20 transition-all">
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="space-y-3">
                                    <label class="text-[10px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-widest ml-5">Contraseña</label>
                                    <input type="password" v-model="password" required placeholder="••••••••" class="w-full bg-gray-50/50 dark:bg-gray-900/50 border border-gray-100 dark:border-white/10 rounded-3xl p-6 text-gray-900 dark:text-white font-bold text-base outline-none focus:ring-4 focus:ring-primary/5 focus:border-primary/20 transition-all">
                                </div>
                                <div class="space-y-3">
                                    <label class="text-[10px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-widest ml-5">Confirmar</label>
                                    <input type="password" v-model="password_confirmation" required placeholder="••••••••" class="w-full bg-gray-50/50 dark:bg-gray-900/50 border border-gray-100 dark:border-white/10 rounded-3xl p-6 text-gray-900 dark:text-white font-bold text-base outline-none focus:ring-4 focus:ring-primary/5 focus:border-primary/20 transition-all">
                                </div>
                            </div>

                            <!-- Human Verification -->
                            <div class="p-6 bg-gray-900 dark:bg-black rounded-[2.5rem] text-white flex items-center justify-between gap-6 border border-gray-800 dark:border-white/5">
                                <div class="flex-1">
                                    <p class="text-[8px] font-black text-primary uppercase tracking-[0.4em] mb-1">Verificacion de seguridad</p>
                                    <p class="text-sm font-black italic uppercase">Resuelve: {{ captchaChallenge.a }} + {{ captchaChallenge.b }} = ?</p>
                                </div>
                                <input type="number" v-model="captchaAnswer" required placeholder="?" class="w-20 bg-white/10 dark:bg-white/5 border border-white/20 dark:border-white/10 rounded-2xl p-4 text-center text-white font-black outline-none focus:border-primary transition-all">
                            </div>

                            <button type="submit" :disabled="loading" class="w-full bg-primary text-white font-black py-6 rounded-3xl shadow-2xl hover:bg-gray-900 transition-all active:scale-95 text-[10px] uppercase tracking-[0.2em] mt-4">
                                {{ loading ? 'CREANDO...' : 'CREAR MI CUENTA' }}
                            </button>
                            <p class="text-center text-[10px] font-black text-gray-400 uppercase tracking-[0.2em] mt-6">
                                ¿YA TIENES CUENTA? <button type="button" @click="mode = 'password'" class="text-primary hover:underline ml-2">INICIA SESIÓN</button>
                            </p>
                        </form>
                    </div>
                </div>
            </transition>

            <div class="mt-14 text-center">
                <p class="text-[8px] font-black text-gray-300 uppercase tracking-[0.5em]">N3XT — Acceso seguro</p>
            </div>
          </div>
        </div>
    </div>
  </div>
</template>

<style scoped>
.technical-grid {
  background-size: 50px 50px;
  background-image: 
    linear-gradient(to right, rgba(30, 58, 52, 0.05) 1px, transparent 1px),
    linear-gradient(to bottom, rgba(30, 58, 52, 0.05) 1px, transparent 1px);
}

.dark .technical-grid {
  background-image: 
    linear-gradient(to right, rgba(255, 255, 255, 0.03) 1px, transparent 1px),
    linear-gradient(to bottom, rgba(255, 255, 255, 0.03) 1px, transparent 1px);
}

.fade-slide-enter-active,
.fade-slide-leave-active {
  transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
}

.fade-slide-enter-from {
  opacity: 0;
  transform: translateY(20px);
}

.fade-slide-leave-to {
  opacity: 0;
  transform: translateY(-20px);
}
</style>
