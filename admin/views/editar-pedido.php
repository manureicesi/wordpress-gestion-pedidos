<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap gpi-wrap">
  <h1>✏️ Editar Pedido <?php echo esc_html( $pedido->numero ); ?></h1>
  <hr class="wp-header-end">

  <div class="gpi-card">
    <div id="gpi-form-msg"></div>
    <form id="gpi-form-editar" autocomplete="off">
      <?php wp_nonce_field( 'gpi_nonce', '_wpnonce' ); ?>
      <input type="hidden" id="pedido_id" name="pedido_id" value="<?php echo esc_attr( $pedido->id ); ?>">

      <div class="gpi-form-row">
        <label for="solicitante">Solicitante <span class="required">*</span></label>
        <input type="text" id="solicitante" name="solicitante" required maxlength="150" value="<?php echo esc_attr( $pedido->solicitante ); ?>">
      </div>

      <div class="gpi-form-row">
        <label for="descripcion">Descripción del pedido <span class="required">*</span></label>
        <textarea id="descripcion" name="descripcion" required rows="5"><?php echo esc_textarea( $pedido->descripcion ); ?></textarea>
      </div>

      <div class="gpi-form-row">
        <label for="notas">Notas internas</label>
        <textarea id="notas" name="notas" rows="3"><?php echo esc_textarea( $pedido->notas ); ?></textarea>
      </div>

      <div class="gpi-form-row">
        <label for="presupuesto">Presupuesto (€)</label>
        <input type="number" id="presupuesto" name="presupuesto" step="0.01" min="0" value="<?php echo esc_attr( number_format( $pedido->presupuesto, 2, '.', '' ) ); ?>">
      </div>

      <div class="gpi-form-row">
        <label>
          <input type="hidden" name="pagado" value="0">
          <input type="checkbox" id="pagado" name="pagado" value="1" <?php checked( $pedido->pagado, 1 ); ?>>
          Pagado
        </label>
      </div>

      <div class="gpi-form-actions">
        <button type="submit" class="button button-primary button-large">Guardar Cambios</button>
        <button type="button" class="button button-large gpi-btn-print" data-id="<?php echo esc_attr( $pedido->id ); ?>">🖨️ Imprimir Ticket</button>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=gpi-pedidos' ) ); ?>" class="button button-large">Cancelar</a>
      </div>
    </form>
  </div>

  <?php if ( ! empty( $historial ) ) : ?>
  <div class="gpi-card" style="margin-top: 20px;">
    <h2>📜 Historial de Estados</h2>
    <table class="widefat fixed striped">
      <thead>
        <tr>
          <th>Fecha</th>
          <th>Estado</th>
          <th>Usuario</th>
          <th>Nota</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ( $historial as $h ) : ?>
        <tr>
          <td><?php echo esc_html( date_i18n( 'd/m/Y H:i', strtotime( $h->fecha ) ) ); ?></td>
          <td>
            <span class="gpi-badge" style="background:<?php echo esc_attr( $h->estado_color ); ?>;color:#fff;padding:2px 8px;border-radius:3px;font-size:12px;">
              <?php echo esc_html( $h->estado_nombre ); ?>
            </span>
          </td>
          <td><?php echo esc_html( $h->usuario ?: '—' ); ?></td>
          <td><?php echo esc_html( $h->nota ?: '—' ); ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>
