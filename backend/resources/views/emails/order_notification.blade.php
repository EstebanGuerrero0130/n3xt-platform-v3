<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'Helvetica', 'Arial', sans-serif; line-height: 1.6; color: #333; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden; }
        .header { background: #0f172a; color: white; padding: 20px; text-align: center; }
        .content { padding: 30px; }
        .button { display: inline-block; padding: 12px 24px; background-color: #10b981; color: white; text-decoration: none; border-radius: 6px; font-weight: bold; margin-top: 20px; }
        .footer { background: #f8fafc; padding: 15px; text-align: center; font-size: 12px; color: #64748b; border-top: 1px solid #e5e7eb; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>N3XT 3D - Cotización Técnica</h2>
        </div>
        <div class="content">
            <p>Hola <strong>{{ $order->customer_name }}</strong>,</p>
            <p>Hemos procesado tu solicitud de fabricación. Adjunto encontrarás la propuesta técnica detallada en formato PDF para el proyecto <strong>"{{ $order->job_name ?? 'Impresión 3D' }}"</strong>.</p>
            
            <table style="width: 100%; border-collapse: collapse; margin: 20px 0; background: #f8fafc; border: 1px solid #e5e7eb;">
                <tr>
                    <td style="padding: 10px; border-bottom: 1px solid #e5e7eb;"><strong>Orden #</strong></td>
                    <td style="padding: 10px; border-bottom: 1px solid #e5e7eb; text-align: right;">{{ $order->id }}</td>
                </tr>
                <tr>
                    <td style="padding: 10px; border-bottom: 1px solid #e5e7eb;"><strong>Tecnología</strong></td>
                    <td style="padding: 10px; border-bottom: 1px solid #e5e7eb; text-align: right;">{{ $order->technology }}</td>
                </tr>
                <tr>
                    <td style="padding: 10px;"><strong>Total</strong></td>
                    <td style="padding: 10px; text-align: right; color: #10b981; font-weight: bold;">${{ number_format($order->total_price, 0) }}</td>
                </tr>
            </table>

            <p>Puedes revisar el estado en tiempo real y gestionar tus archivos directamente en tu panel de control.</p>
            
            <div style="text-align: center;">
                <a href="{{ url(config('app.url') . '/#/customer/dashboard') }}" class="button">Acceder al Portal</a>
            </div>
        </div>
        <div class="footer">
            <p>Este es un correo automático, por favor no respondas a esta dirección.</p>
            <p>&copy; {{ date('Y') }} N3XT 3D - Tecnología Digital Avanzada</p>
        </div>
    </div>
</body>
</html>
