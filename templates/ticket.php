<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Resguardo <?php echo esc_html( $pedido->numero ); ?></title>
<style>
  /* ── Reset y base ── */
  * { margin:0; padding:0; box-sizing:border-box; }

  @page {
    size: <?php echo esc_html( $ancho ); ?> auto;
    margin: 0;
  }

  body {
    font-family: 'Courier New', Courier, monospace;
    font-size: 11px;
    color: #000;
    background: #fff;
    width: <?php echo esc_html( $ancho ); ?>;
    padding: 4mm 3mm;
  }

  /* ── Utilidades ── */
  .center  { text-align: center; }
  .bold    { font-weight: bold; }
  .divider { border: none; border-top: 1px dashed #000; margin: 3mm 0; }
  .small   { font-size: 9px; }
  .big     { font-size: 14px; }
  .xl      { font-size: 17px; }

  /* ── Cabecera ── */
  .ticket-header { text-align: center; margin-bottom: 2mm; }
  .ticket-header .logo { font-size: 16px; font-weight: bold; letter-spacing: 1px; }
  .ticket-header .subtitle { font-size: 9px; color: #555; }

  /* ── Número de pedido ── */
  .numero-pedido {
    text-align: center;
    font-size: 18px;
    font-weight: bold;
    letter-spacing: 2px;
    padding: 2mm 0;
    border: 2px solid #000;
    margin: 2mm 0;
  }

  /* ── Estado ── */
  .estado-badge {
    display: block;
    text-align: center;
    font-size: 12px;
    font-weight: bold;
    padding: 1.5mm 0;
    border-radius: 2px;
    margin: 2mm 0;
    background: <?php echo esc_attr( $pedido->estado_color ); ?>;
    color: #fff;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }

  /* ── Campos ── */
  .campo { margin-bottom: 1.5mm; }
  .campo .label { font-size: 9px; text-transform: uppercase; color: #666; }
  .campo .valor { font-size: 11px; font-weight: bold; word-break: break-word; }

  /* ── Descripción ── */
  .descripcion { font-size: 10px; margin: 1mm 0 2mm; line-height: 1.4; }

  /* ── Historial ── */
  .historial { font-size: 9px; }
  .historial .item { padding: 0.8mm 0; border-bottom: 1px dotted #ccc; }
  .historial .item:last-child { border-bottom: none; }

  /* ── QR ── */
  .qr-block { text-align: center; margin: 3mm 0 2mm; }
  .qr-block img { display: block; margin: 0 auto; }
  .qr-block .qr-label { font-size: 8px; color: #555; margin-top: 1mm; }

  /* ── Footer ── */
  .ticket-footer { text-align: center; font-size: 8px; color: #777; margin-top: 3mm; }

  /* ── Botón imprimir (no se imprime) ── */
  .btn-print {
    display: block;
    margin: 8px auto;
    padding: 8px 24px;
    font-size: 14px;
    background: #2563eb;
    color: #fff;
    border: none;
    border-radius: 4px;
    cursor: pointer;
  }
  @media print { .btn-print { display: none; } }
</style>
</head>
<body>

<!-- Botón visible en pantalla, oculto al imprimir -->
<button class="btn-print" onclick="window.print()">🖨️ Imprimir</button>

<!-- ── CABECERA ── -->
<div class="ticket-header">
  <div class="logo"><?php echo esc_html( strtoupper( $site_name ) ); ?></div>
  <div class="subtitle">RESGUARDO DE PEDIDO</div>
</div>

<hr class="divider">

<!-- ── NÚMERO DE PEDIDO ── -->
<div class="numero-pedido"><?php echo esc_html( $pedido->numero ); ?></div>

<!-- ── ESTADO ACTUAL ── -->
<span class="estado-badge"><?php echo esc_html( strtoupper( $pedido->estado_nombre ) ); ?></span>

<hr class="divider">

<!-- ── DATOS PRINCIPALES ── -->
<div class="campo">
  <div class="label">Solicitante</div>
  <div class="valor"><?php echo esc_html( $pedido->solicitante ); ?></div>
</div>

<div class="campo">
  <div class="label">Fecha</div>
  <div class="valor"><?php echo esc_html( date_i18n( 'd/m/Y H:i', strtotime( $pedido->creado_en ) ) ); ?></div>
</div>

<div class="campo">
  <div class="label">Descripción</div>
  <div class="descripcion"><?php echo nl2br( esc_html( $pedido->descripcion ) ); ?></div>
</div>

<?php if ( ! empty( $pedido->notas ) ) : ?>
<div class="campo">
  <div class="label">Notas</div>
  <div class="descripcion"><?php echo nl2br( esc_html( $pedido->notas ) ); ?></div>
</div>
<?php endif; ?>

<!-- ── HISTORIAL DE ESTADOS ── -->
<?php if ( ! empty( $historial ) ) : ?>
<hr class="divider">
<div class="campo">
  <div class="label">Historial</div>
</div>
<div class="historial">
  <?php foreach ( $historial as $h ) : ?>
  <div class="item">
    <?php echo esc_html( date_i18n( 'd/m/Y H:i', strtotime( $h->fecha ) ) ); ?> —
    <strong><?php echo esc_html( $h->estado_nombre ); ?></strong>
    <?php if ( $h->nota ) : ?>
      · <?php echo esc_html( $h->nota ); ?>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ── QR ── -->
<hr class="divider">
<div class="qr-block">
  <?php if ( $qr_base64 ) : ?>
    <img src="<?php echo esc_attr( $qr_base64 ); ?>" alt="QR Seguimiento" width="130" height="130">
  <?php else : ?>
    <img src="<?php echo esc_url( GPI_QR::url( $tracking_url, 130 ) ); ?>" alt="QR Seguimiento" width="130" height="130">
  <?php endif; ?>
  <div class="qr-label">Escanea para ver el estado del pedido</div>
  <div class="qr-label small"><?php echo esc_html( $tracking_url ); ?></div>
</div>

<!-- ── FOOTER ── -->
<hr class="divider">
<div class="ticket-footer">
  <?php echo esc_html( $site_name ); ?> · <?php echo esc_html( date_i18n( 'd/m/Y H:i:s' ) ); ?><br>
  Conserve este resguardo para consultar su pedido.
</div>

<script>
  // Auto-imprime cuando se abre desde el botón del panel
  if ( window.location.search.indexOf('autoprint=1') !== -1 ) {
    window.addEventListener('load', function(){ window.print(); });
  }
</script>
</body>
</html>
