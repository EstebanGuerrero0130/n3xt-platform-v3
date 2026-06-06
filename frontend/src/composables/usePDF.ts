/**
 * N3XT PDF Generation Composable
 * Genera PDFs de cotizaciones, gu\u00edas de env\u00edo y reportes ejecutivos
 */
import { type Ref } from 'vue'
import { api } from '../services/api'

interface UsePDFOptions {
  settings: Ref<Record<string, any>>
  inventoryData: Ref<Array<Record<string, any>>>
  showNotify?: (msg: string, type?: string) => void
}

function getIframe(): HTMLIFrameElement {
  let iframe = document.getElementById('pdf-print-frame') as HTMLIFrameElement | null
  if (!iframe) {
    iframe = document.createElement('iframe')
    iframe.id = 'pdf-print-frame'
    iframe.style.position = 'absolute'
    iframe.style.top = '-9999px'
    iframe.style.left = '-9999px'
    iframe.style.visibility = 'hidden'
    document.body.appendChild(iframe)
  }
  return iframe
}

function printIframe(iframe: HTMLIFrameElement, delay = 250) {
  const printWindow = iframe.contentWindow || iframe.contentDocument?.defaultView
  if (!printWindow) return
  const imgs = printWindow.document.images
  let loaded = 0
  const print = () => { printWindow.focus(); printWindow.print() }
  if (imgs.length === 0) { setTimeout(print, delay) } else {
    let printed = false
    const onload = () => { if (++loaded === imgs.length && !printed) { printed = true; setTimeout(print, delay) } }
    for (let i = 0; i < imgs.length; i++) {
      if (imgs[i].complete) onload()
      else { imgs[i].onload = onload; imgs[i].onerror = onload }
    }
    setTimeout(() => { if (!printed) { printed = true; print() } }, 3000)
  }
}

function getAbsoluteUrl(path: string): string {
  return path.startsWith('http') ? path : window.location.origin + (path.startsWith('/') ? '' : '/') + path
}

export function usePDF({ settings, inventoryData, showNotify: _showNotify }: UsePDFOptions) {
  const companyLogo = (): string => {
    if (!settings.value?.company_logo) return window.location.origin + '/logo.png'
    if (settings.value.company_logo.startsWith('http')) return settings.value.company_logo
    return getAbsoluteUrl(`${(api as any).storageUrl || '/storage'}/${settings.value.company_logo}`)
  }

  const handleDownloadQuotePDF = (order: any) => {
    const mat = inventoryData.value.find((m: any) => String(m.id) === String(order.material_id))
    const logo = companyLogo()
    const trackingUrl = `${window.location.origin}/#/track?order_id=${order.id}&email=${order.customer_email || ''}`
    const qrDataUrl = `https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=${encodeURIComponent(trackingUrl)}`
    const cName = settings.value.company?.name || 'N3XT 3D'
    const cNit = settings.value.company?.nit || ''
    const cAddr = settings.value.company?.address || ''
    const cPhone = settings.value.company?.phone || ''
    const cEmail = settings.value.company?.email || ''

    const content = `<!DOCTYPE html>
<html><head><title>N3XT 3D - Cotizaci\u00f3n #${order.id}</title>
<style>
@import url('https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;900&display=swap');
@page { size: A4; margin: 8mm; }
body { font-family: 'Outfit', sans-serif; padding: 0; color: #0f172a; line-height: 1.25; font-size: 13px; background: white; }
.header { display: flex; justify-content: space-between; align-items: center; border-bottom: 5px solid #0f172a; padding-bottom: 15px; margin-bottom: 20px; }
.company-brand { display: flex; align-items: center; gap: 20px; }
.company-logo { height: 70px; max-width: 250px; width: auto; object-fit: contain; }
.company-info { font-size: 11px; color: #334155; font-weight: 600; line-height: 1.4; border-left: 3px solid #10b981; padding-left: 15px; }
.company-name { color: #0f172a; font-weight: 900; text-transform: uppercase; font-size: 24px; margin-bottom: 6px; }
.quote-badge { background: #0f172a; color: white; padding: 10px 25px; border-radius: 18px; text-align: right; display: flex; gap: 15px; align-items: center; }
.badge-label { font-size: 9px; font-weight: 900; text-transform: uppercase; letter-spacing: 2px; opacity: 0.8; margin-bottom: 2px; }
.badge-val { font-size: 22px; font-weight: 900; }
.info-box { background: #f8fafc; padding: 20px; border-radius: 20px; border: 1.5px solid #f1f5f9; margin-bottom: 20px; }
.info-label { font-size: 10px; font-weight: 900; text-transform: uppercase; color: #94a3b8; margin-bottom: 5px; display: block; letter-spacing: 1px; }
.info-value { font-weight: 900; font-size: 18px; color: #0f172a; }
.table { width: 100%; border-collapse: separate; border-spacing: 0; margin-bottom: 20px; }
.table th { background: #f8fafc; padding: 12px 15px; text-align: left; font-size: 10px; font-weight: 900; text-transform: uppercase; color: #64748b; letter-spacing: 1.5px; border-bottom: 3px solid #0f172a; }
.table td { padding: 15px; border-bottom: 1.5px solid #f1f5f9; font-size: 14px; font-weight: 700; }
.rec-container { display: grid; grid-template-columns: 1.2fr 0.8fr; gap: 15px; margin-bottom: 20px; }
.rec-box { background: #f0fdf4; border: 1.5px solid #bbf7d0; padding: 15px; border-radius: 20px; }
.rec-title { font-size: 12px; font-weight: 900; color: #166534; text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 10px; }
.cert-box { background: #eff6ff; border: 1.5px solid #bfdbfe; padding: 15px; border-radius: 20px; }
.cert-title { font-size: 12px; font-weight: 900; color: #1e40af; text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 10px; }
.total-box { text-align: right; background: #0f172a; color: white; padding: 25px 40px; border-radius: 25px; border: 2px solid #10b981; }
.total-label { font-size: 12px; font-weight: 900; text-transform: uppercase; letter-spacing: 2px; color: #10b981; margin-bottom: 5px; }
.total-val { font-size: 42px; font-weight: 900; letter-spacing: -2px; }
.footer { font-size: 10px; color: #94a3b8; text-align: center; margin-top: 30px; border-top: 1.5px solid #f1f5f9; padding-top: 20px; font-weight: 600; }
@media print { body { -webkit-print-color-adjust: exact; } .total-box { background-color: #0f172a !important; color: white !important; } .rec-box, .cert-box { -webkit-print-color-adjust: exact; } }
</style></head><body>
<div class="header">
  <div class="company-brand">${logo ? `<img src="${logo}" class="company-logo">` : ''}
    <div class="company-info">
      <div class="company-name">${cName}</div>
      <div>${cNit ? `NIT: ${cNit} | ` : ''}${cAddr}<br>${cPhone ? `TEL: ${cPhone} | ` : ''}${cEmail}</div>
    </div>
  </div>
  <div class="quote-badge"><div style="background:white;padding:4px;border-radius:12px;"><img src="${qrDataUrl}" style="width:75px;height:75px;"></div>
    <div><div class="badge-label">Propuesta T\u00e9cnica</div><div class="badge-val">#${order.id}</div></div>
  </div>
</div>
<div class="info-box">
  <span class="info-label">Proyecto / Cliente Destino</span>
  <div class="info-value">${order.customer_name}${order.customer_company ? ` (${order.customer_company})` : ''}</div>
  <div style="font-size:14px;color:#475569;font-weight:600;margin-top:5px;">
    ${order.customer_id_document ? `ID: ${order.customer_id_document} | ` : ''}${order.customer_phone ? `${order.customer_phone} | ` : ''}${order.customer_email || ''}
  </div>
</div>
<table class="table"><thead><tr><th>Descripci\u00f3n Industrial</th><th style="width:150px;">Proceso</th><th style="width:200px;">Material</th><th style="text-align:right;width:180px;">Inversi\u00f3n</th></tr></thead>
<tbody><tr><td style="font-weight:800;">${order.job_name || 'Fabricaci\u00f3n Digital Bajo Demanda'}<br><span style="font-size:13px;color:#64748b;font-weight:500;">Producci\u00f3n 3D Especializada</span></td>
<td>${order.technology}</td><td>${mat ? mat.name : (order.material_name || order.material_id)}</td>
<td style="text-align:right;font-weight:900;">$${Number(order.total_price - (order.extras_cost || 0)).toLocaleString()}</td></tr>
${(order.extra_items || []).map((extra: any) => `<tr><td>${extra.name} (Adicional)</td><td>N/A</td><td>${extra.qty} x Und</td><td style="text-align:right;font-weight:900;">$${Number(extra.cost * extra.qty).toLocaleString()}</td></tr>`).join('')}
</tbody></table>
<div class="rec-container"><div class="rec-box"><div class="rec-title">Recomendaciones</div>
<ul style="margin:0;padding-left:20px;font-size:11px;color:#166534;font-weight:600;line-height:1.6;">
<li><strong>Estructura:</strong> Optimizaci\u00f3n de per\u00edmetros para mayor solidez.</li>
<li><strong>Acabado:</strong> Altura de capa industrial de alta definici\u00f3n.</li>
<li><strong>Post-Proceso:</strong> ${order.technology === 'SLA' ? 'Curado UV intensivo para estabilidad qu\u00edmica.' : 'Tratamiento t\u00e9rmico para alivio de tensiones.'}</li>
</ul></div>
<div class="cert-box"><div class="cert-title">Sello de Calidad N3XT</div>
<p style="font-size:11px;color:#1e40af;font-weight:600;margin:0;line-height:1.4;">Este documento certifica que el proyecto ha sido analizado bajo los est\u00e1ndares de precisi\u00f3n N3XT.</p></div></div>
<div style="display:flex;justify-content:flex-end;"><div class="total-box">
<div class="total-label">Inversi\u00f3n Total del Proyecto</div>
<div class="total-val">$${Number(order.total_price).toLocaleString()}</div>
<div style="font-size:11px;margin-top:10px;opacity:0.9;font-weight:700;color:#10b981;text-transform:uppercase;">IVA INCLUIDO (${settings.value.margin?.iva || 0}%) \u2022 VALIDEZ: 15 D\u00cdAS</div></div></div>
<div class="footer"><p style="margin-bottom:5px;font-weight:800;">N3XT 3D Administrative System</p>
<p>\u00a9 2026 ${cName} - Tecnolog\u00eda Digital Avanzada</p>
<p>Email: ${cEmail} \u2022 ${cAddr}</p>
<p style="font-size:8px;opacity:0.5;margin-top:10px;">Generado el ${new Date().toLocaleString()}</p></div>
</body></html>`

    const iframe = getIframe()
    const printWindow = iframe.contentWindow || iframe.contentDocument?.defaultView
    if (!printWindow) return
    printWindow.document.open()
    printWindow.document.write(content)
    printWindow.document.close()
    printIframe(iframe)
  }

  const handleDownloadShippingLabel = (order: any) => {
    const logo = companyLogo()
    const content = `<!DOCTYPE html>
<html><head><title>Documento de Entrega - ${order.customer_name}</title>
<style>
@import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700;900&display=swap');
@page { size: A4; margin: 15mm; }
body { font-family: 'Outfit', sans-serif; padding: 0; color: #0f172a; background: #fff; line-height: 1.4; }
.page-border { border: 1px solid #e2e8f0; padding: 40px; height: calc(100vh - 120px); display: flex; flex-direction: column; position: relative; }
.header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 4px solid #0f172a; padding-bottom: 25px; margin-bottom: 35px; }
.brand h1 { font-size: 32px; font-weight: 900; letter-spacing: -2px; margin: 0; text-transform: uppercase; }
.brand p { font-size: 10px; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 3px; margin: 5px 0 0 0; }
.doc-badge { background: #0f172a; color: white; padding: 8px 15px; border-radius: 8px; font-size: 10px; font-weight: 900; text-transform: uppercase; display: inline-block; }
.logistics-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; margin-bottom: 40px; }
.info-card { background: #f8fafc; padding: 25px; border-radius: 20px; border: 1px solid #f1f5f9; }
.card-label { font-size: 9px; font-weight: 900; text-transform: uppercase; color: #94a3b8; letter-spacing: 2px; margin-bottom: 12px; display: block; border-bottom: 1px solid #e2e8f0; padding-bottom: 8px; }
.name { font-size: 22px; font-weight: 900; color: #0f172a; margin-bottom: 4px; }
.company-sub { font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 12px; }
.addr { font-size: 15px; font-weight: 600; color: #334155; line-height: 1.3; }
.city { font-size: 16px; font-weight: 900; text-transform: uppercase; color: #0f172a; margin-top: 5px; }
.contact { font-size: 13px; font-weight: 700; color: #0f172a; margin-top: 10px; }
.order-box { border: 2px solid #0f172a; border-radius: 20px; padding: 30px; margin-bottom: 40px; }
.footer-sig { margin-top: auto; display: grid; grid-template-columns: 1fr 1fr; gap: 60px; padding-top: 40px; }
.sig-line { border-top: 2px solid #0f172a; padding-top: 10px; text-align: center; font-size: 10px; font-weight: 900; text-transform: uppercase; color: #64748b; letter-spacing: 1px; }
.stamp { position: absolute; bottom: 40px; right: 40px; font-size: 8px; color: #cbd5e1; text-transform: uppercase; transform: rotate(-90deg); }
@media print { body { -webkit-print-color-adjust: exact; } }
</style></head><body>
<div class="page-border">
  <div class="header"><div class="brand"><h1>${settings.value.company?.name || 'N3XT 3D'}</h1><p>Manufactura Digital Avanzada</p></div>
    <div><div class="doc-badge">Remisi\u00f3n de Entrega</div><div style="font-size:20px;font-weight:900;">ORDEN #${order.id}</div></div></div>
  <div class="logistics-grid">
    <div class="info-card"><span class="card-label">Remitente (Origen)</span>
      ${logo ? `<div style="display:flex;align-items:center;gap:15px;margin-bottom:15px;"><img src="${logo}" style="height:45px;max-width:180px;object-fit:contain;"><div class="name" style="font-size:18px;">${settings.value.company?.name || 'N3XT 3D'}</div></div>` : ''}
      ${settings.value.company?.nit ? `<div class="company-sub">NIT: ${settings.value.company.nit}</div>` : ''}
      <div class="addr">${settings.value.company?.address || ''}</div>
      ${settings.value.company?.phone ? `<div class="contact">TEL: ${settings.value.company.phone}</div>` : ''}</div>
    <div class="info-card"><span class="card-label">Destinatario (Entrega)</span>
      <div class="name">${order.customer_name}</div>
      <div class="company-sub">${order.customer_company || 'Cliente Registrado'}</div>
      <div class="addr">${order.shipping_address || 'Direcci\u00f3n por confirmar'}</div>
      <div class="city">${order.shipping_city || ''} ${order.shipping_zip ? `(${order.shipping_zip})` : ''}</div>
      <div class="contact">WhatsApp: ${order.customer_phone}</div></div></div>
  <div class="order-box"><div style="font-size:12px;font-weight:900;color:#94a3b8;text-transform:uppercase;letter-spacing:2px;margin-bottom:5px;">Detalles del Despacho</div>
    <div style="font-size:28px;font-weight:900;letter-spacing:-1px;">${order.job_name || 'Fabricaci\u00f3n 3D Bajo Demanda'}</div></div>
  <div class="footer-sig"><div class="sig-line">Entregado por (N3XT 3D)</div><div class="sig-line">Recibido a Conformidad (Cliente)</div></div>
  <div class="stamp">Generado por N3XT OS v3.2 \u2022 ${new Date().toLocaleDateString()} \u2022 Auditor\u00eda Digital</div></div>
</body></html>`

    const iframe = getIframe()
    const printWindow = iframe.contentWindow || iframe.contentDocument?.defaultView
    if (!printWindow) return
    printWindow.document.open()
    printWindow.document.write(content)
    printWindow.document.close()
    printIframe(iframe)
  }

  const handlePrintLabel = (order: any) => {
    const content = `<!DOCTYPE html>
<html><head><style>
@import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;700;900&display=swap');
@page { size: 100mm 150mm; margin: 0; }
body { font-family: 'Outfit', sans-serif; padding: 25px; text-transform: uppercase; color: #000; margin: 0; background: white; }
.label-box { border: 10px solid #000; padding: 30px; height: 125mm; display: flex; flex-direction: column; position: relative; box-sizing: border-box; }
.header { border-bottom: 5px solid #000; padding-bottom: 15px; margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; }
.logo-text { font-size: 28px; font-weight: 900; letter-spacing: -1.5px; }
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
<div class="label-box"><div class="stamp">N3XT 3D SYSTEMS INDUSTRIAL LOGISTICS</div>
<div class="header"><div class="logo-text">N3XT SHIPMENT</div><div class="order-id">#${order.id}</div></div>
<div class="destinatario"><div class="label-text">DESTINATARIO:</div>
<div class="value-text">${order.customer_name}</div>
<div class="address-box"><div class="label-text">DIRECCI\u00d3N DE ENTREGA:</div>
<div class="address-text">${order.shipping_address || 'RECOGE EN TALLER'}</div>
<div class="city-text">${order.shipping_city || ''} ${order.shipping_zip || ''}</div>
<div class="city-text" style="margin-top:10px;">TEL: ${order.customer_phone || 'S/D'}</div></div></div>
<div class="footer"><div>ORIGEN: N3XT 3D SYSTEMS TALLER<br>CONTROL: ${new Date().toLocaleDateString()}<br>LOG: ${order.technology} / ${order.estimated_weight_g}G</div>
<div style="background:#000;padding:8px;border-radius:15px;"><img src="https://api.qrserver.com/v1/create-qr-code/?size=120x120&color=ffffff&bgcolor=000000&data=${encodeURIComponent(window.location.origin + '/#/track?order_id=' + order.id + '&email=' + (order.customer_email || ''))}" style="width:80px;height:80px;display:block;"></div></div></div>
</body></html>`

    const iframe = getIframe()
    const printWindow = iframe.contentWindow || iframe.contentDocument?.defaultView
    if (!printWindow) return
    printWindow.document.open()
    printWindow.document.write(content)
    printWindow.document.close()
    printIframe(iframe)
  }

  return {
    handleDownloadQuotePDF,
    handleDownloadShippingLabel,
    handlePrintLabel
  }
}
