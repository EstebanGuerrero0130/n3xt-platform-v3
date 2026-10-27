<script setup lang="ts">
import { ref, onMounted, onUnmounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '../services/api'
import store from '../utils/cache'
import { useSplitTitle } from '../composables/useSplitTitle'
import { useCaptcha } from '../composables/useCaptcha'
import { usePageMeta } from '../composables/usePageMeta'

useSplitTitle()

usePageMeta({
  title: 'Iniciar sesión | N3XT 3D',
  description: 'Accede a tu cuenta N3XT 3D para ver tus pedidos, o al panel del taller.',
})

// ─── SEO via @unhead/vue (usePageMeta at setup) ───

// ?registro=1 abre directo el formulario de registro (enlaces de catálogo y cotizador)
type LoginMode = 'password' | 'register' | 'forgot' | 'reset'
const mode = ref<LoginMode>(new URLSearchParams(window.location.search).has('registro') ? 'register' : 'password')
const TITLES: Record<LoginMode, [string, string, string]> = {
  password: ['Acceso', 'N3XT', 'Ingresa con tu correo y contraseña.'],
  register: ['Crea tu', 'cuenta', 'Sigue tus pedidos, recibe avisos por correo y un cupón de bienvenida para tu primer pedido.'],
  forgot: ['Recupera tu', 'acceso', 'Te enviamos un código a tu correo para crear una contraseña nueva.'],
  reset: ['Nueva', 'contraseña', 'Escribe el código que te llegó y tu contraseña nueva.'],
}

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

onMounted(async () => {
  // Redirigir si ya hay sesión activa
  const authStatus = await api.checkAuth()
  
  if (authStatus.authenticated) {
    // Cada rol a su panel (el cliente no entra a /admin y volvería aquí)
    router.push(authStatus.role === 'customer' ? '/customer/dashboard' : '/admin')
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
    
    // El role de la API es la única fuente de verdad
    const role = data?.role === 'admin' ? 'admin' : 'customer'
    if (role === 'admin') {
        window.location.href = '/admin'
    } else {
        window.location.href = '/customer/dashboard'
    }
  } catch (err) {
    error.value = err instanceof Error ? err.message : 'No pudimos procesar la solicitud. Inténtalo de nuevo.'
  } finally {
    loading.value = false
  }
}

// --- Recuperar contraseña: pedir código → código + contraseña nueva ---
const resetCode = ref('')
const notice = ref('')

const forgotPassword = async () => {
  error.value = ''
  notice.value = ''
  if (!email.value.trim()) {
    error.value = 'Escribe el correo de tu cuenta.'
    return
  }
  loading.value = true
  try {
    await api.post('/auth/password/forgot', { email: email.value.trim() })
    notice.value = 'Si el correo tiene una cuenta, te llegó un código de 6 números. Revisa también la carpeta de spam.'
    password.value = ''
    password_confirmation.value = ''
    mode.value = 'reset'
  } catch (err) {
    error.value = err instanceof Error ? err.message : 'No pudimos enviar el código. Inténtalo en un minuto.'
  } finally {
    loading.value = false
  }
}

const resetPassword = async () => {
  error.value = ''
  if (!/^\d{6}$/.test(resetCode.value.trim())) {
    error.value = 'El código tiene 6 números.'
    return
  }
  loading.value = true
  try {
    await api.post('/auth/password/reset', {
      email: email.value.trim(),
      code: resetCode.value.trim(),
      password: password.value,
      password_confirmation: password_confirmation.value,
    })
    router.push('/customer/dashboard')
  } catch (err) {
    error.value = err instanceof Error ? err.message : 'No pudimos cambiar la contraseña.'
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
  } catch (err) {
    error.value = err instanceof Error ? err.message : 'No pudimos procesar la solicitud. Inténtalo de nuevo.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="min-h-screen grid lg:grid-cols-2 bg-canvas text-ink">
    <!-- Panel visual (solo escritorio) -->
    <div class="relative hidden lg:block overflow-hidden" aria-hidden="true">
      <img src="/assets/hero/n3xt_hero_voronoi_1200.webp" alt="" class="absolute inset-0 w-full h-full object-cover" decoding="async">
      <div class="absolute inset-0 photo-scrim"></div>
      <div class="absolute left-10 right-10 bottom-12">
        <p class="section-title" style="color: #fff">N3XT <span class="section-title-accent">3D</span></p>
        <p class="mt-4 text-lg text-white/80 max-w-[36ch]">Sigue tus pedidos, revisa cotizaciones y gestiona el taller desde un solo lugar.</p>
      </div>
    </div>

    <main id="main" class="flex flex-col px-4 sm:px-8 py-6 md:py-8">
      <router-link to="/" class="self-start inline-flex items-center gap-2 h-11 px-4 -ml-4 rounded-full text-sm font-medium text-muted hover:text-ink transition-colors">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        Volver al inicio
      </router-link>

      <div class="flex-1 flex items-center justify-center py-10">
        <div class="w-full max-w-md">
          <!-- Honeypot (trampa para bots) -->
          <div class="absolute w-0 h-0 overflow-hidden opacity-0 -z-10" aria-hidden="true">
            <input v-model="honeypot" type="text" tabindex="-1" autocomplete="off">
          </div>

          <h1 class="section-title">{{ TITLES[mode][0] }} <span class="section-title-accent">{{ TITLES[mode][1] }}</span></h1>
          <p class="mt-3 text-muted">{{ TITLES[mode][2] }}</p>
          <p v-if="notice && mode === 'reset'" role="status" class="mt-4 p-3 rounded-[var(--radius-control)] bg-primary/10 text-sm">{{ notice }}</p>

          <div v-if="error" role="alert" class="mt-6 p-4 rounded-[var(--radius-control)] border border-danger/30 bg-danger/5 text-sm text-danger font-medium">{{ error }}</div>

          <transition name="fade-slide" mode="out-in">
            <!-- Iniciar sesión -->
            <form v-if="mode === 'password'" key="login" class="mt-6 space-y-5" @submit.prevent="login">
              <div>
                <label for="login-email" class="block text-sm font-medium mb-2">Correo electrónico</label>
                <input id="login-email" v-model="email" type="email" required autocomplete="email" placeholder="tu@correo.com" class="field">
              </div>
              <div>
                <label for="login-password" class="block text-sm font-medium mb-2">Contraseña</label>
                <div class="relative">
                  <input id="login-password" v-model="password" :type="showPassword ? 'text' : 'password'" required autocomplete="current-password" placeholder="••••••••" class="field pr-12">
                  <button type="button" class="absolute right-1 top-1/2 -translate-y-1/2 w-10 h-10 inline-flex items-center justify-center rounded-full text-muted hover:text-ink transition-colors" :aria-label="showPassword ? 'Ocultar contraseña' : 'Mostrar contraseña'" :aria-pressed="showPassword" @click="showPassword = !showPassword">
                <svg v-if="!showPassword" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                <svg v-else class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3l18 18M10.6 10.6a2 2 0 002.8 2.8M9.9 5.1A9.8 9.8 0 0112 5c4.5 0 8.3 2.9 9.5 7a10 10 0 01-2.3 3.9M6.6 6.6A10 10 0 002.5 12c1.3 4.1 5 7 9.5 7a9.8 9.8 0 005.4-1.6"/></svg>
                  </button>
                </div>
              </div>
              <button type="submit" :disabled="loading" class="btn-primary btn-lg w-full">
                <span v-if="loading" class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin" aria-hidden="true"></span>
                {{ loading ? 'Ingresando…' : 'Iniciar sesión' }}
              </button>
              <p class="text-sm text-muted text-center">
                ¿Eres nuevo? <button type="button" class="font-semibold text-brand hover:underline" @click="mode = 'register'; error = ''">Regístrate</button>
              </p>
              <p class="text-sm text-center">
                <button type="button" class="font-semibold text-muted hover:text-ink hover:underline" @click="mode = 'forgot'; error = ''">¿Olvidaste tu contraseña?</button>
              </p>
            </form>

            <!-- Pedir código -->
            <form v-else-if="mode === 'forgot'" key="forgot" class="mt-6 space-y-5" novalidate @submit.prevent="forgotPassword">
              <div>
                <label for="forgot-email" class="block text-sm font-medium mb-2">Correo de tu cuenta</label>
                <input id="forgot-email" v-model="email" type="email" required autocomplete="email" placeholder="tu@correo.com" class="field">
              </div>
              <button type="submit" :disabled="loading" class="btn-primary btn-lg w-full">{{ loading ? 'Enviando…' : 'Enviar código' }}</button>
              <p class="text-sm text-muted text-center">
                <button type="button" class="font-semibold text-brand hover:underline" @click="mode = 'password'; error = ''">Volver a iniciar sesión</button>
              </p>
            </form>

            <!-- Código + contraseña nueva -->
            <form v-else-if="mode === 'reset'" key="reset" class="mt-6 space-y-5" novalidate @submit.prevent="resetPassword">
              <div>
                <label for="reset-code" class="block text-sm font-medium mb-2">Código del correo</label>
                <input id="reset-code" v-model="resetCode" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6" placeholder="000000" class="field text-center text-lg tracking-[0.4em] tabular-nums">
              </div>
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                  <label for="reset-password" class="block text-sm font-medium mb-2">Contraseña nueva</label>
                  <input id="reset-password" v-model="password" type="password" required minlength="8" autocomplete="new-password" class="field">
                </div>
                <div>
                  <label for="reset-password2" class="block text-sm font-medium mb-2">Repítela</label>
                  <input id="reset-password2" v-model="password_confirmation" type="password" required autocomplete="new-password" class="field">
                </div>
              </div>
              <button type="submit" :disabled="loading" class="btn-primary btn-lg w-full">{{ loading ? 'Guardando…' : 'Cambiar contraseña' }}</button>
              <p class="text-sm text-muted text-center">
                ¿No llegó? <button type="button" class="font-semibold text-brand hover:underline" :disabled="loading" @click="forgotPassword">Enviar otro código</button>
              </p>
            </form>

            <!-- Registro -->
            <form v-else-if="mode === 'register'" key="register" class="mt-8 space-y-5" @submit.prevent="register">
              <div>
                <label for="reg-name" class="block text-sm font-medium mb-2">Nombre completo</label>
                <input id="reg-name" v-model="name" type="text" required autocomplete="name" placeholder="Juan Pérez" class="field">
              </div>
              <div>
                <label for="reg-email" class="block text-sm font-medium mb-2">Correo electrónico</label>
                <input id="reg-email" v-model="email" type="email" required autocomplete="email" placeholder="tu@correo.com" class="field">
              </div>
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                  <label for="reg-password" class="block text-sm font-medium mb-2">Contraseña</label>
                  <input id="reg-password" v-model="password" type="password" required autocomplete="new-password" placeholder="••••••••" class="field">
                </div>
                <div>
                  <label for="reg-password2" class="block text-sm font-medium mb-2">Confirmar contraseña</label>
                  <input id="reg-password2" v-model="password_confirmation" type="password" required autocomplete="new-password" placeholder="••••••••" class="field">
                </div>
              </div>

              <!-- Verificación humana -->
              <div class="p-4 bg-surface rounded-[var(--radius-card)] flex items-center justify-between gap-4">
                <div class="min-w-0">
                  <label for="reg-captcha" class="block text-sm text-muted">Verificación: resuelve</label>
                  <p class="font-semibold">{{ challenge.text }}</p>
                  <p v-if="isLocked" class="mt-1 text-sm text-danger" role="status">Bloqueado 30 segundos por intentos fallidos.</p>
                </div>
                <input id="reg-captcha" v-model="answer" type="number" inputmode="numeric" required placeholder="?" :disabled="isLocked" class="field w-24 text-center">
              </div>

              <button type="submit" :disabled="loading" class="btn-primary btn-lg w-full">
                <span v-if="loading" class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin" aria-hidden="true"></span>
                {{ loading ? 'Creando cuenta…' : 'Crear mi cuenta' }}
              </button>
              <p class="text-sm text-muted text-center">
                ¿Ya tienes cuenta? <button type="button" class="font-semibold text-brand hover:underline" @click="mode = 'password'; error = ''">Inicia sesión</button>
              </p>
            </form>
          </transition>
        </div>
      </div>
    </main>
  </div>
</template>

<style scoped>
.fade-slide-enter-active,
.fade-slide-leave-active {
  transition: opacity 0.3s var(--ease-premium), transform 0.3s var(--ease-premium);
}
.fade-slide-enter-from { opacity: 0; transform: translateY(12px); }
.fade-slide-leave-to { opacity: 0; transform: translateY(-12px); }
@media (prefers-reduced-motion: reduce) {
  .fade-slide-enter-active, .fade-slide-leave-active { transition: none; }
}
</style>
