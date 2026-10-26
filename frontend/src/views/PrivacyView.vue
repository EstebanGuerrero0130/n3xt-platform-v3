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
  <div class="min-h-screen flex flex-col bg-canvas text-ink overflow-x-clip">
    <AppNavbar active-tab="" subtext="Privacidad" />

    <main id="main" class="flex-1 w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 md:py-16">
      <header class="mb-10 md:mb-14 grid lg:grid-cols-12 gap-6 lg:items-end">
        <h1 class="section-title lg:col-span-8">Política de <span class="section-title-accent">privacidad</span></h1>
        <p class="lg:col-span-4 text-sm text-muted">Última actualización: Octubre 2026</p>
      </header>

      <div class="grid lg:grid-cols-12 gap-10 items-start">
        <!-- Índice -->
        <nav class="hidden lg:block lg:col-span-4 lg:sticky lg:top-24" aria-label="Contenido">
          <p class="text-sm font-semibold text-ink mb-3">En esta página</p>
          <ol>
            <li><a href="#introduccion" class="block py-1.5 text-sm text-muted hover:text-ink transition-colors">1. Introducción</a></li>
            <li><a href="#informacion-que-recopilamos" class="block py-1.5 text-sm text-muted hover:text-ink transition-colors">2. Información que Recopilamos</a></li>
            <li><a href="#uso-de-la-informacion" class="block py-1.5 text-sm text-muted hover:text-ink transition-colors">3. Uso de la Información</a></li>
            <li><a href="#proteccion-de-datos" class="block py-1.5 text-sm text-muted hover:text-ink transition-colors">4. Protección de Datos</a></li>
            <li><a href="#archivos-3d-y-propiedad-intelectual" class="block py-1.5 text-sm text-muted hover:text-ink transition-colors">5. Archivos 3D y Propiedad Intelectual</a></li>
            <li><a href="#tus-derechos" class="block py-1.5 text-sm text-muted hover:text-ink transition-colors">6. Tus Derechos</a></li>
            <li><a href="#contacto" class="block py-1.5 text-sm text-muted hover:text-ink transition-colors">7. Contacto</a></li>
          </ol>
        </nav>

        <!-- Contenido -->
        <div class="legal lg:col-span-8">
          <section aria-labelledby="introduccion">
            <h2 id="introduccion">1. Introducción</h2>
            <p>En N3XT 3D nos comprometemos a proteger tu privacidad. Esta política describe cómo recopilamos, usamos y protegemos tu información personal cuando utilizas nuestros servicios de manufactura digital y cotización de piezas 3D.</p>
          </section>

          <section aria-labelledby="informacion-que-recopilamos">
            <h2 id="informacion-que-recopilamos">2. Información que Recopilamos</h2>
            <p>Podemos recopilar los siguientes datos personales:</p>
            <ul>
              <li><strong>Datos de contacto:</strong> Nombre, correo electrónico, teléfono y dirección de envío.</li>
              <li><strong>Datos de facturación:</strong> NIT, razón social y documentos de identificación cuando aplique.</li>
              <li><strong>Datos de tu cuenta:</strong> Correo y contraseña (guardada cifrada) si creas una cuenta.</li>
              <li><strong>Datos técnicos:</strong> Archivos 3D (STL, OBJ, 3MF), parámetros de impresión y especificaciones de diseño.</li>
              <li><strong>Datos de navegación:</strong> Contamos las visitas a las páginas públicas de forma agregada (qué páginas se ven y desde qué tipo de dispositivo) para mejorar el sitio. No medimos tu cuenta ni el panel del taller, y no usamos esos datos para publicidad.</li>
            </ul>
          </section>

          <section aria-labelledby="uso-de-la-informacion">
            <h2 id="uso-de-la-informacion">3. Uso de la Información</h2>
            <p>Utilizamos tu información para:</p>
            <ul>
              <li>Procesar cotizaciones y órdenes de fabricación 3D</li>
              <li>Comunicarnos contigo sobre el estado de tus proyectos</li>
              <li>Manejar tu cuenta: verificar tu correo con un código, recuperar tu contraseña y mostrarte tus pedidos</li>
            <li>Avisarte por correo cuando recibimos tu pedido y cada vez que cambia de estado</li>
            <li>Mejorar el cotizador y nuestros precios</li>
              <li>Cumplir con obligaciones fiscales y legales en Colombia</li>
              <li>Enviar comunicaciones relacionadas con el servicio (no spam)</li>
            </ul>
          </section>

          <section aria-labelledby="proteccion-de-datos">
            <h2 id="proteccion-de-datos">4. Protección de Datos</h2>
            <p>Implementamos medidas de seguridad técnicas y organizativas para proteger tu información, incluyendo:</p>
            <ul>
              <li>Cifrado SSL/TLS en todas las comunicaciones</li>
              <li>Contraseñas cifradas: nunca guardamos tu contraseña en texto</li>
              <li>Tus pedidos solo se ven desde tu cuenta, después de verificar tu correo</li>
              <li>Archivos 3D en almacenamiento privado, al que solo accede el taller</li>
              <li>Límite de tamaño de archivos (50 MB) para prevenir abusos</li>
            </ul>
          </section>

          <section aria-labelledby="archivos-3d-y-propiedad-intelectual">
            <h2 id="archivos-3d-y-propiedad-intelectual">5. Archivos 3D y Propiedad Intelectual</h2>
            <p>Los archivos 3D que subes a nuestra plataforma son de tu propiedad. No compartimos, distribuimos ni utilizamos tus diseños sin tu consentimiento explícito. Los guardamos mientras fabricamos y entregamos tu pedido, por si hay que repetir una pieza. Si quieres que los borremos, escríbenos y lo hacemos en máximo 10 días hábiles.</p>
          </section>

          <section aria-labelledby="tus-derechos">
            <h2 id="tus-derechos">6. Tus Derechos</h2>
            <p>Como titular de datos en Colombia (Ley 1581 de 2012), tienes derecho a:</p>
            <ul>
              <li>Conocer, actualizar y rectificar tus datos personales</li>
              <li>Solicitar la eliminación de tus datos</li>
              <li>Revocar la autorización para el tratamiento de datos</li>
              <li>Presentar quejas ante la Superintendencia de Industria y Comercio</li>
            </ul>
          </section>

          <section aria-labelledby="contacto">
            <h2 id="contacto">7. Contacto</h2>
            <p>Para ejercer tus derechos o resolver cualquier duda sobre esta política, contáctanos:</p>
            <div class="legal-card">
              <p><strong>Email:</strong> servicion3xt@gmail.com</p>
              <p><strong>Teléfono:</strong> +57 311 879 6416</p>
              <p><strong>WhatsApp:</strong> +57 311 879 6416</p>
            </div>
          </section>
        </div>
      </div>
    </main>

    <AppFooter />
  </div>
</template>
