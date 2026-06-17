<script setup lang="ts">
import { onMounted, onUnmounted } from 'vue'
import AppNavbar from '../components/AppNavbar.vue'
import AppFooter from '../components/AppFooter.vue'
import { usePageMeta } from '../composables/usePageMeta'

usePageMeta({
 title: 'Política de Privacidad | N3XT 3D',
 description: 'Política de privacidad y protección de datos de N3XT 3D. Conoce cómo gestionamos tu información personal.',
})

onMounted(() => {
 window.scrollTo(0, 0)
})
</script>

<template>
 <div class="min-h-screen bg-[#f8fafc] dark:bg-[#0a0f14] text-[#ffffff] dark:text-white transition-colors duration-500 overflow-x-hidden">
 <AppNavbar active-tab="" subtext="Privacidad" />

 <main class="max-w-4xl mx-auto px-6 py-20 md:py-32">
 <!-- Header -->
 <div class="mb-16">
 <div class="inline-flex items-center gap-3 px-4 py-2 bg-[#08872b]/10 rounded-[60px] border border-primary/20 mb-6">
 <span class="w-2 h-2 bg-[#08872b] rounded-[60px] animate-pulse"></span>
 <span class="text-[10px] font-black text-[#8dd6ff] uppercase tracking-[0.4em]">Legal</span>
 </div>
 <h1 class="text-5xl md:text-7xl font-black tracking-tighter uppercase leading-[0.85] italic mb-6">
 Política de <span class="text-[#8dd6ff]">Privacidad</span>
 </h1>
 <p class="text-[#a4aea6] dark:text-[#c3c4c5] font-bold text-sm uppercase tracking-widest">
 Última actualización: Junio 2026
 </p>
 </div>

 <!-- Content -->
 <div class="space-y-10 text-[#a4aea6] dark:text-gray-300 font-medium leading-relaxed text-sm md:text-base">
 <section class="space-y-4">
 <h2 class="text-xl md:text-2xl font-black text-[#ffffff] dark:text-white uppercase tracking-tight">1. Introducción</h2>
 <p>En N3XT 3D nos comprometemos a proteger tu privacidad. Esta política describe cómo recopilamos, usamos y protegemos tu información personal cuando utilizas nuestros servicios de manufactura digital y cotización de piezas 3D.</p>
 </section>

 <section class="space-y-4">
 <h2 class="text-xl md:text-2xl font-black text-[#ffffff] dark:text-white uppercase tracking-tight">2. Información que Recopilamos</h2>
 <p>Podemos recopilar los siguientes datos personales:</p>
 <ul class="list-disc pl-6 space-y-2">
 <li><strong class="text-[#ffffff] dark:text-white">Datos de contacto:</strong> Nombre, correo electrónico, teléfono y dirección de envío.</li>
 <li><strong class="text-[#ffffff] dark:text-white">Datos de facturación:</strong> NIT, razón social y documentos de identificación cuando aplique.</li>
 <li><strong class="text-[#ffffff] dark:text-white">Datos técnicos:</strong> Archivos 3D (STL, OBJ, 3MF), parámetros de impresión y especificaciones de diseño.</li>
 <li><strong class="text-[#ffffff] dark:text-white">Datos de navegación:</strong> Información anónima sobre el uso del sitio web para mejorar nuestros servicios.</li>
 </ul>
 </section>

 <section class="space-y-4">
 <h2 class="text-xl md:text-2xl font-black text-[#ffffff] dark:text-white uppercase tracking-tight">3. Uso de la Información</h2>
 <p>Utilizamos tu información para:</p>
 <ul class="list-disc pl-6 space-y-2">
 <li>Procesar cotizaciones y órdenes de fabricación 3D</li>
 <li>Comunicarnos contigo sobre el estado de tus proyectos</li>
 <li>Mejorar nuestro motor de cotización OrcaEngine</li>
 <li>Cumplir con obligaciones fiscales y legales en Colombia</li>
 <li>Enviar comunicaciones relacionadas con el servicio (no spam)</li>
 </ul>
 </section>

 <section class="space-y-4">
 <h2 class="text-xl md:text-2xl font-black text-[#ffffff] dark:text-white uppercase tracking-tight">4. Protección de Datos</h2>
 <p>Implementamos medidas de seguridad técnicas y organizativas para proteger tu información, incluyendo:</p>
 <ul class="list-disc pl-6 space-y-2">
 <li>Cifrado SSL/TLS en todas las comunicaciones</li>
 <li>Almacenamiento seguro con Supabase (PostgreSQL)</li>
 <li>Control de acceso basado en tokens (Sanctum)</li>
 <li>Límite de tamaño de archivos (150MB) para prevenir abusos</li>
 </ul>
 </section>

 <section class="space-y-4">
 <h2 class="text-xl md:text-2xl font-black text-[#ffffff] dark:text-white uppercase tracking-tight">5. Archivos 3D y Propiedad Intelectual</h2>
 <p>Los archivos 3D que subes a nuestra plataforma son de tu propiedad. No compartimos, distribuimos ni utilizamos tus diseños sin tu consentimiento explícito. Una vez completada tu orden, los archivos se eliminan de nuestros servidores dentro de los 30 días posteriores a la finalización del proyecto.</p>
 </section>

 <section class="space-y-4">
 <h2 class="text-xl md:text-2xl font-black text-[#ffffff] dark:text-white uppercase tracking-tight">6. Tus Derechos</h2>
 <p>Como titular de datos en Colombia (Ley 1581 de 2012), tienes derecho a:</p>
 <ul class="list-disc pl-6 space-y-2">
 <li>Conocer, actualizar y rectificar tus datos personales</li>
 <li>Solicitar la eliminación de tus datos</li>
 <li>Revocar la autorización para el tratamiento de datos</li>
 <li>Presentar quejas ante la Superintendencia de Industria y Comercio</li>
 </ul>
 </section>

 <section class="space-y-4">
 <h2 class="text-xl md:text-2xl font-black text-[#ffffff] dark:text-white uppercase tracking-tight">7. Contacto</h2>
 <p>Para ejercer tus derechos o resolver cualquier duda sobre esta política, contáctanos:</p>
 <div class="bg-[#151a22] dark:bg-[#151a22]/5 rounded-[24px] p-6 border border-[#21262d] dark:border-[#21262d] space-y-3 mt-4">
 <p class="text-sm"><strong class="text-[#ffffff] dark:text-white">Email:</strong> servicion3xt@gmail.com</p>
 <p class="text-sm"><strong class="text-[#ffffff] dark:text-white">Teléfono:</strong> +57 311 879 6416</p>
 <p class="text-sm"><strong class="text-[#ffffff] dark:text-white">WhatsApp:</strong> +57 311 879 6416</p>
 </div>
 </section>
 </div>
 </main>

 <AppFooter />
 </div>
</template>
