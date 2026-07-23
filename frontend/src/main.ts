import { createApp } from 'vue'
import { createHead } from '@unhead/vue'
import { injectSpeedInsights } from '@vercel/speed-insights'
import './style.css'
import App from './App.vue'
import router from './router'
import './assets/structuredData'

injectSpeedInsights()

const app = createApp(App)
const head = createHead()
app.use(head)
app.use(router)
app.mount('#app')
