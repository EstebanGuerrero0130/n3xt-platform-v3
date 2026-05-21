import { createRouter, createWebHistory } from 'vue-router'

// Lazy-loaded routes for code splitting (N3XT Performance Protocol)
const HomeView = () => import('../views/HomeView.vue')
const QuoteView = () => import('../views/QuoteView.vue')
const CatalogView = () => import('../views/CatalogView.vue')
const ProductDetailView = () => import('../views/ProductDetailView.vue')
const TrackView = () => import('../views/TrackView.vue')
const ProjectInitView = () => import('../views/ProjectInitView.vue')
const LoginView = () => import('../views/LoginView.vue')
const AdminDashboard = () => import('../views/AdminDashboard.vue')
const CustomerDashboard = () => import('../views/CustomerDashboard.vue')

const router = createRouter({
  history: createWebHistory(),
  routes: [
    {
      path: '/',
      name: 'home',
      component: HomeView
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
      beforeEnter: (to, from, next) => {
        const token = localStorage.getItem('n3xt_admin_token')
        if (!token) {
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
      beforeEnter: (to, from, next) => {
        const token = localStorage.getItem('n3xt_customer_token')
        if (!token) {
          next('/admin/login') // Redirigir al login si no hay token
        } else {
          next()
        }
      }
    }
  ]
})

export default router
