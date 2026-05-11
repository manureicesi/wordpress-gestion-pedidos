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

      <div class="gpi-form-actions">
        <button type="submit" class="button button-primary button-large">Guardar Cambios</button>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=gpi-pedidos' ) ); ?>" class="button button-large">Cancelar</a>
      </div>
    </form>
  </div>
</div>
