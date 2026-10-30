<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Alerta de descuentos · Descuentos Perfumes</title>
<style>
  body { margin:0; padding:0; background:#f0edf8; font-family: 'Helvetica Neue', Arial, sans-serif; color:#1a1a2e; }
  .wrap { max-width:580px; margin:32px auto; background:#ffffff; border-radius:16px; overflow:hidden; box-shadow:0 4px 24px rgba(80,40,160,0.10); }
  .header { background:linear-gradient(135deg,#7c3aed,#5b21b6); padding:32px 32px 24px; text-align:center; }
  .header h1 { margin:0 0 6px; font-size:22px; font-weight:700; color:#fff; letter-spacing:-0.3px; }
  .header p  { margin:0; font-size:13px; color:rgba(255,255,255,0.8); }
  .body  { padding:28px 32px; }
  .badge { display:inline-block; background:#7c3aed; color:#fff; font-size:11px; font-weight:700;
           border-radius:999px; padding:3px 12px; letter-spacing:0.5px; margin-bottom:16px; }
  .intro { font-size:14px; color:#4b5563; margin:0 0 20px; line-height:1.6; }
  .card  { border:1.5px solid #e9d5ff; border-radius:12px; overflow:hidden; margin-bottom:12px; }
  .card-header { background:#faf5ff; padding:10px 16px; display:flex; align-items:center; justify-content:space-between; }
  .card-title  { font-size:13px; font-weight:600; color:#1a1a2e; margin:0; }
  .card-store  { font-size:11px; color:#7c3aed; font-weight:500; }
  .card-body   { padding:10px 16px; }
  .price-row   { display:flex; align-items:baseline; gap:10px; margin-bottom:4px; }
  .price-now   { font-size:18px; font-weight:700; color:#7c3aed; }
  .price-was   { font-size:13px; color:#9ca3af; text-decoration:line-through; }
  .price-save  { font-size:12px; color:#16a34a; font-weight:600; }
  .discount-badge { background:#7c3aed; color:#fff; border-radius:999px; padding:2px 10px;
                    font-size:12px; font-weight:700; }
  .btn { display:inline-block; background:#7c3aed; color:#fff; text-decoration:none;
         font-size:13px; font-weight:600; border-radius:10px; padding:10px 22px; margin-top:4px; }
  .footer { background:#f8f5ff; border-top:1px solid #e9d5ff; padding:20px 32px; text-align:center; }
  .footer p { margin:0; font-size:11px; color:#9ca3af; line-height:1.7; }
  .footer a  { color:#7c3aed; text-decoration:none; }
  .test-notice { background:#fef3c7; border:1px solid #fde68a; border-radius:8px; padding:10px 14px;
                 margin-bottom:16px; font-size:12px; color:#92400e; }
</style>
</head>
<body>
<div class="wrap">

  <div class="header">
    <div style="font-size:36px;margin-bottom:8px;">🔔</div>
    <h1>¡Nuevos descuentos encontrados!</h1>
    <p>Tu alerta para <strong>{{ $alerta->tipoLabel() }}: {{ $alerta->valor }}</strong> tiene novedades</p>
  </div>

  <div class="body">

    @if($resultados === true)
    <div class="test-notice">
      ⚠️ <strong>Email de prueba</strong> — Este es un ejemplo de cómo se verán las notificaciones reales.
      Cuando el scraper detecte descuentos que coincidan con tu alerta, recibirás emails como este con datos reales.
    </div>
    @endif

    <span class="badge">{{ strtoupper($alerta->tipoLabel()) }}: {{ strtoupper($alerta->valor) }}</span>

    @if($resultados === true)
    {{-- Test mode: show sample data --}}
    <p class="intro">
      Estamos monitoreando descuentos en <strong>{{ $alerta->valor }}</strong>.
      Cuando detectemos ofertas activas, aparecerán aquí con todos los detalles de precio y tienda.
    </p>

    <div class="card">
      <div class="card-header">
        <p class="card-title">Ejemplo: Producto con descuento</p>
        <span class="card-store">Tienda ejemplo</span>
      </div>
      <div class="card-body">
        <div class="price-row">
          <span class="price-now">$45.000</span>
          <span class="price-was">$65.000</span>
          <span class="price-save">Ahorrás $20.000</span>
        </div>
        <span class="discount-badge">30% OFF</span>
      </div>
    </div>

    @elseif($resultados instanceof \Illuminate\Support\Collection && $resultados->isNotEmpty())
    <p class="intro">
      Encontramos <strong>{{ $resultados->count() }}</strong> producto(s) con descuento que coinciden con tu alerta.
    </p>

    @foreach($resultados as $r)
    <div class="card">
      <div class="card-header">
        <p class="card-title">{{ $r->producto }}</p>
        <span class="card-store">{{ $r->tienda }}</span>
      </div>
      <div class="card-body">
        <div class="price-row">
          <span class="price-now">${{ number_format($r->precio_actual, 0, ',', '.') }}</span>
          <span class="price-was">${{ number_format($r->precio_lista, 0, ',', '.') }}</span>
          @if($r->ahorro > 0)
          <span class="price-save">Ahorrás ${{ number_format($r->ahorro, 0, ',', '.') }}</span>
          @endif
        </div>
        <span class="discount-badge">{{ round($r->descuento_pct) }}% OFF</span>
        @if(!empty($r->talles))
        <p style="margin:8px 0 0;font-size:12px;color:#64748b;">Talles disponibles: {{ $r->talles }}</p>
        @endif
        @if(!empty($r->url))
        <br><br>
        <a href="{{ $r->url }}" class="btn">Ver en tienda →</a>
        @endif
      </div>
    </div>
    @endforeach
    @endif

  </div>

  <div class="footer">
    <p>
      Recibís este email porque configuraste una alerta en
      <a href="{{ config('app.url') }}">Descuentos · Perfumes ARG</a>.<br>
      Para desactivarla, ingresá a tu cuenta y eliminá la alerta.
    </p>
  </div>

</div>
</body>
</html>
