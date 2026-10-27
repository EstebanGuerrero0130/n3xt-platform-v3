import { ref, watch, onScopeDispose, type Ref } from 'vue'

export interface LeadTimeQuery {
  /** Hay un precio vigente a la vista (sin él no se consulta) */
  shown: boolean
  technology: string
  hours: number
  urgent: boolean
}

export const LEAD_TIME_DEBOUNCE_MS = 500

/**
 * Fecha estimada de entrega del cotizador (GET /quote/lead-time): espera a que el
 * precio se quede quieto, no repite la misma consulta y descarta respuestas viejas.
 * `urgent` es el de la respuesta mostrada, no el del interruptor.
 */
export function useLeadTime(query: () => LeadTimeQuery, fetcher: (params: URLSearchParams) => Promise<any>, delay = LEAD_TIME_DEBOUNCE_MS) {
  const label: Ref<string> = ref('')
  const urgent = ref(false)
  let seq = 0
  let lastKey = ''
  let timer: ReturnType<typeof setTimeout> | null = null

  const clear = () => { label.value = ''; urgent.value = false }

  watch(query, (q) => {
    const params = new URLSearchParams({
      technology: q.technology,
      hours: (Math.ceil(q.hours * 10) / 10).toString(),
      urgent: q.urgent ? '1' : '0',
    })
    const key = params.toString()
    if (!q.shown || !(q.hours > 0)) {
      if (timer) clearTimeout(timer)
      seq++
      lastKey = ''
      clear()
      return
    }
    if (key === lastKey) return
    if (timer) clearTimeout(timer)
    lastKey = key
    const mine = ++seq
    // La fecha anterior ya no corresponde (p. ej. pasó a urgente)
    clear()
    timer = setTimeout(async () => {
      timer = null
      try {
        const data = await fetcher(params)
        if (mine !== seq) return
        label.value = typeof data?.label === 'string' ? data.label : ''
        urgent.value = q.urgent
      } catch {
        if (mine === seq) { clear(); lastKey = '' }
      }
    }, delay)
  }, { immediate: true })

  onScopeDispose(() => { if (timer) clearTimeout(timer) })

  return { label, urgent }
}
