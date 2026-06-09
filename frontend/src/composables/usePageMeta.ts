/**
 * N3XT usePageMeta Composable
 * Wrapper around @unhead/vue useHead for consistent SEO meta tags.
 * Replaces manual document.title + injectedMetaEls pattern.
 */
import { useHead } from '@unhead/vue'

export interface PageMeta {
  title: string
  description: string
  image?: string
  type?: string           // og:type (website, product, article)
  noIndex?: boolean
}

const BASE_URL = 'https://n3xt3d.com'

export function usePageMeta(meta: PageMeta) {
  const title = meta.title
  const description = meta.description
  const image = meta.image || '/assets/n3xt_og_default.png'
  const type = meta.type || 'website'
  const fullImageUrl = image.startsWith('http') ? image : `${BASE_URL}${image}`

  useHead({
    title,
    meta: [
      // Standard meta
      { name: 'description', content: description },

      // Open Graph
      { property: 'og:title', content: title },
      { property: 'og:description', content: description },
      { property: 'og:image', content: fullImageUrl },
      { property: 'og:type', content: type },
      { property: 'og:url', content: BASE_URL },

      // Twitter Cards
      { name: 'twitter:card', content: 'summary_large_image' },
      { name: 'twitter:title', content: title },
      { name: 'twitter:description', content: description },
      { name: 'twitter:image', content: fullImageUrl },

      // Robots
      ...(meta.noIndex
        ? [{ name: 'robots', content: 'noindex, nofollow' }]
        : []),
    ],
  })
}
