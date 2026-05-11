<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class GPI_Print {

    /**
     * Genera y envía la página HTML del resguardo optimizado para impresora de tickets.
     * Llámalo desde un action de admin (wp_ajax).
     */
    public static function render_ticket( $pedido_id ) {
        $pedido = GPI_Database::get_pedido( absint( $pedido_id ) );
        if ( ! $pedido ) wp_die( 'Pedido no encontrado.' );

        $tracking_url = GPI_Pedido::get_tracking_url( $pedido->numero );
        $qr_base64    = GPI_QR::base64( $tracking_url, 150 );
        $historial    = GPI_Database::get_historial( $pedido->id );
        $site_name    = get_bloginfo( 'name' );

        // Ancho estándar 80mm ≈ 302px a 96dpi; 58mm ≈ 219px
        $ancho = get_option( 'gpi_ticket_ancho', '80mm' );
        ob_start();
        include GPI_PLUGIN_DIR . 'templates/ticket.php';
        $html = ob_get_clean();

        // Cabeceras para que el navegador abra el diálogo de impresión
        header( 'Content-Type: text/html; charset=UTF-8' );
        header( 'X-Frame-Options: SAMEORIGIN' );
        echo $html; // phpcs:ignore WordPress.Security.EscapeOutput
        exit;
    }
}
