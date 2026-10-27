/**
 * Carrito del catálogo, compartido entre vistas y guardado en el navegador.
 * El precio aquí es solo para mostrar: el servidor lo recalcula al guardar el pedido.
 */
import { reactive, computed, watch } from 'vue'
import { parsePrice } from '../utils/format'

export interface CartItem {
  key: string // id del producto o su nombre (como en /catalog/:id)
  name: string
  price: number
  image?: string
  qty: number
}

const STORAGE_KEY = 'n3xt_cart'
const MAX_QTY = 99

const load = (): CartItem[] => {
  try {
    const raw = JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]')
    return Array.isArray(raw) ? raw.filter(i => i && i.key && i.name && i.qty > 0) : []
  } catch {
    return []
  }
}

const state = reactive<{ items: CartItem[] }>({ items: load() })

watch(() => state.items, (items) => {
  try { localStorage.setItem(STORAGE_KEY, JSON.stringify(items)) } catch { /* sin almacenamiento: el carrito vive en memoria */ }
}, { deep: true })

export function useCart() {
  const count = computed(() => state.items.reduce((n, i) => n + i.qty, 0))
  const total = computed(() => state.items.reduce((s, i) => s + i.price * i.qty, 0))

  const add = (p: { id?: string | number; name: string; price?: unknown; image?: string }, qty = 1) => {
    const key = String(p.id ?? p.name)
    const existing = state.items.find(i => i.key === key)
    if (existing) existing.qty = Math.min(MAX_QTY, existing.qty + qty)
    else state.items.push({ key, name: p.name, price: parsePrice(p.price) ?? 0, image: p.image, qty: Math.min(MAX_QTY, qty) })
  }
  const setQty = (key: string, qty: number) => {
    const item = state.items.find(i => i.key === key)
    if (item) item.qty = Math.max(1, Math.min(MAX_QTY, Math.round(qty) || 1))
  }
  const remove = (key: string) => { state.items = state.items.filter(i => i.key !== key) }
  const clear = () => { state.items = [] }

  /**
   * Pone los precios del catálogo de hoy y quita lo que ya no se vende. El carrito
   * vive en el navegador: sin esto mostraría precios viejos (o editados a mano).
   */
  const syncWithCatalog = (catalog: { id?: string | number; name?: string; price?: unknown; status?: string; image?: string }[]) => {
    const active = new Map(catalog
      .filter(p => p?.name && (p.status ?? 'active') === 'active')
      .map(p => [String(p.id ?? p.name), p]))
    state.items = state.items
      .filter(i => active.has(i.key))
      .map(i => {
        const p = active.get(i.key)!
        return { ...i, name: p.name!, price: parsePrice(p.price) ?? 0, image: p.image || i.image }
      })
  }

  return { items: computed(() => state.items), count, total, add, setQty, remove, clear, syncWithCatalog }
}
