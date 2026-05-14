import { createRouter, createWebHistory } from 'vue-router'
import QuoteView from '../views/QuoteView.vue'
import HomeView from '../views/HomeView.vue'
import LoginView from '../views/LoginView.vue'
import AdminDashboard from '../views/AdminDashboard.vue'
import TrackView from '../views/TrackView.vue'
import CustomerDashboard from '../views/CustomerDashboard.vue'
import CatalogView from '../views/CatalogView.vue'
import ProductDetailView from '../views/ProductDetailView.vue'
import ProjectInitView from '../views/ProjectInitView.vue'

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
