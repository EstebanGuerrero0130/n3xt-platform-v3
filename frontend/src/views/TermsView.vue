<script setup lang="ts">
import { onMounted } from 'vue'
import AppNavbar from '../components/AppNavbar.vue'
import AppFooter from '../components/AppFooter.vue'
import { usePageMeta } from '../composables/usePageMeta'

usePageMeta({
 title: 'Términos y Condiciones | N3XT 3D',
 description: 'Términos y condiciones de uso del servicio de manufactura digital N3XT 3D.',
})

onMounted(() => {
 window.scrollTo(0, 0)
})
</script>

<template>
 <div class="min-h-screen bg-[#f8fafc] dark:bg-[#0a0f14] text-[#ffffff] dark:text-white transition-colors duration-500 overflow-x-hidden">
 <AppNavbar active-tab="" subtext="Términos" />

 <main class="max-w-4xl mx-auto px-6 py-20 md:py-32">
 <!-- Header -->
 <div class="mb-16">
 <div class="inline-flex items-center gap-3 px-4 py-2 bg-[#08872b]/10 rounded-[60px] border border-primary/20 mb-6">
 <span class="w-2 h-2 bg-[#08872b] rounded-[60px] animate-pulse"></span>
 <span class="text-label text-[#8dd6ff]">Legal</span>
 </div>
 <h1 class="text-6xl md:text-8xl lg:text-9xl font-black tracking-normal uppercase leading-[0.85] italic mb-6 text-slate-900 dark:text-white animate-fade-in">
 Términos y <span class="text-[#8dd6ff]">Condiciones</span>
 </h1>
 <p class="text-[#a4aea6] dark:text-[#c3c4c5] font-bold text-sm tracking-widest">
 Última actualización: Junio 2026
 </p>
 </div>

 <!-- Content -->
 <div class="space-y-10 text-[#a4aea6] dark:text-gray-300 font-medium leading-relaxed text-sm md:text-base">
 <section class="space-y-4">
 <h2 class="text-xl md:text-2xl font-black text-[#ffffff] dark:text-white tracking-tight">1. Aceptación de los Términos</h2>
 <p>Al utilizar la plataforma N3XT 3D y sus servicios de manufactura digital, aceptas estar sujeto a estos términos y condiciones. Si no estás de acuerdo con alguna parte, no debes usar nuestros servicios.</p>
 </section>

 <section class="space-y-4">
 <h2 class="text-xl md:text-2xl font-black text-[#ffffff] dark:text-white tracking-tight">2. Descripción del Servicio</h2>
 <p>N3XT 3D ofrece servicios de fabricación aditiva (impresión 3D) incluyendo:</p>
 <ul class="list-disc pl-6 space-y-2">
 <li>Cotización automatizada mediante motor OrcaEngine</li>
 <li>Impresión 3D en tecnologías FDM y SLA</li>
 <li>Post-procesado y acabado de piezas</li>
 <li>Venta de catálogo de piezas y coleccionables</li>
 <li>Asesoría técnica en diseño para manufactura aditiva</li>
 </ul>
 </section>

 <section class="space-y-4">
 <h2 class="text-xl md:text-2xl font-black text-[#ffffff] dark:text-white tracking-tight">3. Cotizaciones y Órdenes</h2>
 <ul class="list-disc pl-6 space-y-2">
 <li>Las cotizaciones generadas por OrcaEngine son estimaciones basadas en los parámetros ingresados</li>
 <li>El precio final puede variar según el análisis técnico del archivo 3D</li>
 <li>Una vez confirmada la orden, se enviará una factura electrónica válida en Colombia</li>
 <li>Los tiempos de producción son estimados y pueden variar según la complejidad</li>
 </ul>
 </section>

 <section class="space-y-4">
 <h2 class="text-xl md:text-2xl font-black text-[#ffffff] dark:text-white tracking-tight">4. Propiedad Intelectual</h2>
 <p>Al subir un archivo 3D para cotización o producción:</p>
 <ul class="list-disc pl-6 space-y-2">
 <li>Declaras tener los derechos legales para reproducir el diseño</li>
 <li>N3XT 3D no reclama propiedad sobre tus diseños originales</li>
 <li>No fabricaremos ni distribuiremos copias de tus diseños sin autorización</li>
 <li>Nos reservamos el derecho de rechazar órdenes que infrinjan derechos de terceros</li>
 </ul>
 </section>

 <section class="space-y-4">
 <h2 class="text-xl md:text-2xl font-black text-[#ffffff] dark:text-white tracking-tight">5. Garantía y Calidad</h2>
 <ul class="list-disc pl-6 space-y-2">
 <li>Ofrecemos garantía de 6 meses en piezas de catálogo contra defectos de fabricación</li>
 <li>Las piezas personalizadas se fabrican según las especificaciones del archivo 3D proporcionado</li>
 <li>No nos hacemos responsables por problemas derivados del diseño del cliente</li>
 <li>Las variaciones dimensionales de ±0.5mm se consideran dentro del estándar industrial</li>
 </ul>
 </section>

 <section class="space-y-4">
 <h2 class="text-xl md:text-2xl font-black text-[#ffffff] dark:text-white tracking-tight">6. Envíos</h2>
 <ul class="list-disc pl-6 space-y-2">
 <li>Realizamos envíos a todo Colombia</li>
 <li>El costo de envío se calcula según la ubicación y dimensiones del paquete</li>
 <li>Los tiempos de entrega son estimados proporcionados por la transportadora</li>
 <li>No nos hacemos responsables por demoras causadas por la empresa de mensajería</li>
 </ul>
 </section>

 <section class="space-y-4">
 <h2 class="text-xl md:text-2xl font-black text-[#ffffff] dark:text-white tracking-tight">7. Limitación de Responsabilidad</h2>
 <p>N3XT 3D no será responsable por daños indirectos, incidentales o consecuentes derivados del uso o la imposibilidad de usar nuestros servicios, incluyendo pero no limitado a pérdida de datos, lucro cesante, o interrupción del negocio.</p>
 </section>

 <section class="space-y-4">
 <h2 class="text-xl md:text-2xl font-black text-[#ffffff] dark:text-white tracking-tight">8. Contacto</h2>
 <div class="bg-[#151a22] dark:bg-[#151a22]/5 rounded-[24px] p-6 border border-[#21262d] dark:border-[#21262d] space-y-3 mt-4">
 <p class="text-sm"><strong class="text-[#ffffff] dark:text-white">Correo:</strong> servicion3xt@gmail.com</p>
 <p class="text-sm"><strong class="text-[#ffffff] dark:text-white">WhatsApp:</strong> +57 311 879 6416</p>
 </div>
 </section>
 </div>
 </main>

 <AppFooter />
 </div>
</template>
