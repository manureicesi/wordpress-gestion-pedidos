<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap gpi-wrap">
  <h1>➕ Nuevo Pedido</h1>
  <hr class="wp-header-end">

  <div class="gpi-card">
    <div id="gpi-form-msg"></div>
    <form id="gpi-form-nuevo" autocomplete="off">
      <?php wp_nonce_field( 'gpi_nonce', '_wpnonce' ); ?>

      <div class="gpi-form-row">
        <label for="solicitante">Solicitante <span class="required">*</span></label>
        <input type="text" id="solicitante" name="solicitante" required maxlength="150" placeholder="Nombre de quien realiza el pedido">
      </div>

      <div class="gpi-form-row">
        <label for="descripcion">Descripción del pedido <span class="required">*</span></label>
        <textarea id="descripcion" name="descripcion" required rows="5" placeholder="Detalla aquí los artículos o servicios solicitados…"></textarea>
      </div>

      <div class="gpi-form-row">
        <label for="notas">Notas internas</label>
        <textarea id="notas" name="notas" rows="3" placeholder="Notas opcionales (solo visibles internamente)"></textarea>
      </div>

      <div class="gpi-form-row">
        <label for="presupuesto">Presupuesto (€)</label>
        <input type="number" id="presupuesto" name="presupuesto" step="0.01" min="0" placeholder="0.00">
      </div>

      <div class="gpi-form-row">
        <label>
          <input type="hidden" name="pagado" value="0">
          <input type="checkbox" id="pagado" name="pagado" value="1">
          Pagado
        </label>
      </div>

      <div class="gpi-form-actions">
        <button type="submit" class="button button-primary button-large">Crear Pedido</button>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=gpi-pedidos' ) ); ?>" class="button button-large">Cancelar</a>
      </div>
    </form>
  </div>
</div>
