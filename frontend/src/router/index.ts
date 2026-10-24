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

const router = createRouter({
  history: createWebHistory(),
  scrollBehavior(to, _from, savedPosition) {
    if (savedPosition) return savedPosition
    if (to.hash) return { el: to.hash, behavior: 'smooth' }
    return { top: 0 }
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
      path: '/catalog',
      name: 'catalog',
      component: CatalogView
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
