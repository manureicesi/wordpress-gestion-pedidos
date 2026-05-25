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

        // Estados AJAX
        add_action( 'wp_ajax_gpi_save_estado_item',   [ $this, 'ajax_save_estado_item' ] );
        add_action( 'wp_ajax_gpi_delete_estado_item', [ $this, 'ajax_delete_estado_item' ] );
        add_action( 'wp_ajax_gpi_mover_estado',       [ $this, 'ajax_mover_estado' ] );

        // Etiquetas AJAX
        add_action( 'wp_ajax_gpi_save_etiqueta',   [ $this, 'ajax_save_etiqueta' ] );
        add_action( 'wp_ajax_gpi_delete_etiqueta', [ $this, 'ajax_delete_etiqueta' ] );

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
        wp_enqueue_script( 'gpi-admin', GPI_PLUGIN_URL . 'assets/js/admin.js', [ 'jquery' ], GPI_VERSION, true );
        wp_localize_script( 'gpi-admin', 'GPI', [
            'ajax_url'   => admin_url( 'admin-ajax.php' ),
            'nonce'      => wp_create_nonce( 'gpi_nonce' ),
            'print_url'  => admin_url( 'admin-ajax.php?action=gpi_imprimir_ticket&nonce=' . wp_create_nonce( 'gpi_print' ) ),
            'export_url' => admin_url( 'admin-post.php?action=gpi_export_csv&_wpnonce=' . wp_create_nonce( 'gpi_export_csv' ) ),
        ] );
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
        wp_redirect( admin_url( 'admin.php?page=gpi-ajustes&updated=1' ) );
        exit;
    }
}
