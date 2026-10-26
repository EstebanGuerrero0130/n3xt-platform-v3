import { createRouter, createWebHistory } from 'vue-router'
import { api } from '../services/api'

// Lazy-loaded routes for code splitting (N3XT Performance Protocol)
const HomeView = () => import('../views/HomeView.vue')
const QuoteView = () => import('../views/QuoteView.vue')
const CatalogView = () => import('../views/CatalogView.vue')
const ProductDetailView = () => import('../views/ProductDetailView.vue')
const TrackView = () => import('../views/TrackView.vue')
const ProjectInitView = () => import('../views/ProjectInitView.vue')
const LoginView = () => import('../views/LoginView.vue')
const AdminDashboard = () => import('../views/AdminDashboard.vue')
const GalleryView = () => import('../views/GalleryView.vue')
const GalleryDetailView = () => import('../views/GalleryDetailView.vue')
const CustomerDashboard = () => import('../views/CustomerDashboard.vue')
const PrivacyView = () => import('../views/PrivacyView.vue')
const TermsView = () => import('../views/TermsView.vue')
const NotFoundView = () => import('../views/NotFoundView.vue')

/** Debe coincidir con :duration.leave de la transición de página en App.vue */
const PAGE_LEAVE_MS = 160

const router = createRouter({
  history: createWebHistory(),
  // App.vue usa <transition mode="out-in"> (salida de 160 ms): se espera a que la vista
  // nueva esté montada; si no, la posición guardada se recorta y la saliente salta.
  scrollBehavior(to, from, savedPosition) {
    const target = savedPosition ?? (to.hash ? { el: to.hash, behavior: 'smooth' as const } : { top: 0 })
    if (to.path === from.path) return target
    return new Promise(resolve => setTimeout(() => resolve(target), PAGE_LEAVE_MS + 20))
  },
  routes: [
    {
      path: '/',
      name: 'home',
      component: HomeView
    },
    {
      path: '/galeria',
      name: 'gallery',
      component: GalleryView
    },
    {
      path: '/galeria/:id',
      name: 'gallery-detail',
      component: GalleryDetailView
    },
    {
      path: '/noticias/:slug?',
      name: 'news',
      component: () => import('../views/NewsView.vue')
    },
    {
      path: '/catalog',
      name: 'catalog',
      component: CatalogView
    },
    {
      path: '/carrito',
      name: 'cart',
      component: () => import('../views/CartView.vue')
    },
    {
      path: '/catalog/:id',
      name: 'product-detail',
      component: ProductDetailView
    },
    {
      path: '/quote',
      name: 'quote',
      component: QuoteView
    },
    {
      path: '/project/init',
      name: 'project-init',
      component: ProjectInitView
    },
    {
      path: '/track',
      name: 'track',
      component: TrackView
    },
    {
      path: '/admin/login',
      name: 'admin-login',
      component: LoginView
    },
    {
      path: '/admin',
      name: 'admin-dashboard',
      component: AdminDashboard,
      beforeEnter: async (to, from, next) => {
        const auth = await api.checkAuth()
        if (!auth.authenticated || auth.role !== 'admin') {
          next('/admin/login')
        } else {
          next()
        }
      }
    },

    {
      path: '/admin/dashboard',
      redirect: '/admin'
    },
    {
      path: '/customer/dashboard',
      name: 'customer-dashboard',
      component: CustomerDashboard,
      beforeEnter: async (to, from, next) => {
        const auth = await api.checkAuth()
        if (!auth.authenticated || auth.role !== 'customer') {
          next('/admin/login')
        } else {
          next()
        }
      }
    },
    {
      path: '/privacy',
      name: 'privacy',
      component: PrivacyView
    },
    {
      path: '/terms',
      name: 'terms',
      component: TermsView
    },
    {
      path: '/:pathMatch(.*)*',
      name: 'not-found',
      component: NotFoundView
    }
  ]
})

export default router
