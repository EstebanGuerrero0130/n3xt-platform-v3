import { createApp } from 'vue'
import { createHead } from '@unhead/vue'
import './style.css'
import App from './App.vue'
import router from './router'
import './assets/structuredData'
import { installDirectives } from './directives'
import { initSmoothScroll } from './composables/useSmoothScroll'

const app = createApp(App)
const head = createHead()
app.use(head)
app.use(router)
installDirectives(app)
app.mount('#app')

initSmoothScroll(router)
