<script setup lang="ts">
/**
 * Noticias: lista en /noticias y cada nota en /noticias/:slug.
 * Salen de `web.news` (panel → Sitio web → Noticias); solo las publicadas.
 */
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { api } from '../services/api'
import { publishedNews, paragraphs, isExternalUrl as isExternal, newsLink as linkOf, type NewsItem } from '../utils/news'
import { srcsetFor } from '../utils/images'
import { usePageMeta, absoluteUrl } from '../composables/usePageMeta'
import AppNavbar from '../components/AppNavbar.vue'
import AppFooter from '../components/AppFooter.vue'

const route = useRoute()
const news = ref<NewsItem[]>([])
const loading = ref(true)
const loadError = ref(false)

const slug = computed(() => String(route.params.slug || ''))
const article = computed(() => (slug.value ? news.value.find(n => n.slug === slug.value) ?? null : null))
const others = computed(() => news.value.filter(n => n.slug !== slug.value).slice(0, 3))

const dateLabel = (d: string) => (d ? new Date(d + 'T12:00:00').toLocaleDateString('es-CO', { day: 'numeric', month: 'long', year: 'numeric' }) : '')

usePageMeta(() => article.value
  ? {
      title: `${article.value.title} | Noticias N3XT 3D`,
      description: article.value.summary || 'Noticias del taller N3XT 3D.',
      image: article.value.image || undefined,
      type: 'article',
      jsonLd: {
        '@type': 'NewsArticle',
        headline: article.value.title,
        description: article.value.summary || undefined,
        image: article.value.image ? [absoluteUrl(article.value.image)] : undefined,
        datePublished: article.value.date || undefined,
        articleSection: article.value.category || undefined,
        // El negocio se declara una vez en assets/structuredData.ts
        author: { '@id': 'https://n3xt3d.com/#business' },
        publisher: { '@id': 'https://n3xt3d.com/#business' },
        mainEntityOfPage: absoluteUrl(`/noticias/${article.value.slug}`),
      },
    }
  : {
      title: slug.value && !loading.value ? 'Noticia no encontrada | N3XT 3D' : 'Noticias | N3XT 3D',
      description: 'Novedades del taller N3XT 3D y de la impresión 3D en Colombia: eventos, materiales y proyectos.',
      noIndex: !!slug.value && !loading.value,
    })

onMounted(async () => {
  try {
    const data = await api.get('/settings')
    news.value = publishedNews(data?.web?.news)
  } catch {
    loadError.value = true
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="min-h-screen flex flex-col bg-canvas text-ink overflow-x-clip">
    <AppNavbar active-tab="news" subtext="Noticias" />

    <main id="main" class="flex-1 w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-16 pb-24 md:pt-24 md:pb-36">
      <p v-if="loading" class="py-24 text-center text-muted" role="status">Cargando noticias…</p>
      <p v-else-if="loadError" class="py-24 text-center text-muted" role="alert">No pudimos cargar las noticias. Intenta de nuevo en un momento.</p>

      <!-- Una nota -->
      <template v-else-if="slug">
        <article v-if="article" class="mx-auto max-w-3xl">
          <router-link to="/noticias" class="inline-flex items-center gap-2 font-semibold text-brand hover:underline underline-offset-4">← Todas las noticias</router-link>
          <p class="mt-8 text-base font-semibold text-brand">
            {{ article.category }}<span v-if="article.category && article.date" aria-hidden="true"> · </span><time v-if="article.date" :datetime="article.date" class="text-muted font-normal">{{ dateLabel(article.date) }}</time>
          </p>
          <h1 class="mt-3 text-4xl md:text-5xl font-black tracking-tight leading-[1.08] text-balance">{{ article.title }}</h1>
          <p v-if="article.summary && article.summary !== article.body.slice(0, 200)" class="mt-6 text-xl leading-relaxed text-ink/80">{{ article.summary }}</p>
          <div v-if="article.image" class="mt-10 overflow-hidden rounded-[var(--radius-panel)] bg-surface-2">
            <img :src="article.image" :srcset="srcsetFor(article.image)" sizes="(min-width: 768px) 48rem, 100vw" :alt="article.title" width="1024" height="640" class="aspect-[16/10] w-full object-cover" @error="(e: any) => e.target.style.display = 'none'">
          </div>
          <div class="mt-10 space-y-6 text-lg leading-relaxed">
            <p v-for="(p, i) in paragraphs(article.body)" :key="i" class="whitespace-pre-line">{{ p }}</p>
          </div>
          <a v-if="isExternal(article.url)" :href="article.url" target="_blank" rel="noopener noreferrer" class="mt-10 btn-secondary">Ver la fuente</a>
        </article>

        <div v-else class="py-24 text-center">
          <h1 class="text-3xl font-black tracking-tight">No encontramos esa noticia</h1>
          <p class="mt-3 text-muted">Puede que la hayamos retirado o cambiado de dirección.</p>
          <router-link to="/noticias" class="mt-8 btn-primary">Ver todas las noticias</router-link>
        </div>

        <section v-if="article && others.length" class="mt-24 border-t border-line pt-12" aria-labelledby="more-news">
          <h2 id="more-news" class="text-2xl md:text-3xl font-extrabold tracking-tight">Más noticias</h2>
          <ul class="mt-8 grid gap-8 md:grid-cols-3">
            <li v-for="n in others" :key="n.slug">
              <component :is="isExternal(linkOf(n)) ? 'a' : 'router-link'" v-bind="isExternal(linkOf(n)) ? { href: linkOf(n), target: '_blank', rel: 'noopener noreferrer' } : { to: linkOf(n) }" class="group block">
                <div class="overflow-hidden rounded-[var(--radius-card)] bg-surface-2">
                  <img v-if="n.image" :src="n.image" :srcset="srcsetFor(n.image)" sizes="(min-width: 768px) 30vw, 100vw" alt="" width="1024" height="640" loading="lazy" class="aspect-[16/10] w-full object-cover transition-transform duration-500 group-hover:scale-[1.03]">
                </div>
                <h3 class="mt-4 text-xl font-black tracking-tight leading-snug group-hover:underline underline-offset-4">{{ n.title }}</h3>
              </component>
            </li>
          </ul>
        </section>
      </template>

      <!-- Lista -->
      <template v-else>
        <header class="grid gap-6 lg:grid-cols-12 lg:items-end">
          <h1 class="section-title lg:col-span-7">Noticias <span class="section-title-accent">del taller</span></h1>
          <p class="section-lead lg:col-span-5">Eventos, materiales nuevos y proyectos de impresión 3D en Colombia.</p>
        </header>

        <p v-if="!news.length" class="mt-16 admin-card py-14 text-center text-muted">Pronto publicaremos novedades.</p>

        <ul v-else class="mt-16 grid gap-x-8 gap-y-14 md:grid-cols-2 lg:grid-cols-3">
          <li v-for="n in news" :key="n.slug">
            <component :is="isExternal(linkOf(n)) ? 'a' : 'router-link'" v-bind="isExternal(linkOf(n)) ? { href: linkOf(n), target: '_blank', rel: 'noopener noreferrer' } : { to: linkOf(n) }" class="group block">
              <div class="overflow-hidden rounded-[var(--radius-card)] bg-surface-2">
                <img v-if="n.image" :src="n.image" :srcset="srcsetFor(n.image)" sizes="(min-width: 1024px) 30vw, (min-width: 768px) 45vw, 100vw" alt="" width="1024" height="640" loading="lazy" class="aspect-[16/10] w-full object-cover transition-transform duration-500 group-hover:scale-[1.03]">
              </div>
              <p class="mt-5 text-sm font-semibold text-brand">
                {{ n.category }}<span v-if="n.category && n.date" aria-hidden="true"> · </span><time v-if="n.date" :datetime="n.date" class="text-muted font-normal">{{ dateLabel(n.date) }}</time>
              </p>
              <h2 class="mt-2 text-2xl font-black tracking-tight leading-snug group-hover:underline underline-offset-4">{{ n.title }}</h2>
              <p v-if="n.summary" class="mt-3 text-base leading-relaxed text-ink/80 line-clamp-3">{{ n.summary }}</p>
            </component>
          </li>
        </ul>
      </template>
    </main>

    <AppFooter />
  </div>
</template>
