// =============================================================================
// N3XT 3D — PDF/Print Generators (Extraído de AdminDashboard.vue)
// =============================================================================
// Funciones que devuelven HTML string para renderizar e imprimir en iframe.

/** Obtiene URL absoluta (maneja rutas relativas del storage) */
export function getAbsoluteUrl(path: string, _storageUrl?: string): string {
  if (!path) return ''
  if (path.startsWith('http')) return path
  const base: string = window.location.origin
  return base + (path.startsWith('/') ? '' : '/') + path
}

interface OrderData {
  id?: number | string
  customer_name?: string
  customer_company?: string
  customer_id_document?: string
  customer_email?: string
  customer_phone?: string
  shipping_address?: string
  shipping_city?: string
  shipping_zip?: string
  shipping_reference?: string
  tracking_guide?: string
  tracking_carrier?: string
  job_name?: string | null
  technology?: string
  material_name?: string
  material_id?: string
  estimated_weight_g?: number | string
  estimated_duration_h?: number | string
  total_price?: number | string
  extras_cost?: number | string
  extra_items?: Array<{ name?: string; cost?: number; qty?: number }>
}

interface SettingsData {
  company?: { name?: string; nit?: string; email?: string; phone?: string; address?: string; slogan?: string; website?: string }
  margin?: { iva?: number }
  company_logo?: string
}

/**
 * Crea un iframe oculto para impresión y devuelve su contentWindow
 */
export function getPrintFrame(): Window {
  let frame: HTMLIFrameElement | null = document.getElementById('n3xt-print-frame') as HTMLIFrameElement | null
  if (!frame) {
    frame = document.createElement('iframe')
    frame.id = 'n3xt-print-frame'
    frame.style.cssText = 'position:absolute;top:-9999px;left:-9999px;visibility:hidden;'
    document.body.appendChild(frame)
  }
  return frame.contentWindow || frame.contentDocument!.defaultView!
}

/**
 * Escribe HTML en un iframe y ejecuta print() tras cargar imágenes
 */
export function printHTML(html: string, timeout: number = 3000): void {
  const printWindow: Window = getPrintFrame()
  printWindow.document.open()
  printWindow.document.write(html)
  printWindow.document.close()

  const imgs: HTMLCollectionOf<HTMLImageElement> = printWindow.document.images
  if (imgs.length === 0) {
    setTimeout(() => { printWindow.focus(); printWindow.print() }, 250)
    return
  }

  let loaded = 0
  let printed = false
  const print = () => {
    if (!printed) { printed = true; printWindow.focus(); printWindow.print() }
  }
  const onload = () => { if (++loaded === imgs.length) print() }
  for (let i = 0; i < imgs.length; i++) {
    if (imgs[i].complete) onload()
    else { imgs[i].onload = onload; imgs[i].onerror = onload }
  }
  setTimeout(print, timeout)
}

// =============================================================================
// 1. SHIPPING LABEL (Etiqueta de Envío / Guía)
// =============================================================================
export function shippingLabelHTML(order: OrderData, settings: SettingsData): string {
  const company = settings.company || {}
  const logoUrl = settings.company_logo
    ? getAbsoluteUrl(settings.company_logo.startsWith('http') ? settings.company_logo : '/storage/' + settings.company_logo)
    : window.location.origin + '/logo.png'

  return `<!DOCTYPE html>
<html><head><meta charset="utf-8">
<style>
  @import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;700;900&display=swap');
  @page { size: 100mm 150mm; margin: 0; }
  body { font-family: 'Outfit', sans-serif; padding: 25px; text-transform: uppercase; color: #000; margin: 0; background: white; }
  .label-box { border: 10px solid #000; padding: 30px; height: 125mm; display: flex; flex-direction: column; position: relative; box-sizing: border-box; }
  .header { border-bottom: 5px solid #000; padding-bottom: 15px; margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; }
  .logo { font-size: 28px; font-weight: 900; letter-spacing: -1.5px; }
  .order-id { font-size: 16px; font-weight: 900; background: #000; color: #fff; padding: 8px 15px; border-radius: 8px; }
  .destinatario { flex: 1; display: flex; flex-direction: column; justify-content: center; }
  .label-text { font-size: 11px; font-weight: 900; margin-bottom: 8px; color: #666; letter-spacing: 2px; }
  .value-text { font-size: 32px; font-weight: 900; margin-bottom: 25px; line-height: 0.9; }
  .address-box { border-top: 3px solid #eee; padding-top: 20px; }
  .address-text { font-size: 22px; font-weight: 900; line-height: 1.1; margin-bottom: 10px; }
  .city-text { font-size: 18px; font-weight: 700; color: #444; }
  .footer { border-top: 5px solid #000; padding-top: 20px; font-size: 11px; font-weight: 900; display: flex; justify-content: space-between; align-items: flex-end; }
  .stamp { position: absolute; bottom: 120px; right: -40px; transform: rotate(-90deg); font-size: 10px; font-weight: 900; color: #eee; }
</style></head><body>
<div class="label-box">
  <div class="stamp">N3XT 3D SYSTEMS INDUSTRIAL LOGISTICS</div>
  <div class="header">
    <div class="logo">N3XT SHIPMENT</div>
    <div class="order-id">#${order.id}</div>
  </div>
  <div class="destinatario">
    <div class="label-text">DESTINATARIO:</div>
    <div class="value-text">${order.customer_name}</div>
    <div class="address-box">
      <div class="label-text">DIRECCIÓN DE ENTREGA:</div>
      <div class="address-text">${order.shipping_address || 'RECOGE EN TALLER'}</div>
      <div class="city-text">${order.shipping_city || ''} ${order.shipping_zip || ''}</div>
      <div class="city-text" style="margin-top: 10px;">TEL: ${order.customer_phone || 'S/D'}</div>
    </div>
  </div>
  <div class="footer">
    <div>
      ORIGEN: ${company.name || 'N3XT 3D SYSTEMS TALLER'}<br>
      CONTROL: ${new Date().toLocaleDateString()}<br>
      LOG: ${order.technology || ''} / ${order.estimated_weight_g || 0}G
    </div>
    <div style="background: #000; padding: 8px; border-radius: 15px;">
      <img src="https://api.qrserver.com/v1/create-qr-code/?size=120x120&color=ffffff&bgcolor=000000&data=${encodeURIComponent(window.location.origin + '/#/track?order_id=' + order.id + '&email=' + (order.customer_email || ''))}" style="width: 80px; height: 80px; display: block;">
    </div>
  </div>
</div></body></html>`
}

// =============================================================================
// 2. QUOTE / DELIVERY NOTE (Remisión de Entrega)
// =============================================================================
export function deliveryNoteHTML(order: OrderData, settings: SettingsData): string {
  const company = settings.company || {}
  const companyLogo = settings.company_logo
    ? getAbsoluteUrl(settings.company_logo.startsWith('http') ? settings.company_logo : '/storage/' + settings.company_logo)
    : window.location.origin + '/logo.png'

  return `<!DOCTYPE html>
<html><head><meta charset="utf-8">
<style>
  @import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700;900&display=swap');
  @page { size: A4; margin: 15mm; }
  body { font-family: 'Outfit', sans-serif; padding: 0; color: #0f172a; background: #fff; line-height: 1.4; }
  .page-border { border: 1px solid #e2e8f0; padding: 40px; height: calc(100vh - 120px); display: flex; flex-direction: column; position: relative; }
  .header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 4px solid #0f172a; padding-bottom: 25px; margin-bottom: 35px; }
  .brand h1 { font-size: 32px; font-weight: 900; letter-spacing: -2px; margin: 0; text-transform: uppercase; }
  .brand p { font-size: 10px; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 3px; margin: 5px 0 0 0; }
  .doc-type { text-align: right; }
  .doc-badge { background: #0f172a; color: white; padding: 8px 15px; border-radius: 8px; font-size: 10px; font-weight: 900; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px; display: inline-block; }
  .doc-num { font-size: 20px; font-weight: 900; color: #0f172a; }
  .logistics-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; margin-bottom: 40px; }
  .info-card { background: #f8fafc; padding: 25px; border-radius: 20px; border: 1px solid #f1f5f9; }
  .card-label { font-size: 9px; font-weight: 900; text-transform: uppercase; color: #94a3b8; letter-spacing: 2px; margin-bottom: 12px; display: block; border-bottom: 1px solid #e2e8f0; padding-bottom: 8px; }
  .name { font-size: 22px; font-weight: 900; color: #0f172a; margin-bottom: 4px; }
  .company { font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 12px; }
  .addr { font-size: 15px; font-weight: 600; color: #334155; line-height: 1.3; }
  .city { font-size: 16px; font-weight: 900; text-transform: uppercase; color: #0f172a; margin-top: 5px; }
  .contact { font-size: 13px; font-weight: 700; color: #0f172a; margin-top: 10px; display: flex; gap: 10px; align-items: center; }
  .order-box { border: 2px solid #0f172a; border-radius: 20px; padding: 30px; margin-bottom: 40px; }
  .order-ref { font-size: 12px; font-weight: 900; color: #94a3b8; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 5px; }
  .order-title { font-size: 28px; font-weight: 900; color: #0f172a; letter-spacing: -1px; }
  .ref-tag { background: #f1f5f9; padding: 15px; border-radius: 12px; margin-top: 15px; font-size: 13px; color: #475569; font-weight: 600; border-left: 5px solid #0f172a; }
  .footer-sig { margin-top: auto; display: grid; grid-template-columns: 1fr 1fr; gap: 60px; padding-top: 40px; }
  .sig-line { border-top: 2px solid #0f172a; padding-top: 10px; text-align: center; font-size: 10px; font-weight: 900; text-transform: uppercase; color: #64748b; letter-spacing: 1px; }
  .stamp { position: absolute; bottom: 40px; right: 40px; font-size: 8px; color: #cbd5e1; text-transform: uppercase; transform: rotate(-90deg); transform-origin: bottom right; }
  @media print { body { -webkit-print-color-adjust: exact; } .page-border { border: none; padding: 0; } }
</style></head><body>
<div class="page-border">
  <div class="header">
    <div class="brand">
      <h1>${company.name || 'N3XT 3D'}</h1>
      <p>Manufactura Digital Avanzada</p>
    </div>
    <div class="doc-type">
      <div class="doc-badge">Remisión de Entrega</div>
      <div class="doc-num">ORDEN #${order.id}</div>
    </div>
  </div>
  <div class="logistics-grid">
    <div class="info-card">
      <span class="card-label">Remitente (Origen)</span>
      <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 15px;">
        ${companyLogo ? `<img src="${companyLogo}" style="height: 45px; max-width: 180px; width: auto; object-fit: contain;">` : ''}
        <div class="name" style="font-size: 18px;">${company.name || 'N3XT 3D'}</div>
      </div>
      ${company.nit ? `<div class="company">NIT: ${company.nit}</div>` : ''}
      <div class="addr">${company.address || ''}</div>
      ${company.phone ? `<div class="contact">TEL: ${company.phone}</div>` : ''}
      ${company.email ? `<div class="contact" style="font-size: 11px;">EMAIL: ${company.email}</div>` : ''}
    </div>
    <div class="info-card">
      <span class="card-label">Destinatario (Entrega)</span>
      <div class="name">${order.customer_name}</div>
      ${order.customer_id_document ? `<div class="company" style="font-size: 13px; color: #000;">ID: ${order.customer_id_document}</div>` : ''}
      <div class="company">${order.customer_company || 'Cliente Registrado'}</div>
      <div class="addr">${order.shipping_address || 'Dirección por confirmar'}</div>
      <div class="city">${order.shipping_city || 'Ciudad / Depto'} ${order.shipping_zip ? `(${order.shipping_zip})` : ''}</div>
      <div class="contact">WhatsApp: ${order.customer_phone}</div>
    </div>
  </div>
  <div class="order-box">
    <div class="order-ref">Detalles del Despacho</div>
    <div class="order-title">${order.job_name || 'Fabricación 3D Bajo Demanda'}</div>
    ${order.shipping_reference ? `<div class="ref-tag"><strong>Indicaciones de Entrega:</strong><br>${order.shipping_reference}</div>` : ''}
  </div>
  <div class="footer-sig">
    <div class="sig-line">Entregado por (${company.name || 'N3XT 3D'})</div>
    <div class="sig-line">Recibido a Conformidad (Cliente)</div>
  </div>
  <div class="stamp">Generado por N3XT OS v3.2 • ${new Date().toLocaleDateString()} • Auditoría Digital</div>
</div></body></html>`
}

// =============================================================================
// 3. QUOTE PDF (Propuesta Técnica / Cotización)
// =============================================================================
export function quotePDFHTML(order: OrderData, material: any, settings: SettingsData): string {
  const company = settings.company || {}
  const trackingUrl = `${window.location.origin}/#/track?order_id=${order.id}&email=${order.customer_email || ''}`
  const qrDataUrl = `https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=${encodeURIComponent(trackingUrl)}`

  return `<!DOCTYPE html>
<html><head><meta charset="utf-8">
<style>
  @import url('https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;900&display=swap');
  @page { size: A4; margin: 8mm; }
  body { font-family: 'Outfit', sans-serif; padding: 0; color: #0f172a; line-height: 1.25; font-size: 13px; background: white; }
  .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 5px solid #0f172a; padding-bottom: 15px; margin-bottom: 20px; }
  .company-brand { display: flex; align-items: center; gap: 20px; }
  .company-info-mini { border-left: 5px solid #0f172a; padding-left: 20px; }
  .quote-badge { background: #0f172a; color: white; padding: 10px 25px; border-radius: 18px; text-align: right; }
  .badge-label { font-size: 9px; font-weight: 900; text-transform: uppercase; letter-spacing: 2px; opacity: 0.8; margin-bottom: 2px; }
  .badge-val { font-size: 22px; font-weight: 900; }
  .grid { display: grid; grid-template-columns: 1fr; gap: 15px; margin-bottom: 20px; }
  .info-box { background: #f8fafc; padding: 20px; border-radius: 20px; border: 1.5px solid #f1f5f9; }
  .info-label { font-size: 10px; font-weight: 900; text-transform: uppercase; color: #94a3b8; margin-bottom: 5px; display: block; letter-spacing: 1px; }
  .info-value { font-weight: 900; font-size: 18px; color: #0f172a; }
  .table { width: 100%; border-collapse: separate; border-spacing: 0; margin-bottom: 20px; }
  .table th { background: #f8fafc; padding: 12px 15px; text-align: left; font-size: 10px; font-weight: 900; text-transform: uppercase; color: #64748b; letter-spacing: 1.5px; border-bottom: 3px solid #0f172a; }
  .table td { padding: 15px; border-bottom: 1.5px solid #f1f5f9; font-size: 14px; font-weight: 700; }
  .rec-container { display: grid; grid-template-columns: 1.2fr 0.8fr; gap: 15px; margin-bottom: 20px; }
  .rec-box { background: #f0fdf4; border: 1.5px solid #bbf7d0; padding: 15px; border-radius: 20px; }
  .rec-title { font-size: 12px; font-weight: 900; color: #166534; text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 10px; display: flex; align-items: center; gap: 8px; }
  .rec-list { margin: 0; padding-left: 20px; font-size: 11px; color: #166534; font-weight: 600; line-height: 1.6; }
  .cert-box { background: #eff6ff; border: 1.5px solid #bfdbfe; padding: 15px; border-radius: 20px; display: flex; flex-direction: column; justify-content: center; }
  .cert-title { font-size: 12px; font-weight: 900; color: #1e40af; text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 10px; }
  .cert-text { font-size: 11px; color: #1e40af; font-weight: 600; margin: 0; line-height: 1.4; }
  .total-section { display: flex; justify-content: flex-end; padding-top: 5px; }
  .total-box { text-align: right; background: #0f172a; color: white; padding: 25px 40px; border-radius: 25px; border: 2px solid #10b981; }
  .total-label { font-size: 12px; font-weight: 900; text-transform: uppercase; letter-spacing: 2px; color: #10b981; margin-bottom: 5px; }
  .total-val-main { font-size: 42px; font-weight: 900; letter-spacing: -2px; }
  .footer { font-size: 10px; color: #94a3b8; text-align: center; margin-top: 30px; border-top: 1.5px solid #f1f5f9; padding-top: 20px; font-weight: 600; }
  @media print { body { -webkit-print-color-adjust: exact; } .total-box { -webkit-print-color-adjust: exact; background-color: #0f172a !important; color: white !important; border-color: #10b981 !important; } .rec-box, .cert-box { -webkit-print-color-adjust: exact; } }
</style></head><body>
<div class="header">
  <div class="company-brand">
    <div class="company-info-mini">
      <div style="color: #0f172a; font-weight: 900; text-transform: uppercase; font-size: 24px; margin-bottom: 6px;">${company.name || 'N3XT 3D'}</div>
      <div style="font-size: 12px; font-weight: 800; color: #64748b; text-transform: uppercase;">
        ${company.nit ? `NIT: ${company.nit} | ` : ''} ${company.address || ''}<br>
        ${company.phone ? `TEL: ${company.phone} | ` : ''} ${company.email ? `EMAIL: ${company.email}` : ''}
      </div>
    </div>
  </div>
  <div class="quote-badge" style="display: flex; gap: 15px; align-items: center; padding: 10px 20px;">
    <div style="background: white; padding: 4px; border-radius: 12px; line-height: 0; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
      <img src="${qrDataUrl}" style="width: 75px; height: 75px;">
    </div>
    <div style="text-align: right;">
      <div class="badge-label">Propuesta Técnica</div>
      <div class="badge-val">#${order.id}</div>
      <div style="font-size: 6px; margin-top: 5px; opacity: 0.5; font-family: monospace;">TRACKING ENABLED</div>
    </div>
  </div>
</div>
<div class="grid">
  <div class="info-box">
    <span class="info-label">Proyecto / Cliente Destino</span>
    <div class="info-value">${order.customer_name} ${order.customer_company ? `(${order.customer_company})` : ''}</div>
    <div style="font-size: 14px; color: #475569; font-weight: 600; margin-top: 5px;">
      ${order.customer_id_document ? `ID: ${order.customer_id_document} | ` : ''} ${order.customer_phone ? `${order.customer_phone} | ` : ''} ${order.customer_email || ''}
    </div>
    ${order.shipping_address ? `<div style="font-size: 13px; color: #64748b; font-weight: 500; margin-top: 10px; padding-top: 10px; border-top: 1px solid #e2e8f0;"><strong>Envío:</strong> ${order.shipping_address}, ${order.shipping_city} ${order.shipping_zip ? `(ZIP: ${order.shipping_zip})` : ''}${order.shipping_reference ? `<br><small>Ref: ${order.shipping_reference}</small>` : ''}</div>` : ''}
  </div>
</div>
<table class="table">
  <thead><tr>
    <th>Descripción Industrial</th>
    <th style="width: 150px;">Proceso</th>
    <th style="width: 200px;">Material</th>
    <th style="text-align: right; width: 180px;">Inversión</th>
  </tr></thead>
  <tbody>
    <tr>
      <td style="font-weight: 800; color: #0f172a;">${order.job_name || 'Fabricación Digital Bajo Demanda'}<br><span style="font-size: 13px; color: #64748b; font-weight: 500;">Producción 3D Especializada - Prototipado / Final</span></td>
      <td>${order.technology}</td>
      <td>${material ? material.name : (order.material_name || order.material_id)}</td>
      <td style="text-align: right; font-weight: 900; color: #0f172a;">$${Number(order.total_price - (order.extras_cost || 0)).toLocaleString(undefined, {maximumFractionDigits: 0})}</td>
    </tr>
    ${(order.extra_items || []).map(extra => `
    <tr>
      <td>${extra.name} (Adicional / Empaque)</td>
      <td>N/A</td>
      <td>${extra.qty} x Und</td>
      <td style="text-align: right; font-weight: 900; color: #0f172a;">$${Number(extra.cost * extra.qty).toLocaleString(undefined, {maximumFractionDigits: 0})}</td>
    </tr>`).join('')}
  </tbody>
</table>
<div class="rec-container">
  <div class="rec-box">
    <div class="rec-title">Recomendaciones de Fabricacion</div>
    <ul class="rec-list">
      <li><strong>Estructura:</strong> Optimización de perímetros para mayor solidez.</li>
      <li><strong>Acabado:</strong> Altura de capa industrial de alta definición.</li>
      <li><strong>Post-Proceso:</strong> ${order.technology === 'SLA' ? 'Curado UV intensivo para estabilidad química.' : 'Tratamiento térmico para alivio de tensiones.'}</li>
    </ul>
  </div>
  <div class="cert-box">
    <div class="cert-title">Sello de Calidad N3XT</div>
    <p class="cert-text">Este documento certifica que el proyecto ha sido analizado bajo los estándares de precisión N3XT. Garantía de estabilidad dimensional y resistencia estructural según ficha técnica del material.</p>
  </div>
</div>
<div class="total-section">
  <div class="total-box">
    <div class="total-label">Inversión Total del Proyecto</div>
    <div class="total-val-main">$${Number(order.total_price).toLocaleString(undefined, {maximumFractionDigits: 0})}</div>
    <div style="font-size: 11px; margin-top: 10px; opacity: 0.9; font-weight: 700; color: #10b981; text-transform: uppercase;">IVA INCLUIDO (${settings.margin?.iva || 0}%) • VALIDEZ: 15 DÍAS • ANTICIPO: 50%</div>
  </div>
</div>
<div class="footer">
  <p style="margin-bottom: 5px; font-weight: 800;">${company.name || 'N3XT 3D Administrative System'} • Soluciones de Manufactura Aditiva de Alta Precisión</p>
  <p>© 2026 ${company.name || 'N3XT 3D SYSTEMS'} - Tecnología Digital Avanzada</p>
  <p>Email: ${company.email || 'ventas@n3xt.com'} • ${company.address || ''}</p>
  <p style="font-size: 8px; opacity: 0.5; margin-top: 10px;">Generado el ${new Date().toLocaleString(undefined, {maximumFractionDigits: 0})} • Copia Digital Autenticada</p>
</div></body></html>`
}

// =============================================================================
// 4. EXECUTIVE METRICS REPORT
// =============================================================================
export function metricsReportHTML(reportData: Record<string, any>, settings: SettingsData, companyLogo?: string): string {
  const company = settings.company || {}

  return `<!DOCTYPE html>
<html><head><meta charset="utf-8">
<style>
  @import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700;900&display=swap');
  @page { size: A4; margin: 10mm; }
  body { font-family: 'Outfit', sans-serif; padding: 0; margin: 0; color: #0f172a; background: #fff; line-height: 1.2; font-size: 11px; }
  .page-wrapper { padding: 30px; }
  .header-bar { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #f1f5f9; padding-bottom: 25px; margin-bottom: 30px; }
  .brand-box { display: flex; align-items: center; gap: 15px; }
  .logo-img { height: 55px; max-width: 200px; width: auto; object-fit: contain; }
  .brand-info h1 { font-size: 26px; font-weight: 900; letter-spacing: -1.5px; margin: 0; text-transform: uppercase; color: #0f172a; }
  .brand-info p { font-size: 8px; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 3px; margin: 4px 0 0 0; }
  .audit-info { text-align: right; }
  .status-badge { display: inline-block; background: #1e3a34; color: white; padding: 6px 12px; border-radius: 8px; font-size: 9px; font-weight: 900; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 10px; }
  .period-label { font-size: 8px; font-weight: 800; color: #94a3b8; text-transform: uppercase; margin-bottom: 2px; }
  .period-val { font-size: 11px; font-weight: 900; color: #1e293b; }
  .hero-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 30px; }
  .card { padding: 20px; border-radius: 24px; border: 1px solid #f1f5f9; background: #f8fafc; }
  .card.dark { background: #0f172a; color: white; border: none; }
  .card.emerald { background: #1e3a34; color: white; border: none; }
  .card-label { font-size: 7px; font-weight: 900; text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 8px; opacity: 0.7; }
  .card-val { font-size: 24px; font-weight: 900; letter-spacing: -0.5px; }
  .card-sub { font-size: 8px; font-weight: 700; margin-top: 5px; opacity: 0.6; }
  .content-grid { display: grid; grid-template-columns: 1fr 1.6fr; gap: 30px; }
  .section { margin-bottom: 25px; }
  .section-header { display: flex; align-items: center; gap: 8px; margin-bottom: 15px; border-bottom: 1px solid #f1f5f9; padding-bottom: 8px; }
  .dot { width: 6px; height: 6px; border-radius: 50%; background: #1e3a34; }
  .section-title { font-size: 9px; font-weight: 900; text-transform: uppercase; letter-spacing: 2px; color: #0f172a; }
  .data-item { display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid #f8fafc; }
  .data-item:last-child { border: none; }
  .item-label { font-weight: 600; color: #64748b; font-size: 10px; }
  .item-val { font-weight: 900; color: #0f172a; font-size: 11px; }
  .item-val.neg { color: #ef4444; }
  .sub-item { padding-left: 15px; opacity: 0.8; font-size: 9px; padding-top: 5px; padding-bottom: 5px; }
  .sub-item .item-label { font-weight: 400; font-size: 9px; }
  .audit-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
  .audit-table th { text-align: left; background: #f1f5f9; padding: 12px; font-weight: 900; color: #64748b; text-transform: uppercase; font-size: 7px; letter-spacing: 1px; }
  .audit-table td { padding: 12px; border-bottom: 1px solid #f1f5f9; }
  .audit-table tr:last-child td { border: none; }
  .footer { margin-top: 40px; padding-top: 20px; border-top: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: flex-end; }
  .sys-stamp { font-size: 7px; font-weight: 800; color: #94a3b8; line-height: 1.5; }
  .sign-box { width: 180px; text-align: center; }
  .sign-line { border-top: 1.5px solid #0f172a; margin-top: 15px; padding-top: 5px; font-weight: 900; font-size: 8px; text-transform: uppercase; }
  @media print { body { -webkit-print-color-adjust: exact; } .card.dark { background-color: #0f172a !important; color: white !important; } .card.emerald { background-color: #1e3a34 !important; color: white !important; } .status-badge { background-color: #1e3a34 !important; color: white !important; } }
</style></head><body>
<div class="page-wrapper">
  <div class="header-bar">
    <div class="brand-box">
      ${companyLogo ? `<img src="${companyLogo}" class="logo-img">` : ''}
      <div class="brand-info">
        <h1>${company.name || 'N3XT 3D'}</h1>
        <p>${company.nit ? `NIT: ${company.nit} | ` : ''} ${company.address || ''}<br>${company.phone ? `TEL: ${company.phone} | ` : ''} ${company.email || ''}</p>
      </div>
    </div>
    <div class="audit-info">
      <div class="status-badge">Reporte de Auditoría Verificado</div>
      <div class="period-label">Intervalo de Análisis</div>
      <div class="period-val">${reportData.start_date || 'ORIGEN'} AL ${reportData.end_date || 'HOY'}</div>
    </div>
  </div>
  <div class="hero-grid">
    <div class="card"><div class="card-label">Ingresos Brutos</div><div class="card-val">$${Number(reportData.summary.total_revenue).toLocaleString(undefined, {maximumFractionDigits: 0})}</div><div class="card-sub">${reportData.summary.orders_count} órdenes procesadas</div></div>
    <div class="card"><div class="card-label">Gastos de Operación</div><div class="card-val" style="color: #ef4444;">$${Number(reportData.summary.total_expenses).toLocaleString(undefined, {maximumFractionDigits: 0})}</div><div class="card-sub">Costo de producción base</div></div>
    <div class="card emerald"><div class="card-label">Utilidad Neta Real</div><div class="card-val">$${Number(reportData.summary.net_profit).toLocaleString(undefined, {maximumFractionDigits: 0})}</div><div class="card-sub" style="color: #4ade80;">Flujo de caja positivo</div></div>
    <div class="card"><div class="card-label">Margen de Beneficio</div><div class="card-val">${reportData.summary.profit_margin_pct}%</div><div class="card-sub">Retorno sobre inversión</div></div>
  </div>
  <div class="content-grid">
    <div class="left-col">
      <div class="section"><div class="section-header"><div class="dot"></div><div class="section-title">Actividad de Taller</div></div>
        <div class="data-item"><span class="item-label">Horas Máquina</span><span class="item-val">${reportData.summary.total_hours} H</span></div>
        <div class="data-item"><span class="item-label">Masa Total Procesada</span><span class="item-val">${reportData.summary.total_weight_kg} KG</span></div>
        <div class="data-item"><span class="item-label">Tasa de Entrega</span><span class="item-val">${reportData.summary.completed_count} / ${reportData.summary.orders_count}</span></div>
      </div>
      <div class="section"><div class="section-header"><div class="dot" style="background: #ef4444;"></div><div class="section-title">Control de Pérdidas</div></div>
        <div class="data-item"><span class="item-label">Costo por Fallos</span><span class="item-val neg">$${Number(reportData.summary.waste_cost).toLocaleString(undefined, {maximumFractionDigits: 0})}</span></div>
        <div class="data-item"><span class="item-label">Material Desperdiciado</span><span class="item-val">${reportData.summary.waste_weight_g} g</span></div>
      </div>
      <div class="section"><div class="section-header"><div class="dot" style="background: #64748b;"></div><div class="section-title">Mix Tecnológico</div></div>
        ${(reportData.by_technology || []).map(tech => `<div class="data-item"><span class="item-label">${tech.technology === 'FDM' ? 'Filamento (FDM)' : 'Resina (SLA)'}</span><span class="item-val">${tech.count} trabajos</span></div>`).join('')}
      </div>
    </div>
    <div class="right-col">
      <div class="section"><div class="section-header"><div class="dot"></div><div class="section-title">Estado de Gastos Detallado</div></div>
        <div class="data-item"><span class="item-label">Materia Prima e Insumos</span><span class="item-val">$${Number(reportData.summary.total_material_cost).toLocaleString(undefined, {maximumFractionDigits: 0})}</span></div>
        <div class="data-item sub-item"><span class="item-label">Consumo FDM</span><span class="item-val">$${Number(reportData.summary.breakdown?.mat_fdm || 0).toLocaleString(undefined, {maximumFractionDigits: 0})}</span></div>
        <div class="data-item sub-item"><span class="item-label">Consumo SLA</span><span class="item-val">$${Number(reportData.summary.breakdown?.mat_sla || 0).toLocaleString(undefined, {maximumFractionDigits: 0})}</span></div>
        <div class="data-item"><span class="item-label">Mano de Obra Especializada</span><span class="item-val">$${Number(reportData.summary.breakdown?.labor || 0).toLocaleString(undefined, {maximumFractionDigits: 0})}</span></div>
        <div class="data-item"><span class="item-label">Costos de Energía y Luz</span><span class="item-val">$${Number(reportData.summary.breakdown?.luz || 0).toLocaleString(undefined, {maximumFractionDigits: 0})}</span></div>
        <div class="data-item"><span class="item-label">Plan de Mantenimiento</span><span class="item-val">$${Number(reportData.summary.breakdown?.mant || 0).toLocaleString(undefined, {maximumFractionDigits: 0})}</span></div>
        <div class="data-item"><span class="item-label">Depreciación Activos</span><span class="item-val">$${Number(reportData.summary.breakdown?.depr || 0).toLocaleString(undefined, {maximumFractionDigits: 0})}</span></div>
        <div class="data-item"><span class="item-label">Logística y Extras</span><span class="item-val">$${Number(reportData.summary.total_extras_cost).toLocaleString(undefined, {maximumFractionDigits: 0})}</span></div>
      </div>
      <div class="section"><div class="section-header"><div class="dot"></div><div class="section-title">Contribuyentes Principales (LTV)</div></div>
        <table class="audit-table"><thead><tr><th>Identificación Cliente</th><th style="text-align: right">Contribución Total</th></tr></thead>
        <tbody>${(reportData.top_customers || []).slice(0, 5).map(c => `<tr><td style="font-weight: 700;">${c.customer_name} <span style="font-size: 7px; color: #94a3b8; display: block;">${c.orders_count} órdenes registradas</span></td><td style="text-align: right; font-weight: 900; color: #1e3a34; font-size: 12px;">$${Number(c.total_spent).toLocaleString(undefined, {maximumFractionDigits: 0})}</td></tr>`).join('')}</tbody>
        </table>
      </div>
    </div>
  </div>
  <div class="footer">
    <div class="sys-stamp">SISTEMA DE GESTIÓN N3XT CORE | VERSIÓN 3.2.4<br>ID ÚNICO DE AUDITORÍA: ${Math.random().toString(36).substr(2, 9).toUpperCase()}<br>EMISIÓN: ${new Date().toLocaleString(undefined, {maximumFractionDigits: 0})}</div>
    <div class="sign-box"><div style="font-size: 7px; color: #94a3b8; font-weight: 700; margin-bottom: 20px;">VERIFICACIÓN DIGITAL REQUERIDA</div><div class="sign-line">Firma Autorizada Auditoría</div><div style="font-size: 8px; font-weight: 600; margin-top: 4px;">${company.name}</div></div>
  </div>
</div></body></html>`
}

// =============================================================================
// 5. LEDGER / SALES BOOK
// =============================================================================
export function ledgerReportHTML(filteredOrders: OrderData[], settings: SettingsData, companyLogo?: string): string {
  const company = settings.company || {}
  const totalSales = filteredOrders.reduce((acc, o) => acc + parseFloat(o.total_price || 0), 0)

  return `<!DOCTYPE html>
<html><head><meta charset="utf-8">
<style>
  @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap');
  body { font-family: 'Inter', sans-serif; padding: 40px; color: #1e293b; background: #fff; }
  .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #f1f5f9; padding-bottom: 25px; margin-bottom: 35px; }
  .header-left { display: flex; align-items: center; }
  .logo-img { height: 50px; width: auto; object-fit: contain; margin-right: 15px; }
  .logo { font-size: 22px; font-weight: 900; letter-spacing: -1px; text-transform: uppercase; color: #0f172a; }
  .logo span { color: #94a3b8; }
  .doc-title { font-size: 10px; font-weight: 900; color: #64748b; text-transform: uppercase; letter-spacing: 4px; margin-bottom: 8px; }
  table { width: 100%; border-collapse: collapse; font-size: 10px; }
  th { text-align: left; background: #f8fafc; padding: 15px 12px; text-transform: uppercase; font-weight: 900; color: #64748b; border-bottom: 2px solid #e2e8f0; letter-spacing: 1px; }
  td { padding: 15px 12px; border-bottom: 1px solid #f1f5f9; font-weight: 600; color: #334155; }
  tr:nth-child(even) { background: #fcfdfe; }
  .id-cell { font-family: monospace; font-size: 11px; color: #94a3b8; font-weight: 900; }
  .price-cell { font-weight: 900; text-align: right; color: #0f172a; font-size: 11px; }
  .tech-badge { background: #f1f5f9; padding: 4px 8px; border-radius: 6px; font-size: 8px; font-weight: 900; text-transform: uppercase; }
  .summary-row { background: #0f172a !important; color: white !important; }
  .summary-row td { border: none; padding: 20px 12px; }
  .footer { margin-top: 50px; font-size: 9px; color: #94a3b8; font-weight: 700; display: flex; justify-content: space-between; }
</style></head><body>
<div class="header"><div class="header-left">${companyLogo ? `<img src="${companyLogo}" class="logo-img">` : ''}<div><div class="doc-title">Reporte de Libro Mayor</div><div class="logo">N3XT<span> 3D</span></div></div></div><div style="text-align: right"><div style="font-weight: 900; font-size: 14px;">${filteredOrders.length} ÓRDENES</div><div style="font-weight: 600; font-size: 10px; color: #94a3b8;">FILTRO: TODOS / TODOS</div><div style="font-size: 7px; color: #10b981; font-weight: 900; margin-top: 5px; text-transform: uppercase;">Auditoria Financiera Validada</div></div></div>
<table><thead><tr><th>ID</th><th>Fecha</th><th>Cliente</th><th>Proyecto / Trabajo</th><th>Tec</th><th>Masa</th><th style="text-align: right">Monto Total</th></tr></thead>
<tbody>${filteredOrders.map(o => `<tr><td class="id-cell">#${String(o.id).padStart(5, '0')}</td><td>${new Date(o.created_at).toLocaleDateString()}</td><td style="color: #0f172a; font-weight: 700;">${o.customer_name}</td><td style="color: #64748b;">${o.job_name || 'Servicio de Impresión'}</td><td><span class="tech-badge">${o.technology}</span></td><td>${o.estimated_weight_g}g</td><td class="price-cell">$${Number(o.total_price).toLocaleString(undefined, {maximumFractionDigits: 0})}</td></tr>`).join('')}</tbody>
<tr class="summary-row"><td colspan="6" style="text-align: right; font-weight: 900; text-transform: uppercase; letter-spacing: 2px; font-size: 10px;">Venta Total del Periodo</td><td style="text-align: right; font-size: 18px; font-weight: 900;">$${totalSales.toLocaleString(undefined, {maximumFractionDigits: 0})}</td></tr>
</table>
<div class="footer"><div>EMITIDO POR SISTEMA N3XT | ID: ${Math.random().toString(36).substr(2, 9).toUpperCase()}</div><div>${company.name} | NIT: ${company.nit} | PÁGINA 1 DE 1</div></div>
</body></html>`
}
