@extends('mail.layout', ['title' => 'Cambia tu contraseña', 'preheader' => "Tu código es {$code}"])

@section('content')
  <h1 style="margin:0 0 12px;font-size:24px;line-height:1.25;">Hola, {{ $name }}</h1>
  <p style="margin:0 0 24px;">Pediste cambiar la contraseña de tu cuenta N3XT 3D. Escribe este código en la página:</p>
  <p style="margin:0 0 24px;text-align:center;">
    <span style="display:inline-block;padding:16px 28px;background:#eaefea;border-radius:12px;font-size:34px;font-weight:900;letter-spacing:10px;font-family:'Courier New',monospace;">{{ $code }}</span>
  </p>
  <p style="margin:0 0 8px;">Vence en {{ $minutes }} minutos.</p>
  <p style="margin:0;color:#4e5c55;font-size:14px;">Si no fuiste tú, ignora este correo: tu contraseña sigue igual.</p>
@endsection
