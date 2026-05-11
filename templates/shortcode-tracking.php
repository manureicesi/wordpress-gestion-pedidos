<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="gpi-tracking-wrap">

  <!-- Formulario de búsqueda -->
  <form class="gpi-tracking-form" method="get" action="">
    <label for="gpi-numero-input">Introduce el número de tu pedido:</label>
    <div class="gpi-tracking-input-group">
      <input type="text" id="gpi-numero-input" name="pedido"
             value="<?php echo esc_attr( $numero ); ?>"
             placeholder="PED-20240101-0001"
             maxlength="30" autocomplete="off">
      <button type="submit" class="gpi-btn-buscar">Consultar</button>
    </div>
  </form>

  <?php if ( $error ) : ?>
    <div class="gpi-tracking-error">⚠️ <?php echo esc_html( $error ); ?></div>
  <?php endif; ?>

  <?php if ( $pedido ) : ?>
  <div class="gpi-tracking-card">
    <!-- Cabecera -->
    <div class="gpi-tracking-header">
      <h2 class="gpi-tracking-numero"><?php echo esc_html( $pedido->numero ); ?></h2>
      <span class="gpi-tracking-badge" style="background:<?php echo esc_attr( $pedido->estado_color ); ?>">
        <?php echo esc_html( $pedido->estado_nombre ); ?>
      </span>
    </div>

    <!-- Datos -->
    <dl class="gpi-tracking-datos">
      <div class="gpi-tracking-dato">
        <dt>Solicitante</dt>
        <dd><?php echo esc_html( $pedido->solicitante ); ?></dd>
      </div>
      <div class="gpi-tracking-dato">
        <dt>Fecha de creación</dt>
        <dd><?php echo esc_html( date_i18n( 'd/m/Y H:i', strtotime( $pedido->creado_en ) ) ); ?></dd>
      </div>
      <div class="gpi-tracking-dato">
        <dt>Última actualización</dt>
        <dd><?php echo esc_html( date_i18n( 'd/m/Y H:i', strtotime( $pedido->actualizado_en ) ) ); ?></dd>
      </div>
    </dl>

    <!-- Timeline de estados -->
    <?php
    $historial = GPI_Database::get_historial( $pedido->id );
    $estados   = GPI_Database::get_estados();
    // Slugs de estados predeterminados para la barra de progreso
    $slugs_progreso = [ 'pendiente', 'en-curso', 'en-taller', 'listo', 'entregado' ];
    $slug_actual    = $pedido->estado_slug;
    $cancelado      = ( $slug_actual === 'cancelado' );
    ?>

    <?php if ( ! $cancelado ) : ?>
    <div class="gpi-progress-bar">
      <?php foreach ( $slugs_progreso as $idx => $slug ) :
        $estado_info = null;
        foreach ( $estados as $e ) { if ( $e->slug === $slug ) { $estado_info = $e; break; } }
        if ( ! $estado_info ) continue;
        $done    = false;
        $current = false;
        // Determinar si este step ya se alcanzó
        foreach ( $historial as $h ) {
            if ( $h->estado_nombre === $estado_info->nombre ) { $done = true; break; }
        }
        if ( $slug_actual === $slug ) $current = true;
      ?>
      <div class="gpi-step <?php echo $done ? 'done' : ''; ?> <?php echo $current ? 'current' : ''; ?>">
        <div class="gpi-step-dot" style="<?php echo ( $done || $current ) ? 'background:' . esc_attr( $estado_info->color ) : ''; ?>"></div>
        <div class="gpi-step-label"><?php echo esc_html( $estado_info->nombre ); ?></div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else : ?>
    <div class="gpi-tracking-cancelado">❌ Este pedido ha sido cancelado.</div>
    <?php endif; ?>

    <!-- Historial detallado -->
    <?php if ( ! empty( $historial ) ) : ?>
    <details class="gpi-historial-details">
      <summary>Ver historial completo (<?php echo count( $historial ); ?> eventos)</summary>
      <ul class="gpi-historial-list">
        <?php foreach ( array_reverse( $historial ) as $h ) : ?>
        <li>
          <span class="gpi-dot" style="background:<?php echo esc_attr( $h->estado_color ); ?>"></span>
          <strong><?php echo esc_html( $h->estado_nombre ); ?></strong>
          — <?php echo esc_html( date_i18n( 'd/m/Y H:i', strtotime( $h->fecha ) ) ); ?>
          <?php if ( $h->nota ) : ?><em> · <?php echo esc_html( $h->nota ); ?></em><?php endif; ?>
        </li>
        <?php endforeach; ?>
      </ul>
    </details>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>
