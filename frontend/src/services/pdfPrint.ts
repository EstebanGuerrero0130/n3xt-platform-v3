/**
 * N3XT PDF Print Helper
 * Función reusable para imprimir/descargar documentos HTML en iframe.
 */
import logger from '../utils/logger'

let pdfFrame: HTMLIFrameElement | null = null

function getPdfFrame(): HTMLIFrameElement {
  if (!pdfFrame || !document.getElementById('pdf-print-frame')) {
    pdfFrame = document.createElement('iframe')
    pdfFrame.id = 'pdf-print-frame'
    pdfFrame.style.position = 'absolute'
    pdfFrame.style.top = '-9999px'
    pdfFrame.style.left = '-9999px'
    pdfFrame.style.visibility = 'hidden'
    document.body.appendChild(pdfFrame)
  }
  return pdfFrame
}

function waitForImagesAndPrint(printWindow: Window): void {
  const imgs: HTMLCollectionOf<HTMLImageElement> = printWindow.document.images
  let loaded = 0

  const doPrint = () => {
    printWindow.focus()
    printWindow.print()
  }

  if (imgs.length === 0) {
    setTimeout(doPrint, 500)
    return
  }

  let printed = false
  const onload = () => {
    if (++loaded === imgs.length && !printed) {
      printed = true
      setTimeout(doPrint, 500)
    }
  }

  for (let i = 0; i < imgs.length; i++) {
    if (imgs[i].complete) onload()
    else {
      imgs[i].onload = onload
      imgs[i].onerror = onload
    }
  }

  setTimeout(() => {
    if (!printed) { printed = true; doPrint() }
  }, 5000)
}

/**
 * Abre el contenido HTML en una nueva ventana para vista previa o descarga.
 */
export function openInNewWindow(htmlContent: string, title: string = 'N3XT 3D - Documento'): void {
  const newWindow = window.open('', '_blank')
  if (!newWindow) {
    logger.warn('Popup bloqueado. Usando printHtml como fallback.')
    printHtml(htmlContent)
    return
  }
  newWindow.document.write(htmlContent)
  newWindow.document.close()
  newWindow.document.title = title
}

/**
 * Print HTML content using hidden iframe
 */
export function printHtml(htmlContent: string): void {
  const iframe = getPdfFrame()
  const printWindow = iframe.contentWindow || iframe.contentDocument.defaultView
  printWindow.document.open()
  printWindow.document.write(htmlContent)
  printWindow.document.close()

  // Limpiar iframe DESPUÉS de que se cierre el diálogo de impresión
  const cleanup = () => {
    if (pdfFrame && pdfFrame.parentNode) {
      pdfFrame.parentNode.removeChild(pdfFrame)
      pdfFrame = null
    }
  }

  // afterprint: evento estándar que se dispara cuando se cierra el diálogo
  printWindow.addEventListener('afterprint', cleanup, { once: true })
  // Fallback: limpiar después de 10s por si afterprint no funciona
  setTimeout(() => {
    if (pdfFrame) cleanup()
  }, 10000)

  waitForImagesAndPrint(printWindow)
}

interface CompanyInfo {
  name: string; nit: string; email: string; phone: string; address: string
}

/**
 * Get company info for PDF templates (without logo image)
 */
export function getCompanyInfo(settings: Record<string, any>): CompanyInfo {
  const company = settings?.company || { name: 'N3XT 3D TECHNOLOGY', nit: '', email: '', phone: '', address: '' }
  return {
    name: company.name || 'N3XT 3D',
    nit: company.nit || '',
    email: company.email || 'servicion3xt@gmail.com',
    phone: company.phone || '+57 311 879 6416',
    address: company.address || 'Guateque-Boyaca'
  }
}
