/**
 * N3XT contact constants — single source of truth for public contact fallbacks.
 * The admin-configured value (settings.web.social.whatsapp) takes precedence when available.
 */
export const DEFAULT_WHATSAPP_NUMBER = '573118796416'
export const DEFAULT_PHONE_DISPLAY = '+57 311 879 6416'
export const DEFAULT_EMAIL = 'servicion3xt@gmail.com'

/** Extracts the digits of a WhatsApp number from a raw value or a wa.me link. */
export function parseWhatsAppNumber(value: unknown): string {
  if (typeof value !== 'string' || !value.trim()) return ''
  const raw = value.includes('wa.me/') ? value.split('wa.me/')[1].split('?')[0] : value
  return raw.replace(/\D/g, '')
}

export function whatsappLink(number: string = DEFAULT_WHATSAPP_NUMBER, text?: string): string {
  const base = `https://wa.me/${number || DEFAULT_WHATSAPP_NUMBER}`
  return text ? `${base}?text=${encodeURIComponent(text)}` : base
}
