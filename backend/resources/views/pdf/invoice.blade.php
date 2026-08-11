<!DOCTYPE html>
<html>
<head>
    <title>N3XT 3D - Cotización #{{ $order->id }}</title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <style>
        @page { size: A4; margin: 8mm; }
        body { font-family: 'Helvetica', 'Arial', sans-serif; padding: 0; color: #0f172a; line-height: 1.25; font-size: 13px; background: white; }
        .header { border-bottom: 5px solid #0f172a; padding-bottom: 15px; margin-bottom: 20px; }
        table.layout { width: 100%; }
        table.layout td { vertical-align: top; }
        .company-info { font-size: 11px; color: #334155; font-weight: bold; line-height: 1.4; border-left: 3px solid #10b981; padding-left: 15px; }
        .company-name { color: #0f172a; font-weight: bold; text-transform: uppercase; font-size: 24px; margin-bottom: 6px; }
        .quote-badge { background: #0f172a; color: white; padding: 10px 25px; border-radius: 18px; text-align: right; }
        .badge-label { font-size: 9px; font-weight: bold; text-transform: uppercase; letter-spacing: 2px; opacity: 0.8; margin-bottom: 2px; }
        .badge-val { font-size: 22px; font-weight: bold; }
        .info-box { background: #f8fafc; padding: 20px; border-radius: 20px; border: 1.5px solid #f1f5f9; margin-bottom: 20px; }
        .info-label { font-size: 10px; font-weight: bold; text-transform: uppercase; color: #94a3b8; margin-bottom: 5px; display: block; letter-spacing: 1px; }
        .info-value { font-weight: bold; font-size: 18px; color: #0f172a; }
        .table { width: 100%; border-collapse: separate; border-spacing: 0; margin-bottom: 20px; }
        .table th { background: #f8fafc; padding: 12px 15px; text-align: left; font-size: 10px; font-weight: bold; text-transform: uppercase; color: #64748b; letter-spacing: 1.5px; border-bottom: 3px solid #0f172a; }
        .table td { padding: 15px; border-bottom: 1.5px solid #f1f5f9; font-size: 14px; font-weight: bold; }
        .total-box { text-align: right; background: #0f172a; color: white; padding: 25px 40px; border-radius: 25px; border: 2px solid #10b981; float: right; width: 300px; }
        .total-label { font-size: 12px; font-weight: bold; text-transform: uppercase; letter-spacing: 2px; color: #10b981; margin-bottom: 5px; }
        .total-val { font-size: 42px; font-weight: bold; letter-spacing: -2px; }
        .footer { font-size: 10px; color: #94a3b8; text-align: center; margin-top: 30px; border-top: 1.5px solid #f1f5f9; padding-top: 20px; font-weight: bold; clear: both; }
    </style>
</head>
<body>
    <div class="header">
        <table class="layout">
            <tr>
                <td style="width: 50%;">
                    <div class="company-info">
                        <div class="company-name">{{ $settings['company.name'] ?? 'N3XT 3D' }}</div>
                        <div>
                            @if(isset($settings['company.nit'])) NIT: {{ $settings['company.nit'] }} | @endif
                            {{ $settings['company.address'] ?? '' }}<br>
                            @if(isset($settings['company.phone'])) TEL: {{ $settings['company.phone'] }} | @endif
                            {{ $settings['company.email'] ?? '' }}
                        </div>
                    </div>
                </td>
                <td style="width: 50%; text-align: right;">
                    <div class="quote-badge">
                        <div class="badge-label">Propuesta Técnica</div>
                        <div class="badge-val">#{{ $order->id }}</div>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <div class="info-box">
        <span class="info-label">Proyecto / Cliente Destino</span>
        <div class="info-value">{{ $order->customer_name }} @if($order->customer_company) ({{ $order->customer_company }}) @endif</div>
        <div style="font-size:14px;color:#475569;font-weight:bold;margin-top:5px;">
            @if($order->customer_id_document) ID: {{ $order->customer_id_document }} | @endif
            @if($order->customer_phone) {{ $order->customer_phone }} | @endif
            {{ $order->customer_email ?? '' }}
        </div>
    </div>

    <table class="table">
        <thead>
            <tr>
                <th>Descripción Industrial</th>
                <th style="width:120px;">Proceso</th>
                <th style="width:180px;">Material</th>
                <th style="width:120px;text-align:center;">Cantidad</th>
                <th style="text-align:right;width:160px;">Inversión</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td style="font-weight:bold;">
                    {{ $order->job_name ?? 'Fabricación Digital Bajo Demanda' }}<br>
                    <span style="font-size:13px;color:#64748b;font-weight:normal;">Producción 3D Especializada</span>
                </td>
                <td>{{ $order->technology }}</td>
                <td>{{ $order->material_name ?? $order->material_id }}</td>
                <td style="text-align:center;font-weight:bold;">{{ $order->qty ?? 1 }} uds</td>
                <td style="text-align:right;font-weight:bold;">${{ number_format($order->total_price - ($order->extras_cost ?? 0), 0) }}</td>
            </tr>
            @if(!empty($order->extra_items) && is_array($order->extra_items))
                @foreach($order->extra_items as $extra)
                <tr>
                    <td>{{ $extra['name'] }} (Adicional)</td>
                    <td>N/A</td>
                    <td>-</td>
                    <td style="text-align:center;font-weight:normal;">{{ $extra['qty'] ?? 1 }} uds</td>
                    <td style="text-align:right;font-weight:bold;">${{ number_format($extra['cost'] * $extra['qty'], 0) }}</td>
                </tr>
                @endforeach
            @endif
        </tbody>
    </table>

    <table class="layout" style="margin-bottom: 20px;">
        <tr>
            <td style="width: 50%; padding-right: 10px;">
                <div style="background: #f0fdf4; border: 1.5px solid #bbf7d0; padding: 15px; border-radius: 20px;">
                    <div style="font-size: 12px; font-weight: bold; color: #166534; text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 10px;">Recomendaciones</div>
                    <ul style="margin:0;padding-left:20px;font-size:11px;color:#166534;font-weight:bold;line-height:1.6;">
                        <li><strong>Estructura:</strong> Optimización de perímetros.</li>
                        <li><strong>Post-Proceso:</strong> {{ $order->technology === 'SLA' ? 'Curado UV intensivo.' : 'Tratamiento térmico.' }}</li>
                    </ul>
                </div>
            </td>
            <td style="width: 50%; padding-left: 10px;">
                <div style="background: #eff6ff; border: 1.5px solid #bfdbfe; padding: 15px; border-radius: 20px;">
                    <div style="font-size: 12px; font-weight: bold; color: #1e40af; text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 10px;">Sello N3XT</div>
                    <p style="font-size:11px;color:#1e40af;font-weight:bold;margin:0;line-height:1.4;">Certificamos que el proyecto ha sido analizado bajo los estándares de precisión N3XT.</p>
                </div>
            </td>
        </tr>
    </table>

    <div class="total-box">
        <div class="total-label">Inversión Total del Proyecto</div>
        <div class="total-val">${{ number_format($order->total_price, 0) }}</div>
        <div style="font-size:11px;margin-top:10px;opacity:0.9;font-weight:bold;color:#10b981;text-transform:uppercase;">
            IVA INCLUIDO ({{ $settings['margin.iva'] ?? 0 }}%) • VALIDEZ: 15 DÍAS
        </div>
    </div>

    <div class="footer">
        <p style="margin-bottom:5px;font-weight:bold;">N3XT 3D Administrative System</p>
        <p>© 2026 {{ $settings['company.name'] ?? 'N3XT' }} - Tecnología Digital Avanzada</p>
        <p>Email: {{ $settings['company.email'] ?? '' }} • {{ $settings['company.address'] ?? '' }}</p>
        <p style="font-size:8px;opacity:0.5;margin-top:10px;">Generado el {{ date('Y-m-d H:i') }}</p>
    </div>
</body>
</html>
