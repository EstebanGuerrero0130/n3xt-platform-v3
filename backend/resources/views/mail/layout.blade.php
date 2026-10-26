<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light">
<title>{{ $title ?? 'N3XT 3D' }}</title>
</head>
{{-- Estilos en línea: Gmail y Outlook ignoran casi todo <style> --}}
<body style="margin:0;padding:0;background:#f4f6f3;font-family:Arial,Helvetica,sans-serif;color:#101814;">
  @isset($preheader)
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;">{{ $preheader }}</div>
  @endisset
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6f3;">
    <tr>
      <td align="center" style="padding:32px 16px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;">
          <tr>
            <td style="background:#0e1512;border-radius:16px 16px 0 0;padding:24px 32px;">
              <span style="font-size:22px;font-weight:900;letter-spacing:-0.5px;color:#ffffff;">N3XT<span style="color:#3fcf72;">3D</span></span>
            </td>
          </tr>
          <tr>
            <td style="background:#ffffff;border-radius:0 0 16px 16px;padding:32px;font-size:16px;line-height:1.6;">
              @yield('content')
            </td>
          </tr>
          <tr>
            <td style="padding:24px 8px;font-size:13px;line-height:1.5;color:#4e5c55;text-align:center;">
              N3XT 3D · Impresión 3D en Colombia<br>
              ¿Dudas? Escríbenos por WhatsApp al +57 311 879 6416
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
