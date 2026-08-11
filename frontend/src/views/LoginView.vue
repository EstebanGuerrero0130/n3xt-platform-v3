<script setup lang="ts">
import { ref, onMounted, onUnmounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '../services/api'
import { supabase } from '../services/supabase'
import store from '../utils/cache'
import { useSplitTitle } from '../composables/useSplitTitle'
import { useSplitButton } from '../composables/useSplitButton'
import { useCaptcha } from '../composables/useCaptcha'
import { usePageMeta } from '../composables/usePageMeta'

useSplitTitle()
useSplitButton()

usePageMeta({
 title: 'Acceso N3XT 3D | Login',
 description: 'Accede al panel de control N3XT 3D. Inicia sesion como cliente o administrador.',
 image: '/assets/n3xt_og_login.png',
})

// ─── SEO via @unhead/vue (usePageMeta at setup) ───

const mode = ref('password') // 'password' or 'register'

// --- CAPTCHA PROTOCOL ---
const { challenge, answer, verify, reset, isLocked } = useCaptcha()

watch(mode, (newMode) => {
 if (newMode === 'register') reset()
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

// Limpiar errores al cambiar de rol
watch(selectedRole, () => {
  error.value = ''
  email.value = ''
  password.value = ''
})

onMounted(async () => {
 // Redirigir si ya hay sesión activa
 const authStatus = await api.checkAuth()
 
 if (authStatus.authenticated) {
 if (authStatus.role === 'admin') {
 router.push('/admin')
 } else {
 router.push('/customer/dashboard')
 }
 return
 }

 const savedEmail = store.get('remember_email')
 if (savedEmail) {
 email.value = savedEmail
 rememberMe.value = true
 }
})



onUnmounted(() => {
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
 
 // Sanctum SPA: session cookie is set automatically by the server
 // No manual token storage needed
 
 if (rememberMe.value) store.set('remember_email', email.value)
 else store.remove('remember_email')
 
 // Confiar en el role de la API como fuente única de verdad
 // Solo usar email como fallback si la API no devuelve role
 const role = data.role || (email.value.includes('n3xt@admin.com') ? 'admin' : 'customer')
 if (role === 'admin') {
 window.location.href = '/admin'
 } else {
 window.location.href = '/customer/dashboard'
 }
 } catch (err: any) {
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

 if (!verify()) {
 error.value = isLocked.value
 ? "Demasiados intentos. Espera 30 segundos."
 : "Error de Verificación: Resuelve el reto matemático correctamente."
 return
 }
 
 lastAttemptTime.value = now;
 error.value = ''
 loading.value = true
 try {
 await api.post('/auth/unified-register', {
 name: name.value,
 email: email.value,
 password: password.value,
 password_confirmation: password_confirmation.value
 })
 
 // Sanctum SPA: session cookie is set automatically
 router.push('/customer/dashboard')
 } catch (err: any) {
 error.value = err.response?.data?.message || err.message
 } finally {
 loading.value = false
 }
}

const loginWithGoogle = async () => {
  error.value = ''
  loading.value = true
  try {
    const { error: supaError } = await supabase.auth.signInWithOAuth({
      provider: 'google',
      options: {
        redirectTo: `${window.location.origin}/auth/callback`
      }
    })
    
    if (supaError) throw supaError
  } catch (err: any) {
    error.value = err.message || 'Error al conectar con Google'
    loading.value = false
  }
}

</script>

<template>
 <main class="min-h-screen bg-[#f8fafc] dark:bg-black flex flex-col items-center justify-center p-6 md:p-12 overflow-hidden relative transition-colors duration-500">
 <!-- Technical Background -->
 <div class="absolute inset-0 technical-grid opacity-30 dark:opacity-20 pointer-events-none"></div>
 <div class="absolute top-[-10%] left-[-10%] w-[60%] h-[60%] bg-[#08872b]/5 rounded-[60px] blur-[140px] pointer-events-none"></div>
 <div class="absolute bottom-[-10%] right-[-10%] w-[60%] h-[60%] bg-emerald-500/5 rounded-[60px] blur-[140px] pointer-events-none"></div>

 <!-- Layout Container -->
 <div class="w-full max-w-7xl mx-auto flex flex-col items-center relative min-h-[inherit] justify-center">
 
 <!-- Theme & Back Button Group -->
 <div class="md:absolute top-0 left-0 right-0 flex justify-between items-center mb-8 md:mb-0 px-0 md:px-8 z-50 w-full">
 <router-link to="/" class="group flex items-center gap-3 px-6 py-3 bg-[#151a22] dark:bg-[#151a22] border border-[#21262d] dark:border-[#21262d] rounded-[60px] hover: transition-all hover:-translate-x-1">
 <svg class="w-4 h-4 text-[#c3c4c5] group-hover:text-[#8dd6ff] transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M10 19l-7-7m0 0l7 7m7-7"/></svg>
 <span class="text-[9px] font-black text-[#c3c4c5] uppercase tracking-[0.2em] group-hover:text-[#8dd6ff]">Volver al Inicio</span>
 </router-link>
 </div>

 <div class="w-full max-w-xl animate-in fade-in zoom-in duration-1000 relative z-10">
 
 <!-- Main Container -->
 <div class="bg-[#151a22]/90 dark:bg-[#090d0a]/90 backdrop-blur-3xl border border-white dark:border-[#21262d] p-8 md:p-16 rounded-[4.5rem] -[0_60px_120px_-30px_rgba(0,0,0,0.08)] relative">
 
 <!-- Header Unificado -->
 
 <!-- Honeypot (Bot Trap) -->
 <div style="opacity: 0; position: absolute; top: 0; left: 0; height: 0; width: 0; z-index: -1; overflow: hidden;">
 <input v-model="honeypot" type="text" tabindex="-1" autocomplete="off">
 </div>

 <transition name="fade-slide" mode="out-in">
 <div :key="mode" class="text-center mb-12">
 <div class="space-y-4">
 <div class="w-20 h-20 bg-[#151a22] dark:bg-[#151a22] dark:text-[#ffffff] rounded-[2rem] mx-auto flex items-center justify-center -black/20 mb-6">
 <svg v-if="selectedRole === 'admin'" class="w-8 h-8 text-[#8dd6ff]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
 <svg v-else class="w-8 h-8 text-[#8dd6ff]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
 </div>
 <h1 class="split-title text-2xl md:text-3xl lg:text-4xl font-black text-slate-900 dark:text-white tracking-tight uppercase italic leading-tight mb-6 animate-fade-in">
 Acceso <span class="text-[#8dd6ff]">N3XT</span>
 </h1>
 
 <!-- Role Selector Tabs -->
 <div class="flex bg-[#151a22] dark:bg-[#151a22]/5 p-1.5 rounded-[1.5rem] max-w-[320px] mx-auto mt-8 border border-[#21262d] dark:border-[#21262d]">
 <button 
 :class="['flex-1 py-3 px-6 rounded-[24px] text-[9px] font-black uppercase tracking-widest transition-all', selectedRole === 'customer' ? 'bg-[#151a22] dark:bg-[#283041] text-[#8dd6ff] ' : 'text-[#c3c4c5] hover:text-[#a4aea6] dark:hover:text-white']"
 @click="selectedRole = 'customer'"
 >
 Cliente
 </button>
 <button 
 :class="['flex-1 py-3 px-6 rounded-[24px] text-[9px] font-black uppercase tracking-widest transition-all', selectedRole === 'admin' ? 'bg-[#151a22] dark:bg-[#283041] text-[#8dd6ff] ' : 'text-[#c3c4c5] hover:text-[#a4aea6] dark:hover:text-white']"
 @click="selectedRole = 'admin'"
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
 <form v-if="mode === 'password'" class="space-y-6" @submit.prevent="login">
 <div v-if="error" class="bg-rose-50 dark:bg-rose-500/10 border border-rose-100 dark:border-rose-500/20 text-rose-500 dark:text-rose-400 p-5 rounded-[24px] text-[10px] font-black uppercase tracking-widest text-center">{{ error }}</div>
 <div class="space-y-3">
 <label for="login-email" class="text-[10px] font-black text-[#c3c4c5] dark:text-[#a4aea6] uppercase tracking-widest ml-5">Correo Electrónico</label>
 <input id="login-email" v-model="email" type="email" required placeholder="tu@ejemplo.com" class="w-full bg-[#151a22]/50 dark:bg-[#151a22]/50 border border-[#21262d] dark:border-[#21262d] rounded-[24px] p-6 text-[#ffffff] dark:text-white font-bold text-base outline-none focus:ring-4 focus:ring-primary/5 focus:border-primary/20 transition-all">
 </div>
 <div class="space-y-3">
 <label for="login-password" class="text-[10px] font-black text-[#c3c4c5] dark:text-[#a4aea6] uppercase tracking-widest ml-5">Contraseña</label>
 <div class="relative group">
 <input id="login-password" v-model="password" :type="showPassword ? 'text' : 'password'" required placeholder="••••••••" class="w-full bg-[#151a22]/50 dark:bg-[#151a22]/50 border border-[#21262d] dark:border-[#21262d] rounded-[24px] p-6 text-[#ffffff] dark:text-white font-bold text-base outline-none focus:ring-4 focus:ring-primary/5 focus:border-primary/20 transition-all pr-16">
 <button type="button" aria-label="Alternar visibilidad de contraseña" class="absolute right-6 top-1/2 -translate-y-1/2 text-[#c3c4c5] hover:text-[#8dd6ff] transition-colors p-2" @click="showPassword = !showPassword">
 <svg v-if="!showPassword" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
 <svg v-else class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7 1.274-4.057-5.064-7-9.542-7-4.477 0-8.268-2.943-9.542-7zM15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3l18 18"/></svg>
 </button>
 </div>
 </div>
 <button type="submit" :disabled="loading" class="split-btn w-full bg-[#151a22] dark:bg-[#151a22] text-white dark:text-[#ffffff] font-black py-6 rounded-[24px] hover:bg-[#08872b] dark:hover:bg-[#08872b] dark:hover:text-white transition-all active:scale-95 text-[10px] uppercase tracking-[0.2em] mt-4">
 {{ loading ? 'AUTENTICANDO...' : 'INICIAR SESIÓN' }}
 </button>

 <div v-if="selectedRole === 'customer'" class="relative flex py-5 items-center">
    <div class="flex-grow border-t border-[#21262d]"></div>
    <span class="flex-shrink-0 mx-4 text-[#c3c4c5] text-[10px] font-black uppercase tracking-widest">O</span>
    <div class="flex-grow border-t border-[#21262d]"></div>
 </div>

 <button v-if="selectedRole === 'customer'" type="button" @click="loginWithGoogle" :disabled="loading" class="w-full flex items-center justify-center gap-3 bg-white text-slate-900 font-black py-5 rounded-[24px] hover:bg-slate-100 transition-all active:scale-95 text-[10px] uppercase tracking-[0.2em] border border-slate-200">
    <svg class="w-5 h-5" viewBox="0 0 24 24">
        <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
        <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
        <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
        <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
    </svg>
    CONTINUAR CON GOOGLE
 </button>

 <p v-if="selectedRole === 'customer'" class="text-center text-[10px] font-black text-[#c3c4c5] uppercase tracking-[0.2em] mt-6">
 ¿ERES NUEVO? <button type="button" class="text-[#8dd6ff] hover:underline ml-2" @click="mode = 'register'">REGÍSTRATE AQUÍ</button>
 </p>
 </form>



 <!-- REGISTER FORM -->
 <form v-else-if="mode === 'register'" class="space-y-6 animate-in slide-in-from-right-10 duration-500" @submit.prevent="register">
 <div v-if="error" class="bg-rose-50 dark:bg-rose-500/10 border border-rose-100 dark:border-rose-500/20 text-rose-500 dark:text-rose-400 p-5 rounded-[24px] text-[10px] font-black uppercase tracking-widest text-center">{{ error }}</div>
 <div class="space-y-3">
 <label for="register-name" class="text-[10px] font-black text-[#c3c4c5] dark:text-[#a4aea6] uppercase tracking-widest ml-5">Nombre Completo</label>
 <input id="register-name" v-model="name" type="text" required placeholder="Juan Pérez" class="w-full bg-[#151a22]/50 dark:bg-[#151a22]/50 border border-[#21262d] dark:border-[#21262d] rounded-[24px] p-6 text-[#ffffff] dark:text-white font-bold text-base outline-none focus:ring-4 focus:ring-primary/5 focus:border-primary/20 transition-all">
 </div>
 <div class="space-y-3">
 <label for="register-email" class="text-[10px] font-black text-[#c3c4c5] dark:text-[#a4aea6] uppercase tracking-widest ml-5">Email Principal</label>
 <input id="register-email" v-model="email" type="email" required placeholder="tu@correo.com" class="w-full bg-[#151a22]/50 dark:bg-[#151a22]/50 border border-[#21262d] dark:border-[#21262d] rounded-[24px] p-6 text-[#ffffff] dark:text-white font-bold text-base outline-none focus:ring-4 focus:ring-primary/5 focus:border-primary/20 transition-all">
 </div>
 <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
 <div class="space-y-3">
 <label for="register-password" class="text-[10px] font-black text-[#c3c4c5] dark:text-[#a4aea6] uppercase tracking-widest ml-5">Contraseña</label>
 <input id="register-password" v-model="password" type="password" required placeholder="••••••••" class="w-full bg-[#151a22]/50 dark:bg-[#151a22]/50 border border-[#21262d] dark:border-[#21262d] rounded-[24px] p-6 text-[#ffffff] dark:text-white font-bold text-base outline-none focus:ring-4 focus:ring-primary/5 focus:border-primary/20 transition-all">
 </div>
 <div class="space-y-3">
 <label for="register-password-confirm" class="text-[10px] font-black text-[#c3c4c5] dark:text-[#a4aea6] uppercase tracking-widest ml-5">Confirmar</label>
 <input id="register-password-confirm" v-model="password_confirmation" type="password" required placeholder="••••••••" class="w-full bg-[#151a22]/50 dark:bg-[#151a22]/50 border border-[#21262d] dark:border-[#21262d] rounded-[24px] p-6 text-[#ffffff] dark:text-white font-bold text-base outline-none focus:ring-4 focus:ring-primary/5 focus:border-primary/20 transition-all">
 </div>
 </div>

 <!-- Human Verification -->
 <div class="p-6 bg-[#151a22] dark:bg-black rounded-[2.5rem] text-white flex items-center justify-between gap-6 border border-gray-800 dark:border-[#21262d]">
 <label for="captcha-answer" class="flex-1">
 <p class="text-[8px] font-black text-[#8dd6ff] uppercase tracking-[0.4em] mb-1">Verificacion de seguridad</p>
 <p class="text-sm font-black italic uppercase">{{ challenge.text }}</p>
 <p v-if="isLocked" class="text-[8px] font-black text-rose-400 uppercase mt-1 tracking-widest">🔒 Bloqueado 30s</p>
 </label>
 <input id="captcha-answer" v-model="answer" type="number" required placeholder="?" :disabled="isLocked" class="w-20 bg-[#151a22]/10 dark:bg-[#151a22]/5 border border-white/20 dark:border-[#21262d] rounded-[24px] p-4 text-center text-white font-black outline-none focus:border-primary transition-all disabled:opacity-30">
 </div>

 <button type="submit" :disabled="loading" class="split-btn w-full bg-[#08872b] text-white font-black py-6 rounded-[24px] hover:bg-[#151a22] transition-all active:scale-95 text-[10px] uppercase tracking-[0.2em] mt-4">
 {{ loading ? 'CREANDO...' : 'CREAR MI CUENTA' }}
 </button>
 <p class="text-center text-[10px] font-black text-[#c3c4c5] uppercase tracking-[0.2em] mt-6">
 ¿YA TIENES CUENTA? <button type="button" class="text-[#8dd6ff] hover:underline ml-2" @click="mode = 'password'">INICIA SESIÓN</button>
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
 </main>
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
