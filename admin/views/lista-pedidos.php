<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap gpi-wrap">
  <h1 class="wp-heading-inline">📋 Gestión de Pedidos</h1>
  <a href="<?php echo esc_url( admin_url( 'admin.php?page=gpi-nuevo-pedido' ) ); ?>" class="page-title-action">+ Nuevo Pedido</a>
  <hr class="wp-header-end">

  <!-- Filtros -->
  <div class="gpi-filters">
    <form method="get" class="gpi-filter-form">
      <input type="hidden" name="page" value="gpi-pedidos">
      <input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="Buscar pedido, solicitante…" class="gpi-search-input">
      <select name="estado" class="gpi-select">
        <option value="">— Todos los estados —</option>
        <?php foreach ( $estados as $e ) : ?>
          <option value="<?php echo esc_attr( $e->id ); ?>" <?php selected( $estado_id, $e->id ); ?>>
            <?php echo esc_html( $e->nombre ); ?>
          </option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="button">Filtrar</button>
      <?php if ( $search || $estado_id ) : ?>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=gpi-pedidos' ) ); ?>" class="button">✕ Limpiar</a>
      <?php endif; ?>
    </form>
    <span class="gpi-total"><?php echo esc_html( $total ); ?> pedido(s)</span>
  </div>

  <!-- Tabla -->
  <?php if ( empty( $pedidos ) ) : ?>
    <p class="gpi-empty">No se encontraron pedidos.</p>
  <?php else : ?>
  <table class="gpi-table">
    <thead>
      <tr>
        <th>Número</th>
        <th>Solicitante</th>
        <th>Descripción</th>
        <th>Estado</th>
        <th>Fecha</th>
        <th>Acciones</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ( $pedidos as $p ) : ?>
      <tr data-id="<?php echo esc_attr( $p->id ); ?>">
        <td class="gpi-numero">
          <a href="<?php echo esc_url( admin_url( 'admin.php?page=gpi-editar-pedido&pedido_id=' . $p->id ) ); ?>">
            <?php echo esc_html( $p->numero ); ?>
          </a>
        </td>
        <td><?php echo esc_html( $p->solicitante ); ?></td>
        <td class="gpi-desc"><?php echo esc_html( wp_trim_words( $p->descripcion, 12 ) ); ?></td>
        <td>
          <div class="gpi-estado-cell">
            <span class="gpi-badge gpi-badge-<?php echo esc_attr( $p->id ); ?>"
                  style="background:<?php echo esc_attr( $p->estado_color ); ?>">
              <?php echo esc_html( $p->estado_nombre ); ?>
            </span>
            <select class="gpi-estado-select" data-pedido="<?php echo esc_attr( $p->id ); ?>">
              <?php foreach ( $estados as $e ) : ?>
                <option value="<?php echo esc_attr( $e->id ); ?>" <?php selected( $p->estado_id, $e->id ); ?>>
                  <?php echo esc_html( $e->nombre ); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </td>
        <td><?php echo esc_html( date_i18n( 'd/m/Y H:i', strtotime( $p->creado_en ) ) ); ?></td>
        <td class="gpi-actions">
          <a href="<?php echo esc_url( admin_url( 'admin.php?page=gpi-editar-pedido&pedido_id=' . $p->id ) ); ?>" class="button button-small" title="Editar pedido">✏️</a>
          <button class="button button-small gpi-btn-print" data-id="<?php echo esc_attr( $p->id ); ?>" title="Imprimir resguardo">🖨️</button>
          <button class="button button-small gpi-btn-delete" data-id="<?php echo esc_attr( $p->id ); ?>" title="Eliminar">🗑️</button>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <!-- Paginación -->
  <?php if ( $pages > 1 ) : ?>
  <div class="gpi-pagination">
    <?php for ( $i = 1; $i <= $pages; $i++ ) : ?>
      <?php
        $url = add_query_arg( [ 'page' => 'gpi-pedidos', 'paged' => $i, 's' => $search, 'estado' => $estado_id ], admin_url( 'admin.php' ) );
      ?>
      <a href="<?php echo esc_url( $url ); ?>" class="<?php echo $i === $paged ? 'current' : ''; ?>"><?php echo $i; ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
  <?php endif; ?>
</div>
