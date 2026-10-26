import { formatCOP } from './format'

/** Número de WhatsApp sin signos (de la empresa o el de respaldo). */
export const whatsappNumber = (phone?: string) => (phone || '573118796416').replace(/[^\d]/g, '')

/** Mensaje de pedido del catálogo para WhatsApp (uno o varios productos). */
export function catalogWhatsAppUrl(opts: {
  phone?: string
  name?: string
  items: { name: string; qty: number; price: number }[]
  orderId?: number | null
}): string {
  const greeting = opts.name ? `Hola N3XT 3D, soy *${opts.name}*.` : 'Hola N3XT 3D,'
  const lines = opts.items.map(i => `• ${i.qty} × *${i.name}* (${formatCOP(i.price)} c/u)`).join('\n')
  const total = opts.items.reduce((s, i) => s + i.price * i.qty, 0)
  const order = opts.orderId ? `\n*Pedido N3XT:* #${opts.orderId}` : ''
  const message = `${greeting}\n\nQuiero estos productos de su catálogo:\n\n${lines}\n\n*Total estimado:* ${formatCOP(total)}${order}\n\n¿Me confirman disponibilidad y el envío?`
  return `https://wa.me/${whatsappNumber(opts.phone)}?text=${encodeURIComponent(message)}`
}
