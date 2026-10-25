<script setup lang="ts">
import { ref, onMounted, computed, watch, nextTick } from 'vue'
import { useRoute } from 'vue-router'
import { parsePrice, formatCOP } from '../utils/format'
import { api } from '../services/api'
import { useRevealAnim } from '../composables/useRevealAnim'
import AppNavbar from '../components/AppNavbar.vue'
import AppFooter from '../components/AppFooter.vue'
import logger from '../utils/logger'
import { useSplitTitle } from '../composables/useSplitTitle'
import { usePageMeta } from '../composables/usePageMeta'

const { applySplitTitle } = useSplitTitle()

usePageMeta({
  title: 'Detalles del Producto | N3XT 3D Shop',
  description: 'Explora nuestros productos 3D de alta precision. Figuras, prototipos y piezas industriales.',
  image: '/assets/n3xt_og_product.png',
  type: 'product',
})

const { observe: observeReveal } = useRevealAnim({ delay: 200 })

interface CatalogProduct {
  id?: string | number
  name: string
  image?: string
  images?: string[]
  category?: string
  subcategory?: string
  description?: string
  price?: string | number
  original_price?: string | number
  support_phone?: string
  technology?: string
  material?: string
  dimensions?: string
}

const MAX_QUANTITY = 99

const route = useRoute()
const quantity = ref(1)
const product = ref<CatalogProduct | null>(null)
const selectedImage = ref('')
const mainImageFailed = ref(false)
const loading = ref(true)
const loadError = ref(false)
const companySettings = ref<{ company?: { phone?: string } }>({ company: { phone: '' } })
const customerForm = ref({ name: '' })

// Identificador estable: muchos productos del catálogo no tienen id
const productKey = (p: CatalogProduct) => String(p.id ?? p.name)

const catalog = ref<CatalogProduct[]>([])
const relatedProducts = computed(() => {
  const current = product.value
  if (!current || !catalog.value.length) return []
  const currentKey = productKey(current)

  // 1. Intentar por subcategoría (si existe)
  let related = catalog.value.filter(i =>
    productKey(i) !== currentKey &&
    i.subcategory === current.subcategory &&
    i.subcategory
  )

  // 2. Si no hay suficientes, intentar por categoría
  if (related.length < 3) {
    const byCategory = catalog.value.filter(i =>
      productKey(i) !== currentKey &&
      i.category === current.category &&
      !related.find(r => productKey(r) === productKey(i))
    )
    related = [...related, ...byCategory]
  }

  return related.slice(0, 3)
})

// Si falla la imagen principal, se muestra un placeholder; se reintenta al cambiar de miniatura
watch(selectedImage, () => { mainImageFailed.value = false })

const fetchProduct = async () => {
  loading.value = true
  loadError.value = false
  try {
    const data = await api.get('/settings')
    companySettings.value = data || {}
    catalog.value = data?.web?.catalog || []
    // vue-router ya entrega el parámetro decodificado
    const rawParam = route.params.id
    const paramId = Array.isArray(rawParam) ? rawParam.join('/') : (rawParam || '')

    // Buscar por ID numérico o por nombre (slug)
    const found = catalog.value.find(i => String(i.id) === paramId || i.name === paramId)

    if (found) {
      product.value = found
      quantity.value = 1
      selectedImage.value = found.images && found.images.length > 0 ? found.images[0] : (found.image || '')
      mainImageFailed.value = false
      usePageMeta({ title: `${found.name} | N3XT 3D Shop`, description: found.description || 'Explora nuestros productos 3D de alta precision.', image: found.image || '/assets/n3xt_og_product.png', type: 'product' })
    } else {
      product.value = null
    }
  } catch (err) {
    logger.error('Error fetching product:', err)
    product.value = null
    loadError.value = true
  } finally {
    loading.value = false
    nextTick(() => {
      applySplitTitle()
      // La sección .reveal aparece tras la carga asíncrona: volver a observarla
      observeReveal()
    })
  }
}

const checkoutWhatsApp = () => {
  if (!product.value) return
  // Prioridad: Soporte específico del producto -> Soporte Global de la empresa
  const phone = (product.value.support_phone || companySettings.value.company?.phone || '573118796416').replace(/\+/g, '').replace(/\s/g, '')
  const name = customerForm.value.name.trim()
  const greeting = name ? `Hola N3XT 3D, soy *${name}*.` : 'Hola N3XT 3D,'
  const message = `${greeting}\n\nMe interesa este producto de su catalogo:\n\n*Producto:* ${product.value.name}\n*Cantidad:* ${quantity.value}\n*Precio Unitario:* ${formatCOP(product.value.price)}\n\nQuisiera mas informacion para concretar la compra.`
  window.open(`https://wa.me/${phone}?text=${encodeURIComponent(message)}`, '_blank')
}

const isDiscounted = computed(() => {
  const p = parsePrice(product.value?.price)
  const op = parsePrice(product.value?.original_price)
  return p !== null && op !== null && p > 0 && p < op
})

const discountPct = computed(() => {
  const p = parsePrice(product.value?.price)
  const op = parsePrice(product.value?.original_price)
  if (!isDiscounted.value || !p || !op) return 0
  return Math.round((1 - p / op) * 100)
})

// Especificaciones: solo las que vienen del producto
const specs = computed(() => {
  const p = product.value
  if (!p) return []
  return [
    { label: 'Tecnología', value: p.technology },
    { label: 'Material', value: p.material },
    { label: 'Dimensiones', value: p.dimensions },
  ].filter((s): s is { label: string; value: string } => typeof s.value === 'string' && s.value.trim() !== '')
})

onMounted(() => {
  fetchProduct()
})

// Observar cambios en la URL para recargar el producto (Navegación entre relacionados)
watch(() => route.params.id, (newId, oldId) => {
  if (!newId || newId === oldId) return
  product.value = null
  fetchProduct()
  window.scrollTo({ top: 0, behavior: 'smooth' })
})
</script>

<template>
  <div class="min-h-screen bg-canvas text-ink overflow-x-hidden">
    <AppNavbar active-tab="catalog" subtext="Detalle de producto" />

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 md:py-14">
      <!-- Cargando -->
      <div v-if="loading" class="grid grid-cols-1 lg:grid-cols-2 gap-10 lg:gap-16" aria-hidden="true">
        <div class="space-y-4">
          <div class="aspect-square bg-surface-2 animate-pulse rounded-[var(--radius-card)]"></div>
          <div class="grid grid-cols-4 gap-3">
            <div v-for="i in 4" :key="i" class="aspect-square bg-surface-2 animate-pulse rounded-[var(--radius-control)]"></div>
          </div>
        </div>
        <div class="space-y-4">
          <div class="h-4 bg-surface-2 animate-pulse rounded w-24"></div>
          <div class="h-12 bg-surface-2 animate-pulse rounded w-3/4"></div>
          <div class="h-8 bg-surface-2 animate-pulse rounded w-1/3"></div>
          <div class="h-4 bg-surface-2 animate-pulse rounded w-full mt-6"></div>
          <div class="h-4 bg-surface-2 animate-pulse rounded w-5/6"></div>
          <div class="h-12 bg-surface-2 animate-pulse rounded-[var(--radius-control)] w-full mt-8"></div>
        </div>
      </div>

      <template v-else-if="product">
        <router-link to="/catalog" class="inline-flex items-center gap-2 text-sm font-medium text-muted hover:text-brand transition-colors mb-8">
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
          Volver al catálogo
        </router-link>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-10 lg:gap-16 items-start">
          <!-- Galería -->
          <div class="space-y-4 min-w-0 lg:sticky lg:top-24">
            <div class="relative aspect-square bg-surface-2 border border-line rounded-[var(--radius-card)] overflow-hidden flex items-center justify-center p-6">
              <img
                v-if="(selectedImage || product.image) && !mainImageFailed"
                :src="selectedImage || product.image"
                :alt="'Detalle de producto 3D: ' + product.name + (product.category ? ' (' + product.category + ')' : '') + ' — N3XT 3D Shop'"
                class="max-w-full max-h-full object-contain"
                fetchpriority="high"
                decoding="async"
                @error="mainImageFailed = true" />
              <svg v-else class="w-20 h-20 text-muted/60" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.25" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            </div>

            <!-- Miniaturas -->
            <div v-if="product.images && product.images.length > 1" class="grid grid-cols-4 gap-3" role="group" aria-label="Imágenes del producto">
              <button
                v-for="(img, i) in product.images" :key="i"
                type="button"
                :aria-pressed="selectedImage === img"
                :aria-label="'Ver imagen ' + (i + 1)"
                :class="selectedImage === img ? 'border-primary' : 'border-line hover:border-muted'"
                class="aspect-square bg-surface-2 rounded-[var(--radius-control)] border-2 overflow-hidden transition-colors"
                @click="selectedImage = img">
                <img :src="img" :alt="'Vista previa ' + (i + 1) + ' de ' + product.name + ' — N3XT 3D Shop'" class="w-full h-full object-cover" loading="lazy" decoding="async" @error="(e: any) => e.target.style.display='none'" />
              </button>
            </div>
          </div>

          <!-- Información -->
          <div class="min-w-0">
            <p v-if="product.category" class="text-sm text-muted">
              {{ product.category }}<template v-if="product.subcategory"> / {{ product.subcategory }}</template>
            </p>
            <h1 class="split-title mt-2 text-4xl sm:text-5xl font-extrabold tracking-tight text-ink break-words [overflow-wrap:anywhere]">
              {{ product.name }}
            </h1>

            <div class="mt-5 flex flex-wrap items-baseline gap-x-3 gap-y-2">
              <p class="text-3xl md:text-4xl font-semibold tabular-nums text-resin">{{ formatCOP(product.price) }}</p>
              <template v-if="isDiscounted">
                <p class="text-lg tabular-nums text-muted line-through"><span class="sr-only">Antes </span>{{ formatCOP(product.original_price) }}</p>
                <span class="inline-flex items-center h-6 px-2 rounded-md text-xs font-semibold bg-surface-2 text-resin tabular-nums">-{{ discountPct }}%</span>
              </template>
            </div>

            <p v-if="product.description" class="mt-6 max-w-prose text-base leading-relaxed text-ink whitespace-pre-line">
              {{ product.description }}
            </p>

            <!-- Especificaciones (solo si vienen del producto) -->
            <dl v-if="specs.length" class="mt-8 grid grid-cols-2 gap-x-6 gap-y-5 border-t border-line pt-6">
              <div v-for="spec in specs" :key="spec.label">
                <dt class="text-sm text-muted">{{ spec.label }}</dt>
                <dd class="mt-1 font-medium text-ink">{{ spec.value }}</dd>
              </div>
            </dl>

            <!-- Pedido -->
            <div class="mt-8 pt-8 border-t border-line space-y-6">
              <div>
                <p id="product-quantity-label" class="block text-sm font-medium text-ink mb-2">Cantidad</p>
                <div class="inline-flex items-center h-11 rounded-[var(--radius-control)] border border-line bg-surface" role="group" aria-labelledby="product-quantity-label">
                  <button type="button" class="w-11 h-full flex items-center justify-center text-lg text-ink hover:bg-surface-2 rounded-l-[var(--radius-control)] transition-colors disabled:opacity-40 disabled:cursor-not-allowed" aria-label="Disminuir cantidad" :disabled="quantity <= 1" @click="quantity > 1 ? quantity-- : null">−</button>
                  <span class="w-12 text-center font-semibold tabular-nums" aria-live="polite" aria-label="Cantidad">{{ quantity }}</span>
                  <button type="button" class="w-11 h-full flex items-center justify-center text-lg text-ink hover:bg-surface-2 rounded-r-[var(--radius-control)] transition-colors disabled:opacity-40 disabled:cursor-not-allowed" aria-label="Aumentar cantidad" :disabled="quantity >= MAX_QUANTITY" @click="quantity < MAX_QUANTITY ? quantity++ : null">+</button>
                </div>
              </div>

              <div>
                <label for="product-customer-name" class="block text-sm font-medium text-ink mb-2">Tu nombre <span class="font-normal text-muted">(opcional, para saludarte en WhatsApp)</span></label>
                <input
                  id="product-customer-name"
                  v-model="customerForm.name"
                  type="text"
                  autocomplete="name"
                  placeholder="Ej. Juan Pérez"
                  class="w-full h-11 px-4 rounded-[var(--radius-control)] border border-line bg-canvas text-ink placeholder:text-muted/70 focus:outline-none focus:border-primary">
              </div>

              <button type="button" class="split-btn btn-primary btn-lg w-full" @click="checkoutWhatsApp">
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.67-1.618-.918-2.213-.242-.588-.487-.51-.67-.51-.172-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                <span>Pedir por WhatsApp</span>
              </button>

              <ul class="space-y-2 text-sm text-muted">
                <li class="flex items-center gap-2">
                  <svg class="w-4 h-4 text-brand shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                  Envíos a todo Colombia
                </li>
                <li class="flex items-center gap-2">
                  <svg class="w-4 h-4 text-brand shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                  Garantía de 6 meses
                </li>
                <li class="flex items-center gap-2">
                  <svg class="w-4 h-4 text-brand shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                  <span>¿Dudas? Escríbenos al <span class="font-medium text-ink tabular-nums">{{ product.support_phone || companySettings.company?.phone || '+57 3118796416' }}</span></span>
                </li>
              </ul>
            </div>
          </div>
        </div>

        <!-- Relacionados -->
        <section v-if="product && relatedProducts.length > 0" class="mt-20 md:mt-28 pt-12 border-t border-line reveal" aria-labelledby="related-heading">
          <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 mb-8">
            <div>
              <h2 id="related-heading" class="text-3xl md:text-4xl font-extrabold tracking-tight text-ink">Piezas similares</h2>
              <p class="mt-2 text-muted">De la misma línea o temática.</p>
            </div>
            <router-link to="/catalog" class="text-sm font-medium text-brand hover:underline underline-offset-4">Ver todo el catálogo</router-link>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            <router-link
              v-for="rel in relatedProducts"
              :key="productKey(rel)"
              :to="'/catalog/' + encodeURIComponent(rel.name)"
              class="group flex flex-col bg-surface border border-line rounded-[var(--radius-card)] overflow-hidden transition-colors hover:border-primary"
            >
              <div class="relative aspect-square bg-surface-2 overflow-hidden">
                <img v-if="rel.image" :src="rel.image" :alt="'Producto relacionado: ' + rel.name + ' | N3XT 3D Shop'" class="absolute inset-0 w-full h-full object-cover" loading="lazy" decoding="async" @error="(e: any) => e.target.style.display='none'" />
              </div>
              <div class="flex flex-col flex-1 p-5">
                <p v-if="rel.category" class="text-xs text-muted">{{ rel.category }}</p>
                <h3 class="mt-1 text-lg md:text-xl font-bold tracking-tight text-ink group-hover:text-brand transition-colors">{{ rel.name }}</h3>
                <p class="mt-auto pt-4 text-xl font-semibold tabular-nums text-resin">{{ formatCOP(rel.price) }}</p>
              </div>
            </router-link>
          </div>
        </section>
      </template>

      <!-- Error de carga -->
      <div v-else-if="loadError" class="py-24 text-center max-w-md mx-auto" role="alert">
        <h1 class="text-2xl font-extrabold tracking-tight text-ink">No pudimos cargar el producto</h1>
        <p class="mt-3 text-muted">Revisa tu conexión e inténtalo de nuevo.</p>
        <button type="button" class="btn-primary mt-6" @click="fetchProduct">Reintentar</button>
      </div>

      <!-- No encontrado -->
      <div v-else class="py-24 text-center max-w-md mx-auto">
        <h1 class="text-2xl font-extrabold tracking-tight text-ink">Producto no encontrado</h1>
        <p class="mt-3 text-muted">Puede que ya no esté disponible. Mira las demás piezas del catálogo.</p>
        <router-link to="/catalog" class="btn-primary mt-6">Volver al catálogo</router-link>
      </div>
    </main>

    <AppFooter />
  </div>
</template>

<style scoped>
.reveal {
  opacity: 0;
  transform: translateY(12px);
  transition: opacity 0.5s cubic-bezier(0.22, 1, 0.36, 1),
              transform 0.5s cubic-bezier(0.22, 1, 0.36, 1);
}
.reveal.revealed {
  opacity: 1;
  transform: translateY(0);
}
</style>
