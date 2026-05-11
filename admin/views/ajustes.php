<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap gpi-wrap">
  <h1>⚙️ Ajustes — Pedidos Internos</h1>
  <hr class="wp-header-end">

  <?php if ( isset( $_GET['updated'] ) ) : ?>
    <div class="notice notice-success is-dismissible"><p>✅ Ajustes guardados.</p></div>
  <?php endif; ?>

  <div class="gpi-card">
    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
      <input type="hidden" name="action" value="gpi_save_settings">
      <?php wp_nonce_field( 'gpi_save_settings' ); ?>

      <table class="form-table">
        <tr>
          <th scope="row"><label for="gpi_ticket_ancho">Ancho del ticket</label></th>
          <td>
            <select name="gpi_ticket_ancho" id="gpi_ticket_ancho">
              <?php
              $current = get_option( 'gpi_ticket_ancho', '80mm' );
              $options = [ '58mm' => '58 mm (pequeño)', '80mm' => '80 mm (estándar)', '112mm' => '112 mm (grande)' ];
              foreach ( $options as $val => $label ) {
                  printf( '<option value="%s" %s>%s</option>', esc_attr( $val ), selected( $current, $val, false ), esc_html( $label ) );
              }
              ?>
            </select>
            <p class="description">Ajusta según el papel de tu impresora de tickets.</p>
          </td>
        </tr>
        <tr>
          <th scope="row"><label for="gpi_tracking_page_id">Página de seguimiento</label></th>
          <td>
            <?php
            $page_id = get_option( 'gpi_tracking_page_id', 0 );
            wp_dropdown_pages( [
                'name'              => 'gpi_tracking_page_id',
                'id'                => 'gpi_tracking_page_id',
                'selected'          => $page_id,
                'show_option_none'  => '— Seleccionar página —',
                'option_none_value' => 0,
            ] );
            ?>
            <p class="description">
              Página donde hayas añadido el shortcode <code>[gpi_estado_pedido]</code>.
              Esta URL se incluirá en el QR del resguardo.
            </p>
          </td>
        </tr>
      </table>

      <p class="submit">
        <button type="submit" class="button button-primary">Guardar ajustes</button>
      </p>
    </form>
  </div>

  <div class="gpi-card" style="margin-top:20px">
    <h2>Shortcode disponible</h2>
    <p>Añade este shortcode en la página de seguimiento para que los clientes puedan consultar el estado de su pedido:</p>
    <code style="font-size:15px;padding:6px 12px;display:inline-block;background:#f0f0f0;border-radius:4px">[gpi_estado_pedido]</code>
    <p class="description" style="margin-top:8px">El visitante podrá introducir el número de pedido o acceder directamente con el parámetro <code>?pedido=PED-XXXX-XXXX</code> (enlace del QR).</p>
  </div>
</div>
