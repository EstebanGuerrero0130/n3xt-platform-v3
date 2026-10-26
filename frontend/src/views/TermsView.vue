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
  <div class="min-h-screen flex flex-col bg-canvas text-ink overflow-x-clip">
    <AppNavbar active-tab="" subtext="Términos" />

    <main id="main" class="flex-1 w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 md:py-16">
      <header class="mb-10 md:mb-14 grid lg:grid-cols-12 gap-6 lg:items-end">
        <h1 class="section-title lg:col-span-8">Términos y <span class="section-title-accent">condiciones</span></h1>
        <p class="lg:col-span-4 text-sm text-muted">Última actualización: Octubre 2026</p>
      </header>

      <div class="grid lg:grid-cols-12 gap-10 items-start">
        <!-- Índice -->
        <nav class="hidden lg:block lg:col-span-4 lg:sticky lg:top-24" aria-label="Contenido">
          <p class="text-sm font-semibold text-ink mb-3">En esta página</p>
          <ol>
            <li><a href="#aceptacion-de-los-terminos" class="block py-1.5 text-sm text-muted hover:text-ink transition-colors">1. Aceptación de los Términos</a></li>
            <li><a href="#descripcion-del-servicio" class="block py-1.5 text-sm text-muted hover:text-ink transition-colors">2. Descripción del Servicio</a></li>
            <li><a href="#cotizaciones-y-ordenes" class="block py-1.5 text-sm text-muted hover:text-ink transition-colors">3. Cotizaciones y Órdenes</a></li>
            <li><a href="#propiedad-intelectual" class="block py-1.5 text-sm text-muted hover:text-ink transition-colors">4. Propiedad Intelectual</a></li>
            <li><a href="#garantia-y-calidad" class="block py-1.5 text-sm text-muted hover:text-ink transition-colors">5. Garantía y Calidad</a></li>
            <li><a href="#envios" class="block py-1.5 text-sm text-muted hover:text-ink transition-colors">6. Envíos</a></li>
            <li><a href="#limitacion-de-responsabilidad" class="block py-1.5 text-sm text-muted hover:text-ink transition-colors">7. Limitación de Responsabilidad</a></li>
            <li><a href="#contacto" class="block py-1.5 text-sm text-muted hover:text-ink transition-colors">8. Contacto</a></li>
          </ol>
        </nav>

        <!-- Contenido -->
        <div class="legal lg:col-span-8">
          <section aria-labelledby="aceptacion-de-los-terminos">
            <h2 id="aceptacion-de-los-terminos">1. Aceptación de los Términos</h2>
            <p>Al utilizar la plataforma N3XT 3D y sus servicios de manufactura digital, aceptas estar sujeto a estos términos y condiciones. Si no estás de acuerdo con alguna parte, no debes usar nuestros servicios.</p>
          </section>

          <section aria-labelledby="descripcion-del-servicio">
            <h2 id="descripcion-del-servicio">2. Descripción del Servicio</h2>
            <p>N3XT 3D ofrece servicios de fabricación aditiva (impresión 3D) incluyendo:</p>
            <ul>
              <li>Cotización automática en línea a partir de tu archivo 3D</li>
              <li>Impresión 3D en tecnologías FDM y SLA</li>
              <li>Post-procesado y acabado de piezas</li>
              <li>Venta de catálogo de piezas y coleccionables</li>
              <li>Asesoría técnica en diseño para manufactura aditiva</li>
            </ul>
          </section>

          <section aria-labelledby="cotizaciones-y-ordenes">
            <h2 id="cotizaciones-y-ordenes">3. Cotizaciones y Órdenes</h2>
            <ul>
              <li>Las cotizaciones del cotizador en línea son estimaciones basadas en tu archivo y los parámetros elegidos</li>
              <li>El precio final puede variar según el análisis técnico del archivo 3D</li>
              <li>Una vez confirmada la orden, se enviará una factura electrónica válida en Colombia</li>
              <li>Los tiempos de producción son estimados y pueden variar según la complejidad</li>
            <li>Se usa un cupón por pedido; no son acumulables. El cupón de bienvenida es personal, de un solo uso y solo vale con el correo de tu cuenta</li>
            <li>Eres responsable de mantener segura la contraseña de tu cuenta</li>
            </ul>
          </section>

          <section aria-labelledby="propiedad-intelectual">
            <h2 id="propiedad-intelectual">4. Propiedad Intelectual</h2>
            <p>Al subir un archivo 3D para cotización o producción:</p>
            <ul>
              <li>Declaras tener los derechos legales para reproducir el diseño</li>
              <li>N3XT 3D no reclama propiedad sobre tus diseños originales</li>
              <li>No fabricaremos ni distribuiremos copias de tus diseños sin autorización</li>
              <li>Nos reservamos el derecho de rechazar órdenes que infrinjan derechos de terceros</li>
            </ul>
          </section>

          <section aria-labelledby="garantia-y-calidad">
            <h2 id="garantia-y-calidad">5. Garantía y Calidad</h2>
            <ul>
              <li>Ofrecemos garantía de 6 meses en piezas de catálogo contra defectos de fabricación</li>
              <li>Las piezas personalizadas se fabrican según las especificaciones del archivo 3D proporcionado</li>
              <li>No nos hacemos responsables por problemas derivados del diseño del cliente</li>
              <li>Las variaciones dimensionales de ±0.5mm se consideran dentro del estándar industrial</li>
            </ul>
          </section>

          <section aria-labelledby="envios">
            <h2 id="envios">6. Envíos</h2>
            <ul>
              <li>Realizamos envíos a todo Colombia</li>
              <li>El costo de envío se calcula según la ubicación y dimensiones del paquete</li>
              <li>Los tiempos de entrega son estimados proporcionados por la transportadora</li>
              <li>No nos hacemos responsables por demoras causadas por la empresa de mensajería</li>
            </ul>
          </section>

          <section aria-labelledby="limitacion-de-responsabilidad">
            <h2 id="limitacion-de-responsabilidad">7. Limitación de Responsabilidad</h2>
            <p>N3XT 3D no será responsable por daños indirectos, incidentales o consecuentes derivados del uso o la imposibilidad de usar nuestros servicios, incluyendo pero no limitado a pérdida de datos, lucro cesante, o interrupción del negocio.</p>
          </section>

          <section aria-labelledby="contacto">
            <h2 id="contacto">8. Contacto</h2>
            <div class="legal-card">
              <p><strong>Correo:</strong> servicion3xt@gmail.com</p>
              <p><strong>WhatsApp:</strong> +57 311 879 6416</p>
            </div>
          </section>
        </div>
      </div>
    </main>

    <AppFooter />
  </div>
</template>
