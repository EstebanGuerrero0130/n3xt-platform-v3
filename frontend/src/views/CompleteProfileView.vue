<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { supabase } from '../services/supabase'
import { api } from '../services/api'
import { usePageMeta } from '../composables/usePageMeta'

usePageMeta({
  title: 'Completar Perfil | N3XT',
  description: 'Completa tu perfil para acceder al sistema N3XT.'
})

const router = useRouter()
const loading = ref(true)
const checking = ref(true)
const error = ref('')

const form = ref({
  email: '',
  name: '',
  phone: '',
  company: '',
  customer_id_document: '',
  supabase_id: ''
})

onMounted(async () => {
  try {
    const { data, error: sessionError } = await supabase.auth.getSession()
    if (sessionError || !data.session) {
      throw new Error('No se pudo autenticar con Google.')
    }

    form.value.email = data.session.user.email || ''
    form.value.name = data.session.user.user_metadata?.full_name || ''
    form.value.supabase_id = data.session.user.id

    // Verificar en el backend
    const check = await api.get('/auth/status')
    // Si llegamos aquí y es exitoso, significa que ya existe y el middleware lo logueó
    if (check.authenticated) {
       router.push(check.role === 'admin' ? '/admin' : '/customer/dashboard')
       return
    }
    
    // Si no es exitoso o requiere registro, caerá en el catch (401/403)
  } catch (err: any) {
    if (err.message && err.message.includes('Completa tu perfil')) {
        // Necesitamos completar el perfil
        checking.value = false
    } else if (err.message && err.message.includes('inicia sesión primero')) {
        // En nuestro diseño, si da 401 el middleware puede decir eso.
        // Pero el middleware devuelve 403 con mensaje de completar perfil si el JWT es válido pero el usuario no existe.
        checking.value = false
    } else {
        error.value = err.message || 'Error de autenticación'
        setTimeout(() => router.push('/login'), 3000)
    }
  } finally {
    loading.value = false
  }
})

const completeProfile = async () => {
  error.value = ''
  loading.value = true
  try {
    const data = await api.post('/auth/complete-profile', form.value)
    router.push('/customer/dashboard')
  } catch (err: any) {
    error.value = err.message || 'Error al completar el perfil'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <main class="min-h-screen bg-[#f8fafc] dark:bg-black flex flex-col items-center justify-center p-6 md:p-12 overflow-hidden relative">
    <div class="w-full max-w-xl animate-in fade-in zoom-in duration-500 relative z-10">
      <div class="bg-[#151a22]/90 dark:bg-[#090d0a]/90 backdrop-blur-3xl border border-white dark:border-[#21262d] p-8 md:p-12 rounded-[3rem] shadow-2xl relative">
        
        <div v-if="checking" class="text-center py-10">
            <svg class="animate-spin h-10 w-10 text-[#8dd6ff] mx-auto mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <p class="text-white text-sm font-black uppercase tracking-widest">Verificando sesión...</p>
        </div>

        <div v-else-if="error && !checking" class="text-center">
            <div class="text-rose-400 font-black mb-4">{{ error }}</div>
            <p class="text-gray-400 text-xs uppercase tracking-widest">Redirigiendo al login...</p>
        </div>

        <form v-else class="space-y-6" @submit.prevent="completeProfile">
          <div class="text-center mb-8">
            <h1 class="text-2xl font-black text-white uppercase italic tracking-tight">Completar Perfil</h1>
            <p class="text-[10px] text-gray-400 font-black uppercase tracking-widest mt-2">Sólo un paso más para acceder a N3XT</p>
          </div>

          <div class="space-y-3">
            <label class="text-[10px] font-black text-[#c3c4c5] uppercase tracking-widest ml-5">Nombre / Razón Social</label>
            <input v-model="form.name" type="text" required class="w-full bg-[#151a22]/50 border border-[#21262d] rounded-[24px] p-4 text-white outline-none focus:border-[#8dd6ff] transition-all">
          </div>

          <div class="space-y-3">
            <label class="text-[10px] font-black text-[#c3c4c5] uppercase tracking-widest ml-5">Teléfono / WhatsApp</label>
            <input v-model="form.phone" type="text" required class="w-full bg-[#151a22]/50 border border-[#21262d] rounded-[24px] p-4 text-white outline-none focus:border-[#8dd6ff] transition-all">
          </div>

          <div class="space-y-3">
            <label class="text-[10px] font-black text-[#c3c4c5] uppercase tracking-widest ml-5">Empresa / Proyecto</label>
            <input v-model="form.company" type="text" required class="w-full bg-[#151a22]/50 border border-[#21262d] rounded-[24px] p-4 text-white outline-none focus:border-[#8dd6ff] transition-all">
          </div>

          <div class="space-y-3">
            <label class="text-[10px] font-black text-[#c3c4c5] uppercase tracking-widest ml-5">NIT / Documento</label>
            <input v-model="form.customer_id_document" type="text" required class="w-full bg-[#151a22]/50 border border-[#21262d] rounded-[24px] p-4 text-white outline-none focus:border-[#8dd6ff] transition-all">
          </div>

          <button type="submit" :disabled="loading" class="w-full bg-[#08872b] text-white font-black py-5 rounded-[24px] hover:bg-emerald-600 transition-all active:scale-95 text-[10px] uppercase tracking-[0.2em] mt-4">
            {{ loading ? 'GUARDANDO...' : 'FINALIZAR REGISTRO' }}
          </button>
        </form>
      </div>
    </div>
  </main>
</template>
