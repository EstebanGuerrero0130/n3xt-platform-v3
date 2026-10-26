@extends('mail.layout', ['title' => $headline, 'preheader' => $intro])

@section('content')
  <p style="margin:0 0 4px;color:#4e5c55;font-size:14px;">Pedido #{{ $order->id }}@if($order->job_name) · {{ $order->job_name }}@endif</p>
  <h1 style="margin:0 0 12px;font-size:24px;line-height:1.25;">{{ $headline }}</h1>
  <p style="margin:0 0 24px;">{{ $intro }}</p>

  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 24px;background:#f4f6f3;border-radius:12px;">
    <tr>
      <td style="padding:16px 20px;font-size:15px;line-height:1.7;">
        <strong>Estado:</strong> {{ $statusLabel }}<br>
        @if($order->material_name)<strong>Material:</strong> {{ $order->material_name }}<br>@endif
        <strong>Total:</strong> ${{ number_format((float) $order->total_price, 0, ',', '.') }}
        @if($order->tracking_guide)
          <br><strong>Guía de envío:</strong> {{ $order->tracking_carrier ? $order->tracking_carrier . ' · ' : '' }}{{ $order->tracking_guide }}
        @endif
      </td>
    </tr>
  </table>

  <p style="margin:0 0 8px;">
    <a href="{{ $trackUrl }}" style="display:inline-block;padding:14px 28px;background:#08872b;color:#ffffff;text-decoration:none;font-weight:700;border-radius:999px;">Ver el seguimiento</a>
  </p>
@endsection
