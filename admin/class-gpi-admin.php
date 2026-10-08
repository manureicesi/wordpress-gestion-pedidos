<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class GPI_Admin {

    public function init() {
        add_action( 'admin_menu',             [ $this, 'register_menus' ] );
        add_action( 'admin_enqueue_scripts',  [ $this, 'enqueue_assets' ] );

        // Pedidos AJAX
        add_action( 'wp_ajax_gpi_crear_pedido',    [ $this, 'ajax_crear_pedido' ] );
        add_action( 'wp_ajax_gpi_cambiar_estado',  [ $this, 'ajax_cambiar_estado' ] );
        add_action( 'wp_ajax_gpi_eliminar_pedido', [ $this, 'ajax_eliminar_pedido' ] );
        add_action( 'wp_ajax_gpi_imprimir_ticket', [ $this, 'ajax_imprimir_ticket' ] );
        add_action( 'wp_ajax_gpi_editar_pedido',   [ $this, 'ajax_editar_pedido' ] );
        add_action( 'wp_ajax_gpi_toggle_pagado',   [ $this, 'ajax_toggle_pagado' ] );

        // Impresión ESC/POS vía QZ Tray
        add_action( 'wp_ajax_gpi_ticket_data',      [ $this, 'ajax_ticket_data' ] );
        add_action( 'wp_ajax_gpi_qz_certificado',   [ $this, 'ajax_qz_certificado' ] );
        add_action( 'wp_ajax_gpi_qz_firmar',        [ $this, 'ajax_qz_firmar' ] );

        // Estados AJAX
        add_action( 'wp_ajax_gpi_save_estado_item',   [ $this, 'ajax_save_estado_item' ] );
        add_action( 'wp_ajax_gpi_delete_estado_item', [ $this, 'ajax_delete_estado_item' ] );
        add_action( 'wp_ajax_gpi_mover_estado',       [ $this, 'ajax_mover_estado' ] );

        // Etiquetas AJAX
        add_action( 'wp_ajax_gpi_save_etiqueta',       [ $this, 'ajax_save_etiqueta' ] );
        add_action( 'wp_ajax_gpi_delete_etiqueta',     [ $this, 'ajax_delete_etiqueta' ] );

        // Autocomplete solicitantes
        add_action( 'wp_ajax_gpi_buscar_solicitantes', [ $this, 'ajax_buscar_solicitantes' ] );

        // Admin-post
        add_action( 'admin_post_gpi_save_settings', [ $this, 'save_settings' ] );
        add_action( 'admin_post_gpi_export_csv',    [ $this, 'export_csv' ] );
    }

    // ── MENÚS ─────────────────────────────────────────────────────────────

    public function register_menus() {
        add_menu_page(
            'Pedidos Internos', 'Pedidos', 'manage_woocommerce',
            'gpi-pedidos', [ $this, 'page_lista_pedidos' ],
            'dashicons-clipboard', 26
        );
        add_submenu_page(
            'gpi-pedidos', 'Nuevo Pedido', 'Nuevo Pedido',
            'manage_woocommerce', 'gpi-nuevo-pedido', [ $this, 'page_nuevo_pedido' ]
        );
        add_submenu_page(
            'gpi-pedidos', 'Ajustes', 'Ajustes',
            'manage_woocommerce', 'gpi-ajustes', [ $this, 'page_ajustes' ]
        );
        add_submenu_page(
            'gpi-pedidos', 'Editar Pedido', null,
            'manage_woocommerce', 'gpi-editar-pedido', [ $this, 'page_editar_pedido' ]
        );
    }

    // ── ASSETS ────────────────────────────────────────────────────────────

    public function enqueue_assets( $hook ) {
        if ( strpos( $hook, 'gpi-' ) === false ) return;
        wp_enqueue_style( 'gpi-admin', GPI_PLUGIN_URL . 'assets/css/admin.css', [], GPI_VERSION );

        $deps = [ 'jquery' ];
        if ( $this->is_print_screen( $hook ) ) {
            // QZ Tray solo se carga (y por tanto solo se conecta) en pantallas que imprimen.
            wp_enqueue_script( 'gpi-qz-tray', GPI_PLUGIN_URL . 'assets/vendor/qz-tray/qz-tray.js', [], '2.2.6', true );
            wp_enqueue_script( 'gpi-escpos', GPI_PLUGIN_URL . 'assets/js/gpi-escpos.js', [], GPI_VERSION, true );
            wp_enqueue_script( 'gpi-qz', GPI_PLUGIN_URL . 'assets/js/gpi-qz.js', [ 'jquery', 'gpi-qz-tray' ], GPI_VERSION, true );
            $deps = [ 'jquery', 'gpi-escpos', 'gpi-qz' ];
        }

        wp_enqueue_script( 'gpi-admin', GPI_PLUGIN_URL . 'assets/js/admin.js', $deps, GPI_VERSION, true );
        wp_localize_script( 'gpi-admin', 'GPI', [
            'ajax_url'   => admin_url( 'admin-ajax.php' ),
            'nonce'      => wp_create_nonce( 'gpi_nonce' ),
            'print_url'  => admin_url( 'admin-ajax.php?action=gpi_imprimir_ticket&nonce=' . wp_create_nonce( 'gpi_print' ) ),
            'export_url' => admin_url( 'admin-post.php?action=gpi_export_csv&_wpnonce=' . wp_create_nonce( 'gpi_export_csv' ) ),
            'qz'         => self::get_print_settings(),
        ] );
    }

    /**
     * Pantallas donde se imprime: lista, edición y ajustes (prueba). En "Nuevo pedido"
     * solo si la impresión automática al crear está activada.
     */
    private function is_print_screen( $hook ) {
        $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
        if ( in_array( $page, [ 'gpi-pedidos', 'gpi-editar-pedido', 'gpi-ajustes' ], true ) ) {
            return true;
        }
        if ( 'gpi-nuevo-pedido' === $page ) {
            $s = self::get_print_settings();
            return 'qz' === $s['metodo'] && $s['auto'];
        }
        return false;
    }

    /**
     * Ajustes de impresión térmica (QZ Tray / ESC/POS) normalizados.
     * Se exponen al JS; no contienen datos sensibles.
     */
    public static function get_print_settings() {
        return [
            'metodo'       => get_option( 'gpi_print_metodo', 'navegador' ) === 'qz' ? 'qz' : 'navegador',
            'impresora'    => (string) get_option( 'gpi_qz_impresora', '' ),
            'columnas'     => absint( get_option( 'gpi_qz_columnas', 42 ) ) ?: 42,
            'codificacion' => (string) get_option( 'gpi_qz_codificacion', 'cp858' ),
            'codepage'     => (string) get_option( 'gpi_qz_codepage', '' ),
            'cajon'        => get_option( 'gpi_qz_cajon', '0' ) === '1',
            'copias'       => absint( get_option( 'gpi_qz_copias', 1 ) ) ?: 1,
            'auto'         => get_option( 'gpi_qz_auto', '0' ) === '1',
            'barcode'      => get_option( 'gpi_qz_barcode', '0' ) === '1',
            'qr'           => get_option( 'gpi_mostrar_qr', '1' ) === '1',
            'iva'          => (float) get_option( 'gpi_qz_iva', 0 ),
            'comercio'     => (string) ( get_option( 'gpi_ticket_comercio', '' ) ?: get_bloginfo( 'name' ) ),
            'cabecera'     => (string) get_option( 'gpi_ticket_cabecera', '' ),
            'pie'          => (string) get_option( 'gpi_ticket_pie', 'Conserve este resguardo para consultar su pedido.' ),
            'signing'      => self::qz_signing_enabled(),
        ];
    }

    /**
     * Firma de QZ Tray activa si en wp-config.php se definen las rutas (fuera del
     * directorio público) del certificado y la clave privada:
     *   define( 'GPI_QZ_CERT_FILE', '/ruta/digital-certificate.txt' );
     *   define( 'GPI_QZ_KEY_FILE',  '/ruta/private-key.pem' );
     *   define( 'GPI_QZ_KEY_PASS',  '' ); // opcional
     */
    private static function qz_signing_enabled() {
        return defined( 'GPI_QZ_CERT_FILE' ) && defined( 'GPI_QZ_KEY_FILE' )
            && is_readable( GPI_QZ_CERT_FILE ) && is_readable( GPI_QZ_KEY_FILE );
    }

    // ── PÁGINAS ───────────────────────────────────────────────────────────

    public function page_lista_pedidos() {
        $estado_id     = isset( $_GET['estado'] )        ? absint( $_GET['estado'] ) : null;
        $etiqueta_id   = isset( $_GET['etiqueta'] )      ? absint( $_GET['etiqueta'] ) : null;
        $search        = isset( $_GET['s'] )             ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
        $paged         = isset( $_GET['paged'] )         ? absint( $_GET['paged'] ) : 1;
        $mostrar_listos = ! empty( $_GET['mostrar_listos'] );
        $per_page      = 20;

        // Ocultar estados marcados como "ocultar por defecto" cuando no hay filtro de estado
        $excluir_ids = [];
        if ( ! $estado_id && ! $mostrar_listos ) {
            $excluir_ids = GPI_Database::get_estados_ocultos_ids();
        }

        $args    = compact( 'estado_id', 'etiqueta_id', 'search', 'paged', 'per_page' );
        $args['excluir_estado_ids'] = $excluir_ids;

        $pedidos   = GPI_Database::get_pedidos( $args );
        $total     = GPI_Database::count_pedidos( $args );
        $estados   = GPI_Database::get_estados();
        $etiquetas = GPI_Database::get_etiquetas();
        $pages     = ceil( $total / $per_page );

        include GPI_PLUGIN_DIR . 'admin/views/lista-pedidos.php';
    }

    public function page_nuevo_pedido() {
        $estados   = GPI_Database::get_estados();
        $etiquetas = GPI_Database::get_etiquetas();
        include GPI_PLUGIN_DIR . 'admin/views/nuevo-pedido.php';
    }

    public function page_editar_pedido() {
        $pedido_id = isset( $_GET['pedido_id'] ) ? absint( $_GET['pedido_id'] ) : 0;
        $pedido    = GPI_Database::get_pedido( $pedido_id );
        if ( ! $pedido ) {
            wp_die( 'Pedido no encontrado.' );
        }
        $historial = GPI_Database::get_historial( $pedido_id );
        $estados   = GPI_Database::get_estados();
        $etiquetas = GPI_Database::get_etiquetas();
        include GPI_PLUGIN_DIR . 'admin/views/editar-pedido.php';
    }

    public function page_ajustes() {
        $estados   = GPI_Database::get_estados( false );
        $etiquetas = GPI_Database::get_etiquetas( false );
        include GPI_PLUGIN_DIR . 'admin/views/ajustes.php';
    }

    // ── AJAX — PEDIDOS ────────────────────────────────────────────────────

    private function verify_nonce() {
        check_ajax_referer( 'gpi_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Sin permiso.', 403 );
        }
    }

    public function ajax_crear_pedido() {
        $this->verify_nonce();
        $numero = GPI_Pedido::generar_numero();
        $id     = GPI_Database::insert_pedido( [
            'numero'      => $numero,
            'solicitante' => sanitize_text_field( wp_unslash( $_POST['solicitante'] ?? '' ) ),
            'descripcion' => sanitize_textarea_field( wp_unslash( $_POST['descripcion'] ?? '' ) ),
            'notas'       => sanitize_textarea_field( wp_unslash( $_POST['notas'] ?? '' ) ),
            'presupuesto' => floatval( wp_unslash( $_POST['presupuesto'] ?? 0 ) ),
            'pagado'      => (int) ( wp_unslash( $_POST['pagado'] ?? 0 ) ),
            'telefono'    => sanitize_text_field( wp_unslash( $_POST['telefono'] ?? '' ) ),
            'email'       => sanitize_email( wp_unslash( $_POST['email'] ?? '' ) ),
            'etiqueta_id' => absint( wp_unslash( $_POST['etiqueta_id'] ?? 0 ) ),
        ] );
        if ( $id ) {
            wp_send_json_success( [ 'id' => $id, 'numero' => $numero, 'redirect' => admin_url( 'admin.php?page=gpi-pedidos' ) ] );
        } else {
            wp_send_json_error( 'Error al crear el pedido.' );
        }
    }

    public function ajax_editar_pedido() {
        $this->verify_nonce();
        $pedido_id = absint( $_POST['pedido_id'] ?? 0 );
        if ( ! $pedido_id ) {
            wp_send_json_error( 'ID de pedido no válido.' );
        }
        $updated = GPI_Database::update_pedido( $pedido_id, [
            'solicitante' => sanitize_text_field( wp_unslash( $_POST['solicitante'] ?? '' ) ),
            'descripcion' => sanitize_textarea_field( wp_unslash( $_POST['descripcion'] ?? '' ) ),
            'notas'       => sanitize_textarea_field( wp_unslash( $_POST['notas'] ?? '' ) ),
            'presupuesto' => floatval( wp_unslash( $_POST['presupuesto'] ?? 0 ) ),
            'pagado'      => (int) ( wp_unslash( $_POST['pagado'] ?? 0 ) ),
            'telefono'    => sanitize_text_field( wp_unslash( $_POST['telefono'] ?? '' ) ),
            'email'       => sanitize_email( wp_unslash( $_POST['email'] ?? '' ) ),
            'etiqueta_id' => absint( wp_unslash( $_POST['etiqueta_id'] ?? 0 ) ),
        ] );
        if ( $updated ) {
            wp_send_json_success( [ 'redirect' => admin_url( 'admin.php?page=gpi-pedidos' ) ] );
        } else {
            wp_send_json_error( 'Error al actualizar el pedido.' );
        }
    }

    public function ajax_toggle_pagado() {
        $this->verify_nonce();
        $pedido_id = absint( $_POST['pedido_id'] ?? 0 );
        $pedido    = GPI_Database::get_pedido( $pedido_id );
        if ( ! $pedido ) {
            wp_send_json_error( 'Pedido no encontrado.' );
        }
        $nuevo = $pedido->pagado ? 0 : 1;
        if ( GPI_Database::update_pedido( $pedido_id, [ 'pagado' => $nuevo ] ) ) {
            wp_send_json_success( [ 'pagado' => $nuevo ] );
        } else {
            wp_send_json_error( 'Error al cambiar estado de pago.' );
        }
    }

    public function ajax_cambiar_estado() {
        $this->verify_nonce();
        $pedido_id = absint( $_POST['pedido_id'] ?? 0 );
        $estado_id = absint( $_POST['estado_id'] ?? 0 );
        $nota      = sanitize_textarea_field( wp_unslash( $_POST['nota'] ?? '' ) );

        if ( GPI_Database::update_estado( $pedido_id, $estado_id, $nota ) ) {
            $pedido = GPI_Database::get_pedido( $pedido_id );
            wp_send_json_success( [
                'estado_nombre' => $pedido->estado_nombre,
                'estado_color'  => $pedido->estado_color,
            ] );
        } else {
            wp_send_json_error( 'Error al actualizar estado.' );
        }
    }

    public function ajax_eliminar_pedido() {
        $this->verify_nonce();
        $id = absint( $_POST['pedido_id'] ?? 0 );
        if ( GPI_Database::delete_pedido( $id ) ) {
            wp_send_json_success();
        } else {
            wp_send_json_error( 'Error al eliminar.' );
        }
    }

    public function ajax_imprimir_ticket() {
        if ( ! check_ajax_referer( 'gpi_print', 'nonce', false ) ) wp_die( 'Nonce inválido.' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) wp_die( 'Sin permiso.' );
        $id = absint( $_GET['pedido_id'] ?? 0 );
        GPI_Print::render_ticket( $id );
    }

    // ── AJAX — IMPRESIÓN QZ TRAY ──────────────────────────────────────────

    /** Datos del pedido para construir el ticket ESC/POS en el navegador. */
    public function ajax_ticket_data() {
        $this->verify_nonce();
        $pedido = GPI_Database::get_pedido( absint( $_POST['pedido_id'] ?? 0 ) );
        if ( ! $pedido ) {
            wp_send_json_error( 'Pedido no encontrado.' );
        }

        $historial = array_map( function ( $h ) {
            return [
                'fecha'  => date_i18n( 'd/m/Y H:i', strtotime( $h->fecha ) ),
                'estado' => (string) $h->estado_nombre,
                'nota'   => (string) $h->nota,
            ];
        }, array_slice( GPI_Database::get_historial( $pedido->id ), -5 ) );

        wp_send_json_success( [
            'numero'       => $pedido->numero,
            'estado'       => (string) $pedido->estado_nombre,
            'fecha'        => date_i18n( 'd/m/Y H:i', strtotime( $pedido->creado_en ) ),
            'solicitante'  => $pedido->solicitante,
            'telefono'     => (string) $pedido->telefono,
            'email'        => (string) $pedido->email,
            'etiqueta'     => (string) ( $pedido->etiqueta_nombre ?? '' ),
            'descripcion'  => $pedido->descripcion,
            'presupuesto'  => (float) $pedido->presupuesto,
            'pagado'       => (bool) $pedido->pagado,
            'historial'    => $historial,
            'tracking_url' => GPI_Pedido::get_tracking_url( $pedido->numero ),
            'impreso'      => 'Impreso: ' . date_i18n( 'd/m/Y H:i' ),
        ] );
    }

    /** Certificado público para QZ Tray (solo si la firma está configurada). */
    public function ajax_qz_certificado() {
        $this->verify_nonce();
        if ( ! self::qz_signing_enabled() ) {
            wp_send_json_error( 'Firma QZ no configurada.' );
        }
        wp_send_json_success( file_get_contents( GPI_QZ_CERT_FILE ) );
    }

    /** Firma SHA512 de la petición de QZ Tray con la clave privada del servidor. */
    public function ajax_qz_firmar() {
        $this->verify_nonce();
        if ( ! self::qz_signing_enabled() ) {
            wp_send_json_error( 'Firma QZ no configurada.' );
        }
        // QZ envía un hash hexadecimal; no se sanitiza más allá de validar el formato.
        $request = (string) wp_unslash( $_POST['request'] ?? '' );
        if ( ! preg_match( '/^[A-Fa-f0-9]{16,256}$/', $request ) ) {
            wp_send_json_error( 'Petición de firma no válida.' );
        }
        $key = openssl_pkey_get_private(
            file_get_contents( GPI_QZ_KEY_FILE ),
            defined( 'GPI_QZ_KEY_PASS' ) ? GPI_QZ_KEY_PASS : ''
        );
        if ( ! $key || ! openssl_sign( $request, $signature, $key, OPENSSL_ALGO_SHA512 ) ) {
            wp_send_json_error( 'No se pudo firmar la petición (revisa la clave privada).' );
        }
        wp_send_json_success( base64_encode( $signature ) );
    }

    // ── AJAX — ESTADOS ────────────────────────────────────────────────────

    public function ajax_save_estado_item() {
        $this->verify_nonce();
        $id     = absint( $_POST['id'] ?? 0 );
        $nombre = sanitize_text_field( wp_unslash( $_POST['nombre'] ?? '' ) );
        $color  = sanitize_hex_color( wp_unslash( $_POST['color'] ?? '#cccccc' ) ) ?: '#cccccc';
        $activo = isset( $_POST['activo'] ) ? (int)(bool)$_POST['activo'] : 1;
        $ocultar = isset( $_POST['ocultar_por_defecto'] ) ? (int)(bool)$_POST['ocultar_por_defecto'] : 0;

        if ( empty( $nombre ) ) {
            wp_send_json_error( 'El nombre es obligatorio.' );
        }

        if ( $id ) {
            $ok = GPI_Database::update_estado_item( $id, [
                'nombre'              => $nombre,
                'color'               => $color,
                'activo'              => $activo,
                'ocultar_por_defecto' => $ocultar,
            ] );
            if ( $ok === false ) wp_send_json_error( 'Error al actualizar.' );
            wp_send_json_success( [ 'id' => $id, 'nombre' => $nombre, 'color' => $color, 'activo' => $activo, 'ocultar_por_defecto' => $ocultar ] );
        } else {
            $ok = GPI_Database::insert_estado_item( [
                'nombre'              => $nombre,
                'color'               => $color,
                'activo'              => $activo,
                'ocultar_por_defecto' => $ocultar,
            ] );
            if ( ! $ok ) wp_send_json_error( 'Error al crear estado.' );
            global $wpdb;
            $new_id = $wpdb->insert_id;
            $estado = GPI_Database::get_estado( $new_id );
            wp_send_json_success( [ 
                'id' => $new_id, 
                'nombre' => $estado->nombre, 
                'color' => $estado->color, 
                'activo' => $estado->activo, 
                'ocultar_por_defecto' => $estado->ocultar_por_defecto 
            ] );
        }
    }

    public function ajax_delete_estado_item() {
        $this->verify_nonce();
        $id     = absint( $_POST['id'] ?? 0 );
        $result = GPI_Database::delete_estado_item( $id );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message() );
        }
        if ( ! $result ) {
            wp_send_json_error( 'Error al eliminar.' );
        }
        wp_send_json_success();
    }

    public function ajax_mover_estado() {
        $this->verify_nonce();
        $id        = absint( $_POST['id'] ?? 0 );
        $direction = in_array( $_POST['direction'] ?? '', [ 'up', 'down' ], true ) ? $_POST['direction'] : 'up';
        if ( GPI_Database::mover_estado( $id, $direction ) ) {
            wp_send_json_success();
        } else {
            wp_send_json_error( 'No se puede mover más.' );
        }
    }

    // ── AJAX — ETIQUETAS ──────────────────────────────────────────────────

    public function ajax_save_etiqueta() {
        $this->verify_nonce();
        $id     = absint( $_POST['id'] ?? 0 );
        $nombre = sanitize_text_field( wp_unslash( $_POST['nombre'] ?? '' ) );
        $color  = sanitize_hex_color( wp_unslash( $_POST['color'] ?? '#6b7280' ) ) ?: '#6b7280';
        $activo = isset( $_POST['activo'] ) ? (int)(bool)$_POST['activo'] : 1;

        if ( empty( $nombre ) ) {
            wp_send_json_error( 'El nombre es obligatorio.' );
        }

        if ( $id ) {
            $ok = GPI_Database::update_etiqueta( $id, compact( 'nombre', 'color', 'activo' ) );
            if ( $ok === false ) wp_send_json_error( 'Error al actualizar.' );
            wp_send_json_success( [ 'id' => $id, 'nombre' => $nombre, 'color' => $color, 'activo' => $activo ] );
        } else {
            $ok = GPI_Database::insert_etiqueta( compact( 'nombre', 'color', 'activo' ) );
            if ( ! $ok ) wp_send_json_error( 'Error al crear etiqueta.' );
            global $wpdb;
            $new_id = $wpdb->insert_id;
            $etiqueta = GPI_Database::get_etiqueta( $new_id );
            wp_send_json_success( [ 
                'id' => $new_id, 
                'nombre' => $etiqueta->nombre, 
                'color' => $etiqueta->color, 
                'activo' => $etiqueta->activo 
            ] );
        }
    }

    public function ajax_buscar_solicitantes() {
        $this->verify_nonce();
        $q = sanitize_text_field( wp_unslash( $_POST['q'] ?? '' ) );
        if ( strlen( $q ) < 3 ) {
            wp_send_json_success( [] );
        }
        wp_send_json_success( GPI_Database::search_solicitantes( $q ) );
    }

    public function ajax_delete_etiqueta() {
        $this->verify_nonce();
        $id     = absint( $_POST['id'] ?? 0 );
        $result = GPI_Database::delete_etiqueta( $id );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message() );
        }
        if ( ! $result ) {
            wp_send_json_error( 'Error al eliminar.' );
        }
        wp_send_json_success();
    }

    // ── CSV EXPORT ────────────────────────────────────────────────────────

    public function export_csv() {
        check_admin_referer( 'gpi_export_csv' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) wp_die( 'Sin permiso.' );

        $estado_id      = isset( $_GET['estado'] )   ? absint( $_GET['estado'] ) : null;
        $etiqueta_id    = isset( $_GET['etiqueta'] )  ? absint( $_GET['etiqueta'] ) : null;
        $search         = isset( $_GET['s'] )         ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
        $mostrar_listos = ! empty( $_GET['mostrar_listos'] );

        $excluir_ids = [];
        if ( ! $estado_id && ! $mostrar_listos ) {
            $excluir_ids = GPI_Database::get_estados_ocultos_ids();
        }

        $args = [
            'estado_id'          => $estado_id,
            'etiqueta_id'        => $etiqueta_id,
            'search'             => $search,
            'excluir_estado_ids' => $excluir_ids,
        ];

        $pedidos = GPI_Database::get_all_pedidos_for_export( $args );

        $filename = 'pedidos-' . date( 'Y-m-d' ) . '.csv';
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );

        $out = fopen( 'php://output', 'w' );
        fprintf( $out, chr(0xEF) . chr(0xBB) . chr(0xBF) ); // UTF-8 BOM para Excel

        fputcsv( $out, [ 'Número', 'Solicitante', 'Teléfono', 'Email', 'Descripción', 'Estado', 'Etiqueta', 'Presupuesto', 'Pagado', 'Fecha creación' ], ';' );

        foreach ( $pedidos as $p ) {
            fputcsv( $out, [
                $p->numero,
                $p->solicitante,
                $p->telefono ?? '',
                $p->email ?? '',
                $p->descripcion,
                $p->estado_nombre ?? '',
                $p->etiqueta_nombre ?? '',
                number_format( (float) $p->presupuesto, 2, ',', '.' ),
                $p->pagado ? 'Sí' : 'No',
                date_i18n( 'd/m/Y H:i', strtotime( $p->creado_en ) ),
            ], ';' );
        }

        fclose( $out );
        exit;
    }

    // ── AJUSTES ───────────────────────────────────────────────────────────

    public function save_settings() {
        check_admin_referer( 'gpi_save_settings' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) wp_die( 'Sin permiso.' );
        update_option( 'gpi_ticket_ancho',     sanitize_text_field( wp_unslash( $_POST['gpi_ticket_ancho']    ?? '80mm' ) ) );
        update_option( 'gpi_tracking_page_id', absint( $_POST['gpi_tracking_page_id'] ?? 0 ) );
        update_option( 'gpi_mostrar_qr',       isset( $_POST['gpi_mostrar_qr'] ) ? '1' : '0' );

        // Impresora térmica (QZ Tray / ESC/POS)
        $metodo = sanitize_key( wp_unslash( $_POST['gpi_print_metodo'] ?? 'navegador' ) );
        $codif  = sanitize_key( wp_unslash( $_POST['gpi_qz_codificacion'] ?? 'cp858' ) );
        $cp     = trim( sanitize_text_field( wp_unslash( $_POST['gpi_qz_codepage'] ?? '' ) ) );
        update_option( 'gpi_print_metodo',    in_array( $metodo, [ 'qz', 'navegador' ], true ) ? $metodo : 'navegador' );
        update_option( 'gpi_qz_impresora',    sanitize_text_field( wp_unslash( $_POST['gpi_qz_impresora'] ?? '' ) ) );
        update_option( 'gpi_qz_columnas',     min( 64, max( 16, absint( $_POST['gpi_qz_columnas'] ?? 42 ) ) ) );
        update_option( 'gpi_qz_codificacion', in_array( $codif, [ 'cp858', 'cp850', 'cp1252' ], true ) ? $codif : 'cp858' );
        update_option( 'gpi_qz_codepage',     '' === $cp ? '' : (string) min( 255, absint( $cp ) ) );
        update_option( 'gpi_qz_cajon',        isset( $_POST['gpi_qz_cajon'] ) ? '1' : '0' );
        update_option( 'gpi_qz_copias',       min( 10, max( 1, absint( $_POST['gpi_qz_copias'] ?? 1 ) ) ) );
        update_option( 'gpi_qz_auto',         isset( $_POST['gpi_qz_auto'] ) ? '1' : '0' );
        update_option( 'gpi_qz_barcode',      isset( $_POST['gpi_qz_barcode'] ) ? '1' : '0' );
        update_option( 'gpi_qz_iva',          min( 100, max( 0, floatval( str_replace( ',', '.', wp_unslash( $_POST['gpi_qz_iva'] ?? '0' ) ) ) ) ) );
        update_option( 'gpi_ticket_comercio', sanitize_text_field( wp_unslash( $_POST['gpi_ticket_comercio'] ?? '' ) ) );
        update_option( 'gpi_ticket_cabecera', sanitize_textarea_field( wp_unslash( $_POST['gpi_ticket_cabecera'] ?? '' ) ) );
        update_option( 'gpi_ticket_pie',      sanitize_textarea_field( wp_unslash( $_POST['gpi_ticket_pie'] ?? '' ) ) );
        wp_redirect( admin_url( 'admin.php?page=gpi-ajustes&updated=1' ) );
        exit;
    }
}
