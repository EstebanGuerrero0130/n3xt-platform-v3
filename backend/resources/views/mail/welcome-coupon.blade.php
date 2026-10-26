@extends('mail.layout', ['title' => 'Bienvenido a N3XT 3D', 'preheader' => "Tu cupón de {$pct}% para el primer pedido"])

@section('content')
  <h1 style="margin:0 0 12px;font-size:24px;line-height:1.25;">Bienvenido, {{ $name }}</h1>
  <p style="margin:0 0 24px;">Tu cuenta está lista. Para tu primer pedido tienes <strong>{{ $pct }}% de descuento</strong> con este cupón:</p>
  <p style="margin:0 0 24px;text-align:center;">
    <span style="display:inline-block;padding:14px 24px;border:2px dashed #08872b;border-radius:12px;font-size:24px;font-weight:900;letter-spacing:2px;font-family:'Courier New',monospace;color:#06701f;">{{ $code }}</span>
  </p>
  <p style="margin:0 0 24px;color:#4e5c55;font-size:14px;">Es de un solo uso y funciona con el correo de tu cuenta.</p>
  <p style="margin:0;">
    <a href="{{ $quoteUrl }}" style="display:inline-block;padding:14px 28px;background:#08872b;color:#ffffff;text-decoration:none;font-weight:700;border-radius:999px;">Cotizar mi primera pieza</a>
  </p>
@endsection
