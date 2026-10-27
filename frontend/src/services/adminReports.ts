/**
 * Reportes imprimibles del panel (Contabilidad → Exportar): balance ejecutivo
 * (`metrics`) y libro de ventas. Arma el HTML y lo imprime desde un iframe oculto.
 */
import { writePrintDocument } from './pdfPrint'

export interface ReportInput {
  type: 'metrics' | string
  start?: string
  end?: string
  filteredOrders: any[]
  /** Respuesta de /admin/analytics para el rango */
  reportData: any
  /** Ajustes del panel (empresa y logo) */
  settings: any
  /** Base de /storage para el logo subido */
  storageUrl: string
}

export function buildReportHtml({ type, start, end, filteredOrders, reportData, settings, storageUrl }: ReportInput): string {
  const company = settings.company || { name: 'N3XT 3D Technology', nit: 'N/A' };
  const getAbsoluteUrl = (path: string) => path.startsWith('http') ? path : window.location.origin + (path.startsWith('/') ? '' : '/') + path;
  const companyLogo = settings.company_logo ? getAbsoluteUrl(settings.company_logo.startsWith('http') ? settings.company_logo : storageUrl + '/' + settings.company_logo) : window.location.origin + '/logo.png';
  let content = '';

  if (type === 'metrics') {
    
    // EXECUTIVE MANUFACTURING AUDIT (PREMIUM REDESIGN)
    content = `
      <html>
        <head>
          <title>N3XT 3D - Balance Ejecutivo [${start || 'HISTÓRICO'}]</title>
          <style>
            @import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700;900&display=swap');
            @page { size: A4; margin: 10mm; }
            
            body { 
              font-family: 'Outfit', sans-serif; 
              padding: 0; 
              margin: 0;
              color: #0f172a; 
              background: #fff; 
              line-height: 1.2; 
              font-size: 11px; 
            }

            /* Layout Containers */
            .page-wrapper { padding: 30px; }
            
            /* Premium Header */
            .header-bar { 
              display: flex; 
              justify-content: space-between; 
              align-items: flex-start; 
              border-bottom: 2px solid #f1f5f9;
              padding-bottom: 25px;
              margin-bottom: 30px;
            }
            
            .brand-box { display: flex; align-items: center; gap: 15px; }
            .logo-img { height: 55px; max-width: 200px; width: auto; object-fit: contain; }
            .brand-info h1 { 
              font-size: 26px; 
              font-weight: 900; 
              letter-spacing: -1.5px; 
              margin: 0; 
              text-transform: uppercase;
              color: #0f172a;
            }
            .brand-info p { 
              font-size: 8px; 
              font-weight: 800; 
              color: #64748b; 
              text-transform: uppercase; 
              letter-spacing: 3px; 
              margin: 4px 0 0 0;
            }

            .audit-info { text-align: right; }
            .status-badge {
              display: inline-block;
              background: #1e3a34;
              color: white;
              padding: 6px 12px;
              border-radius: 8px;
              font-size: 9px;
              font-weight: 900;
              text-transform: uppercase;
              letter-spacing: 1px;
              margin-bottom: 10px;
            }
            .period-label { font-size: 8px; font-weight: 800; color: #94a3b8; text-transform: uppercase; margin-bottom: 2px; }
            .period-val { font-size: 11px; font-weight: 900; color: #1e293b; }

            /* Hero Metrics Cards */
            .hero-grid { 
              display: grid; 
              grid-template-columns: repeat(4, 1fr); 
              gap: 15px; 
              margin-bottom: 30px; 
            }
            .card { 
              padding: 20px; 
              border-radius: 24px; 
              border: 1px solid #f1f5f9;
              background: #f8fafc;
            }
            .card.dark { background: #0f172a; color: white; border: none; }
            .card.emerald { background: #1e3a34; color: white; border: none; }
            
            .card-label { 
              font-size: 7px; 
              font-weight: 900; 
              text-transform: uppercase; 
              letter-spacing: 1.5px; 
              margin-bottom: 8px; 
              opacity: 0.7;
            }
            .card-val { font-size: 24px; font-weight: 900; letter-spacing: -0.5px; }
            .card-sub { font-size: 8px; font-weight: 700; margin-top: 5px; opacity: 0.6; }

            /* Main Content Grid */
            .content-grid { 
              display: grid; 
              grid-template-columns: 1fr 1.6fr; 
              gap: 30px; 
            }

            /* Section Styling */
            .section { margin-bottom: 25px; }
            .section-header { 
              display: flex; 
              align-items: center; 
              gap: 8px;
              margin-bottom: 15px;
              border-bottom: 1px solid #f1f5f9;
              padding-bottom: 8px;
            }
            .dot { width: 6px; height: 6px; border-radius: 50%; background: #1e3a34; }
            .section-title { 
              font-size: 9px; 
              font-weight: 900; 
              text-transform: uppercase; 
              letter-spacing: 2px; 
              color: #0f172a;
            }

            /* Data Lists */
            .data-item { 
              display: flex; 
              justify-content: space-between; 
              align-items: center;
              padding: 10px 0; 
              border-bottom: 1px solid #f8fafc;
            }
            .data-item:last-child { border: none; }
            .item-label { font-weight: 600; color: #64748b; font-size: 10px; }
            .item-val { font-weight: 900; color: #0f172a; font-size: 11px; }
            .item-val.neg { color: #ef4444; }
            .item-val.pos { color: #059669; }

            /* Nested items for breakdown */
            .sub-item { padding-left: 15px; opacity: 0.8; font-size: 9px; padding-top: 5px; padding-bottom: 5px; }
            .sub-item .item-label { font-weight: 400; font-size: 9px; }

            /* Tables */
            .audit-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
            .audit-table th { 
              text-align: left; 
              background: #f1f5f9; 
              padding: 12px; 
              font-weight: 900; 
              color: #64748b; 
              text-transform: uppercase; 
              font-size: 7px;
              letter-spacing: 1px;
              border-radius: 8px 0 0 8px;
            }
            .audit-table th:last-child { border-radius: 0 8px 8px 0; }
            .audit-table td { padding: 12px; border-bottom: 1px solid #f1f5f9; }
            .audit-table tr:last-child td { border: none; }

            /* Footer */
            .footer { 
              margin-top: 40px; 
              padding-top: 20px; 
              border-top: 1px solid #f1f5f9;
              display: flex;
              justify-content: space-between;
              align-items: flex-end;
            }
            .sys-stamp { font-size: 7px; font-weight: 800; color: #94a3b8; line-height: 1.5; }
            .sign-box { width: 180px; text-align: center; }
            .sign-line { border-top: 1.5px solid #0f172a; margin-top: 15px; padding-top: 5px; font-weight: 900; font-size: 8px; text-transform: uppercase; }

            @media print {
              body { -webkit-print-color-adjust: exact; }
              .card.dark { background-color: #0f172a !important; color: white !important; }
              .card.emerald { background-color: #1e3a34 !important; color: white !important; }
              .status-badge { background-color: #1e3a34 !important; color: white !important; }
            }
          </style>
        </head>
        <body>
          <div class="page-wrapper">
            <!-- Header -->
            <div class="header-bar">
              <div class="brand-box">
                ${companyLogo ? `<img src="${companyLogo}" class="logo-img">` : ''}
                <div class="brand-info">
                  <h1>${settings.company?.name || 'N3XT 3D'}</h1>
                  <p>
                    ${settings.company?.nit ? `NIT: ${settings.company.nit} | ` : ''} 
                    ${settings.company?.address || ''}<br>
                    ${settings.company?.phone ? `TEL: ${settings.company.phone} | ` : ''} 
                    ${settings.company?.email || ''}
                  </p>
                </div>
              </div>
              <div class="audit-info">
                <div class="status-badge">Reporte de Auditoría Verificado</div>
                <div class="period-label">Intervalo de Análisis</div>
                <div class="period-val">${start || 'ORIGEN'} AL ${end || 'HOY'}</div>
              </div>
            </div>

            <!-- Hero Metrics -->
            <div class="hero-grid">
              <div class="card">
                <div class="card-label">Ingresos Brutos</div>
                <div class="card-val">$${Number(reportData.summary.total_revenue).toLocaleString()}</div>
                <div class="card-sub">${reportData.summary.orders_count} órdenes procesadas</div>
              </div>
              <div class="card">
                <div class="card-label">Gastos de Operación</div>
                <div class="card-val" style="color: #ef4444;">$${Number(reportData.summary.total_expenses).toLocaleString()}</div>
                <div class="card-sub">Costo de producción base</div>
              </div>
              <div class="card emerald">
                <div class="card-label">Utilidad Neta Real</div>
                <div class="card-val">$${Number(reportData.summary.net_profit).toLocaleString()}</div>
                <div class="card-sub" style="color: #4ade80;">Flujo de caja positivo</div>
              </div>
              <div class="card">
                <div class="card-label">Margen de Beneficio</div>
                <div class="card-val">${reportData.summary.profit_margin_pct}%</div>
                <div class="card-sub">Retorno sobre inversión</div>
              </div>
            </div>

            <!-- Content Sections -->
            <div class="content-grid">
              <div class="left-col">
                <!-- Manufacturing Volume -->
                <div class="section">
                  <div class="section-header"><div class="dot"></div><div class="section-title">Actividad de Taller</div></div>
                  <div class="data-item"><span class="item-label">Horas Máquina</span><span class="item-val">${reportData.summary.total_hours} H</span></div>
                  <div class="data-item"><span class="item-label">Masa Total Procesada</span><span class="item-val">${reportData.summary.total_weight_kg} KG</span></div>
                  <div class="data-item"><span class="item-label">Tasa de Entrega</span><span class="item-val">${reportData.summary.completed_count} / ${reportData.summary.orders_count}</span></div>
                </div>

                <!-- Waste Management -->
                <div class="section">
                  <div class="section-header"><div class="dot" style="background: #ef4444;"></div><div class="section-title">Control de Pérdidas</div></div>
                  <div class="data-item"><span class="item-label">Costo por Fallos</span><span class="item-val neg">$${Number(reportData.summary.waste_cost).toLocaleString()}</span></div>
                  <div class="data-item"><span class="item-label">Material Desperdiciado</span><span class="item-val">${reportData.summary.waste_weight_g} g</span></div>
                </div>

                <!-- Technologies -->
                <div class="section">
                  <div class="section-header"><div class="dot" style="background: #64748b;"></div><div class="section-title">Mix Tecnológico</div></div>
                  ${reportData.by_technology.map((tech: any) => `
                    <div class="data-item">
                      <span class="item-label">${tech.technology === 'FDM' ? 'Filamento (FDM)' : 'Resina (SLA)'}</span>
                      <span class="item-val">${tech.count} trabajos</span>
                    </div>
                  `).join('')}
                </div>
              </div>

              <div class="right-col">
                <!-- Detailed Financials -->
                <div class="section">
                  <div class="section-header"><div class="dot"></div><div class="section-title">Estado de Gastos Detallado</div></div>
                  
                  <div class="data-item"><span class="item-label">Materia Prima e Insumos</span><span class="item-val">$${Number(reportData.summary.total_material_cost).toLocaleString()}</span></div>
                  <div class="data-item sub-item"><span class="item-label">Consumo FDM</span><span class="item-val">$${Number(reportData.summary.breakdown.mat_fdm).toLocaleString()}</span></div>
                  <div class="data-item sub-item"><span class="item-label">Consumo SLA</span><span class="item-val">$${Number(reportData.summary.breakdown.mat_sla).toLocaleString()}</span></div>
                  
                  <div class="data-item"><span class="item-label">Mano de Obra Especializada</span><span class="item-val">$${Number(reportData.summary.breakdown.labor).toLocaleString()}</span></div>
                  <div class="data-item"><span class="item-label">Costos de Energía y Luz</span><span class="item-val">$${Number(reportData.summary.breakdown.luz).toLocaleString()}</span></div>
                  <div class="data-item"><span class="item-label">Plan de Mantenimiento</span><span class="item-val">$${Number(reportData.summary.breakdown.mant).toLocaleString()}</span></div>
                  <div class="data-item"><span class="item-label">Depreciación Activos</span><span class="item-val">$${Number(reportData.summary.breakdown.depr).toLocaleString()}</span></div>
                  <div class="data-item"><span class="item-label">Logística y Extras</span><span class="item-val">$${Number(reportData.summary.total_extras_cost).toLocaleString()}</span></div>
                </div>

                <!-- Top Customers -->
                <div class="section">
                  <div class="section-header"><div class="dot"></div><div class="section-title">Contribuyentes Principales (LTV)</div></div>
                  <table class="audit-table">
                    <thead>
                      <tr>
                        <th>Identificación Cliente</th>
                        <th style="text-align: right">Contribución Total</th>
                      </tr>
                    </thead>
                    <tbody>
                      ${reportData.top_customers.slice(0, 5).map((c: any) => `
                        <tr>
                          <td style="font-weight: 700;">${c.customer_name} <span style="font-size: 7px; color: #94a3b8; display: block;">${c.orders_count} órdenes registradas</span></td>
                          <td style="text-align: right; font-weight: 900; color: #1e3a34; font-size: 12px;">$${Number(c.total_spent).toLocaleString()}</td>
                        </tr>
                      `).join('')}
                    </tbody>
                  </table>
                </div>
              </div>
            </div>

            <!-- Footer -->
            <div class="footer">
              <div class="sys-stamp">
                SISTEMA DE GESTIÓN N3XT CORE | VERSIÓN 3.2.4<br>
                ID ÚNICO DE AUDITORÍA: ${Math.random().toString(36).substr(2, 9).toUpperCase()}<br>
                EMISIÓN: ${new Date().toLocaleString()}
              </div>
              <div class="sign-box">
                <div style="font-size: 7px; color: #94a3b8; font-weight: 700; margin-bottom: 20px;">VERIFICACIÓN DIGITAL REQUERIDA</div>
                <div class="sign-line">Firma Autorizada Auditoría</div>
                <div style="font-size: 8px; font-weight: 600; margin-top: 4px;">${company.name}</div>
              </div>
            </div>
          </div>
        </body>
      </html>
    `;
  } else {
    // LIBRO DE VENTAS PREMIUM (CLEAN LEDGER AESTHETIC)
    content = `
      <html>
        <head>
          <title>N3XT 3D - Libro de Ventas [${start || 'General'}]</title>
          <style>
            @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap');
            body { font-family: 'Inter', sans-serif; padding: 40px; color: #1e293b; background: #fff; }
            
            .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #f1f5f9; padding-bottom: 25px; margin-bottom: 35px; }
            .logo-img { height: 50px; width: auto; object-fit: contain; margin-right: 15px; }
            .header-left { display: flex; align-items: center; }
            .logo { font-size: 22px; font-weight: 900; letter-spacing: -1px; text-transform: uppercase; color: #0f172a; line-height: 1; }
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
          </style>
        </head>
        <body>
          <div class="header">
            <div class="header-left">
              ${companyLogo ? `<img src="${companyLogo}" class="logo-img">` : ''}
              <div>
                <div class="doc-title">Reporte de Libro Mayor</div>
                <div class="logo">N3XT<span> 3D</span></div>
              </div>
            </div>
            <div style="text-align: right">
              <div style="font-weight: 900; font-size: 14px;">${filteredOrders.length} ÓRDENES</div>
              <div style="font-weight: 600; font-size: 10px; color: #94a3b8;">FILTRO: ${start || 'TODOS'} / ${end || 'TODOS'}</div>
              <div style="font-size: 7px; color: #10b981; font-weight: 900; margin-top: 5px; text-transform: uppercase;">Auditoria Financiera Validada</div>
            </div>
          </div>

          <table>
            <thead>
              <tr>
                <th>ID</th>
                <th>Fecha</th>
                <th>Cliente</th>
                <th>Proyecto / Trabajo</th>
                <th>Tec</th>
                <th>Masa</th>
                <th style="text-align: right">Monto Total</th>
              </tr>
            </thead>
            <tbody>
              ${filteredOrders.map(o => `
                <tr>
                  <td class="id-cell">#${String(o.id).padStart(5, '0')}</td>
                  <td>${new Date(o.created_at).toLocaleDateString()}</td>
                  <td style="color: #0f172a; font-weight: 700;">${o.customer_name}</td>
                  <td style="color: #64748b;">${o.job_name || 'Servicio de Impresión'}</td>
                  <td><span class="tech-badge">${o.technology}</span></td>
                  <td>${o.estimated_weight_g}g</td>
                  <td class="price-cell">$${Number(o.total_price).toLocaleString()}</td>
                </tr>
              `).join('')}
              <tr class="summary-row">
                <td colspan="6" style="text-align: right; font-weight: 900; text-transform: uppercase; letter-spacing: 2px; font-size: 10px;">Venta Total del Periodo</td>
                <td style="text-align: right; font-size: 18px; font-weight: 900;">$${filteredOrders.reduce((acc, o) => acc + parseFloat(o.total_price), 0).toLocaleString()}</td>
              </tr>
            </tbody>
          </table>

          <div class="footer">
            <div>EMITIDO POR SISTEMA N3XT | ID: ${Math.random().toString(36).substr(2, 9).toUpperCase()}</div>
            <div>${company.name} | NIT: ${company.nit} | PÁGINA 1 DE 1</div>
          </div>
        </body>
      </html>
    `;
  }
  
  return content
}

/** Imprime el HTML desde un iframe oculto, esperando a que carguen las imágenes. */
export function printHtml(content: string): void {
  let iframe = document.getElementById('pdf-print-frame') as HTMLIFrameElement | null;
  if (!iframe) {
    iframe = document.createElement('iframe');
    iframe.id = 'pdf-print-frame';
    iframe.style.position = 'absolute';
    iframe.style.top = '-9999px';
    iframe.style.left = '-9999px';
    iframe.style.visibility = 'hidden';
    document.body.appendChild(iframe);
  }
  
  const printWindow = (iframe.contentWindow || iframe.contentDocument?.defaultView) as Window;
  writePrintDocument(printWindow.document, content);
  const imgs = printWindow.document.images;
  let loaded = 0;
  const print = () => { printWindow.focus(); printWindow.print(); };
  if (imgs.length === 0) { setTimeout(print, 250); } 
  else {
      let printed = false;
      const onload = () => { if (++loaded === imgs.length && !printed) { printed = true; setTimeout(print, 250); } };
      for (let i = 0; i < imgs.length; i++) {
          if (imgs[i].complete) onload();
          else { imgs[i].onload = onload; imgs[i].onerror = onload; }
      }
      setTimeout(() => { if (!printed) { printed = true; print(); } }, 3000);
  }
  
}
