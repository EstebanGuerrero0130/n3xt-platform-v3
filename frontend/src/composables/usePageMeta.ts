/**
 * N3XT usePageMeta Composable
 * Wrapper around @unhead/vue useHead for consistent SEO meta tags.
 * Replaces manual document.title + injectedMetaEls pattern.
 */
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { useHead } from '@unhead/vue'

export interface PageMeta {
  title: string
  description: string
  image?: string
  type?: string           // og:type (website, product, article)
  noIndex?: boolean
  /** Datos estructurados schema.org (JSON-LD) de la página */
  jsonLd?: Record<string, unknown> | null
}

const BASE_URL = 'https://n3xt3d.com'

/** URL absoluta para datos estructurados (las imágenes locales son relativas). */
export const absoluteUrl = (path?: string) => (!path ? undefined : path.startsWith('http') ? path : `${BASE_URL}${path.startsWith('/') ? '' : '/'}${path}`)

/**
 * Debe llamarse en `setup` (no tras un `await`). Para datos que llegan después
 * (producto, proyecto) se pasa una función: los tags se actualizan solos.
 */
export function usePageMeta(input: PageMeta | (() => PageMeta)) {
  const meta = computed(() => (typeof input === 'function' ? input() : input))
  const title = computed(() => meta.value.title)
  const description = computed(() => meta.value.description)
  const type = computed(() => meta.value.type || 'website')
  const fullImageUrl = computed(() => {
    const image = meta.value.image || '/assets/n3xt_og_default.jpg'
    return image.startsWith('http') ? image : `${BASE_URL}${image}`
  })
  // URL canónica de la ruta actual (sin query ni hash)
  const route = useRoute()
  const canonical = computed(() => `${BASE_URL}${route.path === '/' ? '/' : route.path.replace(/\/$/, '')}`)

  useHead({
    title,
    script: computed(() => meta.value.jsonLd
      ? [{ type: 'application/ld+json', key: 'page-jsonld', innerHTML: JSON.stringify({ '@context': 'https://schema.org', ...meta.value.jsonLd }) }]
      : []),
    link: [{ rel: 'canonical', href: canonical }],
    meta: computed(() => [
      { name: 'description', content: description.value },

      // Open Graph
      { property: 'og:title', content: title.value },
      { property: 'og:description', content: description.value },
      { property: 'og:image', content: fullImageUrl.value },
      { property: 'og:type', content: type.value },
      { property: 'og:url', content: canonical.value },

      // Twitter Cards
      { name: 'twitter:card', content: 'summary_large_image' },
      { name: 'twitter:title', content: title.value },
      { name: 'twitter:description', content: description.value },
      { name: 'twitter:image', content: fullImageUrl.value },

      // Robots
      ...(meta.value.noIndex ? [{ name: 'robots', content: 'noindex, nofollow' }] : []),
    ]),
  })
}
