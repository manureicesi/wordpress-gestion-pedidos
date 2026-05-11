<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class GPI_Admin {

    public function init() {
        add_action( 'admin_menu',             [ $this, 'register_menus' ] );
        add_action( 'admin_enqueue_scripts',  [ $this, 'enqueue_assets' ] );
        add_action( 'wp_ajax_gpi_crear_pedido',    [ $this, 'ajax_crear_pedido' ] );
        add_action( 'wp_ajax_gpi_cambiar_estado',  [ $this, 'ajax_cambiar_estado' ] );
        add_action( 'wp_ajax_gpi_eliminar_pedido', [ $this, 'ajax_eliminar_pedido' ] );
        add_action( 'wp_ajax_gpi_imprimir_ticket', [ $this, 'ajax_imprimir_ticket' ] );
        add_action( 'admin_post_gpi_save_settings', [ $this, 'save_settings' ] );
    }

    // ── MENÚS ─────────────────────────────────────────────────────────────

    public function register_menus() {
        add_menu_page(
            'Pedidos Internos', 'Pedidos', 'manage_options',
            'gpi-pedidos', [ $this, 'page_lista_pedidos' ],
            'dashicons-clipboard', 26
        );
        add_submenu_page(
            'gpi-pedidos', 'Nuevo Pedido', 'Nuevo Pedido',
            'manage_options', 'gpi-nuevo-pedido', [ $this, 'page_nuevo_pedido' ]
        );
        add_submenu_page(
            'gpi-pedidos', 'Ajustes', 'Ajustes',
            'manage_options', 'gpi-ajustes', [ $this, 'page_ajustes' ]
        );
    }

    // ── ASSETS ────────────────────────────────────────────────────────────

    public function enqueue_assets( $hook ) {
        if ( strpos( $hook, 'gpi-' ) === false ) return;
        wp_enqueue_style( 'gpi-admin', GPI_PLUGIN_URL . 'assets/css/admin.css', [], GPI_VERSION );
        wp_enqueue_script( 'gpi-admin', GPI_PLUGIN_URL . 'assets/js/admin.js', [ 'jquery' ], GPI_VERSION, true );
        wp_localize_script( 'gpi-admin', 'GPI', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'gpi_nonce' ),
            'print_url'=> admin_url( 'admin-ajax.php?action=gpi_imprimir_ticket&nonce=' . wp_create_nonce( 'gpi_print' ) ),
        ] );
    }

    // ── PÁGINAS ───────────────────────────────────────────────────────────

    public function page_lista_pedidos() {
        $estado_id = isset( $_GET['estado'] ) ? absint( $_GET['estado'] ) : null;
        $search    = isset( $_GET['s'] )       ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
        $paged     = isset( $_GET['paged'] )   ? absint( $_GET['paged'] ) : 1;
        $per_page  = 20;

        $args    = compact( 'estado_id', 'search', 'paged', 'per_page' );
        $pedidos = GPI_Database::get_pedidos( $args );
        $total   = GPI_Database::count_pedidos( $args );
        $estados = GPI_Database::get_estados();
        $pages   = ceil( $total / $per_page );

        include GPI_PLUGIN_DIR . 'admin/views/lista-pedidos.php';
    }

    public function page_nuevo_pedido() {
        $estados = GPI_Database::get_estados();
        include GPI_PLUGIN_DIR . 'admin/views/nuevo-pedido.php';
    }

    public function page_ajustes() {
        include GPI_PLUGIN_DIR . 'admin/views/ajustes.php';
    }

    // ── AJAX ──────────────────────────────────────────────────────────────

    private function verify_nonce() {
        check_ajax_referer( 'gpi_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Sin permiso.', 403 );
    }

    public function ajax_crear_pedido() {
        $this->verify_nonce();
        $numero = GPI_Pedido::generar_numero();
        $id     = GPI_Database::insert_pedido( [
            'numero'      => $numero,
            'solicitante' => sanitize_text_field( wp_unslash( $_POST['solicitante'] ?? '' ) ),
            'descripcion' => sanitize_textarea_field( wp_unslash( $_POST['descripcion'] ?? '' ) ),
            'notas'       => sanitize_textarea_field( wp_unslash( $_POST['notas'] ?? '' ) ),
        ] );
        if ( $id ) {
            wp_send_json_success( [ 'id' => $id, 'numero' => $numero, 'redirect' => admin_url( 'admin.php?page=gpi-pedidos' ) ] );
        } else {
            wp_send_json_error( 'Error al crear el pedido.' );
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
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Sin permiso.' );
        $id = absint( $_GET['pedido_id'] ?? 0 );
        GPI_Print::render_ticket( $id );
    }

    // ── AJUSTES ───────────────────────────────────────────────────────────

    public function save_settings() {
        check_admin_referer( 'gpi_save_settings' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Sin permiso.' );
        update_option( 'gpi_ticket_ancho',    sanitize_text_field( wp_unslash( $_POST['gpi_ticket_ancho']    ?? '80mm' ) ) );
        update_option( 'gpi_tracking_page_id', absint( $_POST['gpi_tracking_page_id'] ?? 0 ) );
        wp_redirect( admin_url( 'admin.php?page=gpi-ajustes&updated=1' ) );
        exit;
    }
}
