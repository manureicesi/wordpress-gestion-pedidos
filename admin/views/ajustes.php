<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap gpi-wrap">
  <h1>Ajustes — Pedidos Internos</h1>
  <hr class="wp-header-end">

  <?php if ( isset( $_GET['updated'] ) ) : ?>
    <div class="notice notice-success is-dismissible"><p>Ajustes guardados.</p></div>
  <?php endif; ?>

  <!-- ── General ─────────────────────────────────────────────────────── -->
  <h2 class="gpi-section-title">General</h2>
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
            <p class="description">Página con el shortcode <code>[gpi_estado_pedido]</code>. Se incluye en el QR del resguardo.</p>
          </td>
        </tr>
        <tr>
          <th scope="row">Código QR en el ticket</th>
          <td>
            <?php $mostrar_qr = get_option( 'gpi_mostrar_qr', '1' ); ?>
            <label>
              <input type="checkbox" name="gpi_mostrar_qr" value="1" <?php checked( $mostrar_qr, '1' ); ?>>
              Mostrar código QR en el resguardo imprimible
            </label>
            <p class="description">La URL de seguimiento siempre aparece en el ticket aunque el QR esté desactivado.</p>
          </td>
        </tr>
      </table>

      <!-- ── Impresora térmica (QZ Tray / ESC/POS) ─────────────────────── -->
      <?php $qz = GPI_Admin::get_print_settings(); ?>
      <h2 class="gpi-section-title" id="gpi-impresora">Impresora térmica (QZ Tray)</h2>
      <p class="description">
        Imprime el resguardo en ESC/POS crudo a través de <a href="https://qz.io/download/" target="_blank" rel="noopener">QZ Tray</a>
        instalado en el PC de la impresora. Si QZ Tray no está disponible se ofrece el diálogo de impresión del navegador.
      </p>
      <table class="form-table" id="gpi-qz-settings">
        <tr>
          <th scope="row"><label for="gpi_print_metodo">Método de impresión</label></th>
          <td>
            <select name="gpi_print_metodo" id="gpi_print_metodo">
              <option value="qz" <?php selected( $qz['metodo'], 'qz' ); ?>>QZ Tray — ESC/POS directo (recomendado)</option>
              <option value="navegador" <?php selected( $qz['metodo'], 'navegador' ); ?>>Diálogo de impresión del navegador</option>
            </select>
          </td>
        </tr>
        <tr>
          <th scope="row"><label for="gpi_qz_impresora">Nombre de la impresora</label></th>
          <td>
            <input type="text" name="gpi_qz_impresora" id="gpi_qz_impresora" class="regular-text" value="<?php echo esc_attr( $qz['impresora'] ); ?>" placeholder="Ej.: POS-80C">
            <button type="button" class="button" id="gpi-qz-detectar">Detectar impresoras</button>
            <select id="gpi-qz-lista" style="display:none;max-width:320px"></select>
            <p class="description">Nombre exacto tal como lo ve el sistema operativo / QZ Tray.
              Estado de QZ Tray: <span id="gpi-qz-estado" class="gpi-qz-estado">sin conectar</span></p>
          </td>
        </tr>
        <tr>
          <th scope="row"><label for="gpi_qz_columnas">Ancho en caracteres</label></th>
          <td>
            <input type="number" name="gpi_qz_columnas" id="gpi_qz_columnas" min="16" max="64" step="1" value="<?php echo esc_attr( $qz['columnas'] ); ?>" style="width:80px">
            <p class="description">Habitual: 48 o 42 en 80 mm, 32 en 58 mm (fuente A). Usa la regla de la página de prueba para comprobarlo.</p>
          </td>
        </tr>
        <tr>
          <th scope="row"><label for="gpi_qz_codificacion">Codificación</label></th>
          <td>
            <select name="gpi_qz_codificacion" id="gpi_qz_codificacion">
              <?php foreach ( [ 'cp858' => 'CP858 — Multilingüe con € (recomendada)', 'cp850' => 'CP850 — Multilingüe (sin €)', 'cp1252' => 'Windows-1252' ] as $val => $label ) : ?>
                <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $qz['codificacion'], $val ); ?>><?php echo esc_html( $label ); ?></option>
              <?php endforeach; ?>
            </select>
            <label style="margin-left:10px">Tabla ESC t (avanzado):
              <input type="number" name="gpi_qz_codepage" id="gpi_qz_codepage" min="0" max="255" value="<?php echo esc_attr( $qz['codepage'] ); ?>" placeholder="auto" style="width:80px">
            </label>
            <p class="description">Déjalo en «auto» (CP858 = 19, CP850 = 2, 1252 = 16 en Epson). Si los acentos salen mal, consulta el manual de tu impresora y pon aquí el número de tabla.</p>
          </td>
        </tr>
        <tr>
          <th scope="row"><label for="gpi_qz_copias">Copias</label></th>
          <td><input type="number" name="gpi_qz_copias" id="gpi_qz_copias" min="1" max="10" value="<?php echo esc_attr( $qz['copias'] ); ?>" style="width:80px"></td>
        </tr>
        <tr>
          <th scope="row">Opciones</th>
          <td>
            <label><input type="checkbox" name="gpi_qz_cajon" id="gpi_qz_cajon" value="1" <?php checked( $qz['cajon'] ); ?>> Abrir cajón portamonedas al imprimir</label><br>
            <label><input type="checkbox" name="gpi_qz_auto" id="gpi_qz_auto" value="1" <?php checked( $qz['auto'] ); ?>> Imprimir automáticamente al crear un pedido</label><br>
            <label><input type="checkbox" name="gpi_qz_barcode" id="gpi_qz_barcode" value="1" <?php checked( $qz['barcode'] ); ?>> Código de barras con el número de pedido</label>
          </td>
        </tr>
        <tr>
          <th scope="row"><label for="gpi_qz_iva">IVA incluido (%)</label></th>
          <td>
            <input type="number" name="gpi_qz_iva" id="gpi_qz_iva" min="0" max="100" step="0.01" value="<?php echo esc_attr( $qz['iva'] ); ?>" style="width:80px">
            <p class="description">Desglose base / IVA del presupuesto en el ticket. 0 = sin desglose.</p>
          </td>
        </tr>
        <tr>
          <th scope="row"><label for="gpi_ticket_comercio">Nombre del comercio</label></th>
          <td><input type="text" name="gpi_ticket_comercio" id="gpi_ticket_comercio" class="regular-text" value="<?php echo esc_attr( $qz['comercio'] ); ?>"></td>
        </tr>
        <tr>
          <th scope="row"><label for="gpi_ticket_cabecera">Cabecera</label></th>
          <td><textarea name="gpi_ticket_cabecera" id="gpi_ticket_cabecera" rows="3" class="large-text" placeholder="CIF, dirección, teléfono…"><?php echo esc_textarea( $qz['cabecera'] ); ?></textarea></td>
        </tr>
        <tr>
          <th scope="row"><label for="gpi_ticket_pie">Pie</label></th>
          <td><textarea name="gpi_ticket_pie" id="gpi_ticket_pie" rows="2" class="large-text"><?php echo esc_textarea( $qz['pie'] ); ?></textarea></td>
        </tr>
        <tr>
          <th scope="row">Prueba</th>
          <td>
            <button type="button" class="button button-secondary" id="gpi-qz-prueba">🖨️ Imprimir página de prueba</button>
            <p class="description">Usa los valores actuales del formulario, aunque no estén guardados.</p>
            <div id="gpi-qz-resultado" aria-live="polite"></div>
          </td>
        </tr>
      </table>

      <p class="submit">
        <button type="submit" class="button button-primary">Guardar ajustes</button>
      </p>
    </form>
  </div>

  <!-- ── Estados ─────────────────────────────────────────────────────── -->
  <h2 class="gpi-section-title" style="margin-top:28px">Gestión de Estados</h2>
  <div class="gpi-card">
    <p class="description" style="margin-bottom:12px">
      Los estados marcados como <strong>Ocultar por defecto</strong> no aparecen en la lista de pedidos a menos que se filtre por ese estado o se active "Mostrar todos".
    </p>
    <div id="gpi-msg-estados"></div>
    <table class="gpi-settings-table widefat">
      <thead>
        <tr>
          <th style="width:70px">Orden</th>
          <th style="width:44px">Color</th>
          <th>Nombre</th>
          <th style="width:70px">Activo</th>
          <th style="width:120px">Ocultar por defecto</th>
          <th style="width:130px">Acciones</th>
        </tr>
      </thead>
      <tbody id="gpi-estados-tbody">
        <?php foreach ( $estados as $e ) : ?>
        <tr data-id="<?php echo esc_attr( $e->id ); ?>" id="gpi-estado-row-<?php echo esc_attr( $e->id ); ?>">
          <td class="gpi-order-btns">
            <button class="button button-small gpi-btn-mover-estado" data-id="<?php echo esc_attr( $e->id ); ?>" data-dir="up" title="Subir">&#9650;</button>
            <button class="button button-small gpi-btn-mover-estado" data-id="<?php echo esc_attr( $e->id ); ?>" data-dir="down" title="Bajar">&#9660;</button>
          </td>
          <td><span class="gpi-color-dot" style="background:<?php echo esc_attr( $e->color ); ?>"></span></td>
          <td>
            <span class="gpi-estado-nombre"><?php echo esc_html( $e->nombre ); ?></span>
            <span class="gpi-estado-slug" style="color:#9ca3af;font-size:11px;display:block"><?php echo esc_html( $e->slug ); ?></span>
          </td>
          <td><?php echo $e->activo ? '<span class="gpi-check">✓</span>' : '<span class="gpi-cross">✗</span>'; ?></td>
          <td><?php echo $e->ocultar_por_defecto ? '<span class="gpi-check">✓</span>' : '—'; ?></td>
          <td class="gpi-actions">
            <button class="button button-small gpi-btn-edit-estado" data-id="<?php echo esc_attr( $e->id ); ?>"
              data-nombre="<?php echo esc_attr( $e->nombre ); ?>"
              data-color="<?php echo esc_attr( $e->color ); ?>"
              data-activo="<?php echo esc_attr( $e->activo ); ?>"
              data-ocultar="<?php echo esc_attr( $e->ocultar_por_defecto ); ?>">Editar</button>
            <button class="button button-small gpi-btn-delete-estado" data-id="<?php echo esc_attr( $e->id ); ?>">Eliminar</button>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <!-- Form añadir / editar estado -->
    <div id="gpi-form-estado" class="gpi-inline-form" style="margin-top:16px">
      <h3 id="gpi-form-estado-title">Añadir nuevo estado</h3>
      <input type="hidden" id="gpi-estado-id" value="0">
      <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
        <div>
          <label for="gpi-estado-nombre">Nombre <span class="required">*</span></label>
          <input type="text" id="gpi-estado-nombre" placeholder="Nombre del estado" maxlength="80" style="width:180px">
        </div>
        <div>
          <label for="gpi-estado-color">Color</label>
          <input type="color" id="gpi-estado-color" value="#3b82f6" class="gpi-color-input">
        </div>
        <div>
          <label><input type="checkbox" id="gpi-estado-activo" checked> Activo</label>
        </div>
        <div>
          <label><input type="checkbox" id="gpi-estado-ocultar"> Ocultar por defecto</label>
        </div>
        <div>
          <button id="gpi-btn-guardar-estado" class="button button-primary">Añadir estado</button>
          <button id="gpi-btn-cancelar-estado" class="button" style="display:none">Cancelar</button>
        </div>
      </div>
    </div>
  </div>

  <!-- ── Etiquetas ────────────────────────────────────────────────────── -->
  <h2 class="gpi-section-title" style="margin-top:28px">Gestión de Etiquetas</h2>
  <div class="gpi-card">
    <p class="description" style="margin-bottom:12px">
      Las etiquetas son opcionales y permiten clasificar pedidos con una categoría personalizada (ej. Urgente, VIP, Garantía…).
    </p>
    <div id="gpi-msg-etiquetas"></div>
    <table class="gpi-settings-table widefat">
      <thead>
        <tr>
          <th style="width:44px">Color</th>
          <th>Nombre</th>
          <th style="width:70px">Activo</th>
          <th style="width:130px">Acciones</th>
        </tr>
      </thead>
      <tbody id="gpi-etiquetas-tbody">
        <?php foreach ( $etiquetas as $et ) : ?>
        <tr data-id="<?php echo esc_attr( $et->id ); ?>" id="gpi-etiqueta-row-<?php echo esc_attr( $et->id ); ?>">
          <td><span class="gpi-color-dot" style="background:<?php echo esc_attr( $et->color ); ?>"></span></td>
          <td><?php echo esc_html( $et->nombre ); ?></td>
          <td><?php echo $et->activo ? '<span class="gpi-check">✓</span>' : '<span class="gpi-cross">✗</span>'; ?></td>
          <td class="gpi-actions">
            <button class="button button-small gpi-btn-edit-etiqueta" data-id="<?php echo esc_attr( $et->id ); ?>"
              data-nombre="<?php echo esc_attr( $et->nombre ); ?>"
              data-color="<?php echo esc_attr( $et->color ); ?>"
              data-activo="<?php echo esc_attr( $et->activo ); ?>">Editar</button>
            <button class="button button-small gpi-btn-delete-etiqueta" data-id="<?php echo esc_attr( $et->id ); ?>">Eliminar</button>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <!-- Form añadir / editar etiqueta -->
    <div id="gpi-form-etiqueta" class="gpi-inline-form" style="margin-top:16px">
      <h3 id="gpi-form-etiqueta-title">Añadir nueva etiqueta</h3>
      <input type="hidden" id="gpi-etiqueta-id" value="0">
      <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
        <div>
          <label for="gpi-etiqueta-nombre">Nombre <span class="required">*</span></label>
          <input type="text" id="gpi-etiqueta-nombre" placeholder="Nombre de la etiqueta" maxlength="80" style="width:180px">
        </div>
        <div>
          <label for="gpi-etiqueta-color">Color</label>
          <input type="color" id="gpi-etiqueta-color" value="#6b7280" class="gpi-color-input">
        </div>
        <div>
          <label><input type="checkbox" id="gpi-etiqueta-activo" checked> Activo</label>
        </div>
        <div>
          <button id="gpi-btn-guardar-etiqueta" class="button button-primary">Añadir etiqueta</button>
          <button id="gpi-btn-cancelar-etiqueta" class="button" style="display:none">Cancelar</button>
        </div>
      </div>
    </div>
  </div>

  <!-- ── Shortcode info ────────────────────────────────────────────────── -->
  <div class="gpi-card" style="margin-top:20px">
    <h2>Shortcode disponible</h2>
    <p>Añade este shortcode en la página de seguimiento para que los clientes puedan consultar el estado de su pedido:</p>
    <code style="font-size:15px;padding:6px 12px;display:inline-block;background:#f0f0f0;border-radius:4px">[gpi_estado_pedido]</code>
    <p class="description" style="margin-top:8px">El visitante podrá introducir el número de pedido o acceder con el parámetro <code>?pedido=PED-XXXX-XXXX</code> (enlace del QR).</p>
  </div>
</div>
