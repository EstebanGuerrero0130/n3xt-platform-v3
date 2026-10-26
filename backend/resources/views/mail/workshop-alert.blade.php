@extends('mail.layout', ['title' => 'Aviso del taller', 'preheader' => 'Revisa el inventario y las máquinas'])

@section('content')
  <h1 style="margin:0 0 12px;font-size:24px;line-height:1.25;">Aviso del taller</h1>

  @if(count($lowStock))
    <h2 style="margin:24px 0 8px;font-size:18px;">Stock bajo</h2>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6f3;border-radius:12px;">
      @foreach($lowStock as $item)
        <tr>
          <td style="padding:10px 20px;font-size:15px;"><strong>{{ $item['name'] }}</strong></td>
          <td style="padding:10px 20px;font-size:15px;text-align:right;white-space:nowrap;">
            {{ number_format($item['stock'], fmod($item['stock'], 1) ? 1 : 0, ',', '.') }} {{ $item['unit'] }}
            <span style="color:#4e5c55;">(mínimo {{ number_format($item['threshold'], fmod($item['threshold'], 1) ? 1 : 0, ',', '.') }})</span>
          </td>
        </tr>
      @endforeach
    </table>
  @endif

  @if(count($maintenance))
    <h2 style="margin:24px 0 8px;font-size:18px;">Mantenimiento pendiente</h2>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6f3;border-radius:12px;">
      @foreach($maintenance as $p)
        <tr>
          <td style="padding:10px 20px;font-size:15px;"><strong>{{ $p['name'] }}</strong></td>
          <td style="padding:10px 20px;font-size:15px;text-align:right;">
            {{ $p['hours'] }} de {{ $p['interval'] }} h
            @if($p['date'])<br><span style="color:#4e5c55;">Fecha prevista: {{ $p['date'] }}</span>@endif
          </td>
        </tr>
      @endforeach
    </table>
  @endif

  <p style="margin:24px 0 8px;">
    <a href="{{ $adminUrl }}" style="display:inline-block;padding:14px 28px;background:#08872b;color:#ffffff;text-decoration:none;font-weight:700;border-radius:999px;">Abrir el panel</a>
  </p>
  <p style="margin:16px 0 0;font-size:13px;color:#4e5c55;">Solo avisamos una vez por ítem; vuelve a avisar después de reponer el stock o registrar el mantenimiento.</p>
@endsection
