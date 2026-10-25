<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { api } from '../services/api'
import logger from '../utils/logger'
import { DEFAULT_WHATSAPP_NUMBER, DEFAULT_PHONE_DISPLAY, DEFAULT_EMAIL, parseWhatsAppNumber, whatsappLink } from '../utils/contact'

const navLinks = [
  { n: 'Inicio', p: '/' },
  { n: 'Galería', p: '/galeria' },
  { n: 'Catálogo', p: '/catalog' },
  { n: 'Cotizador', p: '/quote' },
  { n: 'Rastrear', p: '/track' },
  { n: 'Taller', p: '/admin' },
  { n: 'Contacto', p: '/project/init' }
]

const tiktokUrl = ref('')
const whatsappNumber = ref(DEFAULT_WHATSAPP_NUMBER)
const email = ref(DEFAULT_EMAIL)
const phone = ref(DEFAULT_PHONE_DISPLAY)
const workshopStatus = ref('Abierto de lunes a sábado')
const year = new Date().getFullYear()

const whatsappHref = computed(() => whatsappLink(whatsappNumber.value))

const isRealUrl = (url: unknown): url is string => typeof url === 'string' && url.trim() !== '' && url.trim() !== '#'

onMounted(async () => {
  try {
    const data = await api.get('/settings')
    const social = data?.web?.social
    if (isRealUrl(social?.tiktok)) tiktokUrl.value = social.tiktok
    const wa = parseWhatsAppNumber(social?.whatsapp)
    if (wa) whatsappNumber.value = wa
    if (data?.web?.workshop_status) workshopStatus.value = data.web.workshop_status
    if (data?.company?.email) email.value = data.company.email
    if (data?.company?.phone) phone.value = data.company.phone
  } catch (err) {
    logger.error('Error fetching footer settings:', err)
  }
})
</script>

<template>
  <footer class="relative mt-24 bg-surface">
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-16 pb-10">
      <div class="grid gap-12 lg:grid-cols-[1.4fr_1fr_1fr_1fr]">
        <div class="max-w-sm">
          <p class="font-display text-3xl font-extrabold tracking-tight text-ink [font-stretch:118%]">N3XT<span class="text-brand">3D</span></p>
          <p class="mt-4 text-[0.9375rem] leading-relaxed text-muted">
            Estudio de impresión 3D en Colombia. Figuras coleccionables, prototipos y piezas a medida en resina y filamento.
          </p>
          <router-link to="/project/init" class="btn-primary mt-6">Iniciar un proyecto</router-link>
        </div>

        <nav aria-label="Enlaces del sitio">
          <h2 class="text-sm font-semibold text-ink [font-stretch:100%] tracking-normal">Sitio</h2>
          <ul class="mt-4 space-y-3">
            <li v-for="link in navLinks" :key="link.n">
              <router-link :to="link.p" class="text-[0.9375rem] text-muted hover:text-ink transition-colors">{{ link.n }}</router-link>
            </li>
          </ul>
        </nav>

        <div>
          <h2 class="text-sm font-semibold text-ink [font-stretch:100%] tracking-normal">Contacto</h2>
          <ul class="mt-4 space-y-3 text-[0.9375rem]">
            <li>
              <a :href="whatsappHref" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2 text-muted hover:text-ink transition-colors">
                <svg class="w-4 h-4" aria-hidden="true" fill="currentColor" viewBox="0 0 362 362"><path d="M307.546 52.566C273.709 18.684 228.706.017 180.756 0 81.951 0 1.538 80.404 1.504 179.235c-.017 31.594 8.242 62.432 23.928 89.609L0 361.736l95.024-24.925c26.179 14.285 55.659 21.805 85.655 21.814h.077c98.788 0 179.21-80.413 179.244-179.244.017-47.898-18.608-92.926-52.454-126.807v-.008Zm-126.79 275.788h-.06c-26.73-.008-52.952-7.194-75.831-20.765l-5.44-3.231-56.391 14.791 15.05-54.981-3.542-5.638c-14.912-23.721-22.793-51.139-22.776-79.286.035-82.14 66.867-148.973 149.051-148.973 39.793.017 77.198 15.53 105.328 43.695 28.131 28.157 43.61 65.596 43.593 105.398-.035 82.149-66.867 148.982-148.982 148.982v.008Zm81.719-111.577c-4.478-2.243-26.497-13.073-30.606-14.568-4.108-1.496-7.09-2.243-10.073 2.243-2.982 4.487-11.568 14.577-14.181 17.559-2.613 2.991-5.226 3.361-9.704 1.117-4.477-2.243-18.908-6.97-36.02-22.226-13.313-11.878-22.304-26.54-24.916-31.027-2.613-4.486-.275-6.91 1.959-9.136 2.011-2.011 4.478-5.234 6.721-7.847 2.244-2.613 2.983-4.486 4.478-7.469 1.496-2.991.748-5.603-.369-7.847-1.118-2.243-10.073-24.289-13.812-33.253-3.636-8.732-7.331-7.546-10.073-7.692-2.613-.13-5.595-.155-8.586-.155-2.991 0-7.839 1.118-11.947 5.604-4.108 4.486-15.677 15.324-15.677 37.361s16.047 43.344 18.29 46.335c2.243 2.991 31.585 48.225 76.51 67.632 10.684 4.615 19.029 7.374 25.535 9.437 10.727 3.412 20.49 2.931 28.208 1.779 8.604-1.289 26.498-10.838 30.228-21.298 3.73-10.46 3.73-19.433 2.613-21.298-1.117-1.865-4.108-2.991-8.586-5.234l.008-.017Z"/></svg>
                WhatsApp
              </a>
            </li>
            <li v-if="tiktokUrl">
              <a :href="tiktokUrl" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2 text-muted hover:text-ink transition-colors">
                <svg class="w-4 h-4" aria-hidden="true" fill="currentColor" viewBox="0 0 384 382"><path d="M137.17 156.98v-15.56c-5.34-.73-10.76-1.18-16.29-1.18C54.23 140.24 0 194.47 0 261.13c0 40.9 20.43 77.09 51.61 98.97-20.12-21.6-32.46-50.53-32.46-82.31 0-65.7 52.69-119.28 118.03-120.81Z"/><path d="M140.02 333c29.74 0 54-23.66 55.1-53.13l.11-263.2h48.08c-1-5.41-1.55-10.97-1.55-16.67h-65.67l-.11 263.2c-1.1 29.47-25.36 53.13-55.1 53.13-9.24 0-17.95-2.31-25.61-6.34C105.3 323.9 121.6 333 140.02 333ZM333.13 106V91.37c-18.34 0-35.43-5.45-49.76-14.8 12.76 14.65 30.09 25.22 49.76 29.43Z"/><path d="M283.38 76.57c-13.98-16.05-22.47-37-22.47-59.91h-17.59c4.63 25.02 19.48 46.49 40.06 59.91ZM120.88 205.92c-30.44 0-55.21 24.77-55.21 55.21 0 21.2 12.03 39.62 29.6 48.86-6.55-9.08-10.45-20.18-10.45-32.2 0-30.44 24.77-55.21 55.21-55.21 5.68 0 11.13.94 16.29 2.55v-67.05c-5.34-.73-10.76-1.18-16.29-1.18-.96 0-1.9.05-2.85.07v51.49c-5.16-1.61-10.61-2.55-16.29-2.55Z"/><path d="M333.13 106v51.04c-34.05 0-65.61-10.89-91.37-29.38v133.47c0 66.66-54.23 120.88-120.88 120.88-25.76 0-49.64-8.12-69.28-21.91 22.08 23.71 53.54 38.57 88.42 38.57 66.66 0 120.88-54.23 120.88-120.88V144.33c25.76 18.49 57.32 29.38 91.37 29.38v-65.68c-6.57 0-12.97-.71-19.14-2.03Z"/></svg>
                TikTok
              </a>
            </li>
            <li><a :href="'mailto:' + email" class="text-muted hover:text-ink transition-colors break-all">{{ email }}</a></li>
            <li><a :href="'tel:' + phone.replace(/[^+\d]/g, '')" class="text-muted hover:text-ink transition-colors">{{ phone }}</a></li>
          </ul>
        </div>

        <div>
          <h2 class="text-sm font-semibold text-ink [font-stretch:100%] tracking-normal">Taller</h2>
          <p class="mt-4 text-[0.9375rem] text-muted">{{ workshopStatus }}</p>
          <router-link to="/track" class="mt-3 inline-block text-[0.9375rem] font-medium text-brand hover:underline underline-offset-4">Rastrear un pedido</router-link>
        </div>
      </div>

      <div class="mt-14 pt-6 flex flex-col sm:flex-row gap-4 justify-between text-sm text-muted">
        <p>&copy; {{ year }} N3XT 3D</p>
        <div class="flex gap-6">
          <router-link to="/privacy" class="hover:text-ink transition-colors">Privacidad</router-link>
          <router-link to="/terms" class="hover:text-ink transition-colors">Términos</router-link>
        </div>
      </div>
    </div>
  </footer>
</template>
