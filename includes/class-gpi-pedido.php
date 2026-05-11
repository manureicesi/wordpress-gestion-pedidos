<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class GPI_Pedido {

    /**
     * Genera un número de pedido único: PED-YYYYMMDD-XXXX
     */
    public static function generar_numero() {
        global $wpdb;
        $prefix = 'PED-' . date( 'Ymd' ) . '-';
        $last   = $wpdb->get_var( $wpdb->prepare(
            "SELECT numero FROM {$wpdb->prefix}gpi_pedidos
             WHERE numero LIKE %s ORDER BY id DESC LIMIT 1",
            $wpdb->esc_like( $prefix ) . '%'
        ) );

        if ( $last ) {
            $seq = (int) substr( $last, -4 ) + 1;
        } else {
            $seq = 1;
        }
        return $prefix . str_pad( $seq, 4, '0', STR_PAD_LEFT );
    }

    /**
     * URL pública para consultar el estado del pedido.
     */
    public static function get_tracking_url( $numero ) {
        $page_id = get_option( 'gpi_tracking_page_id' );
        if ( $page_id ) {
            return add_query_arg( 'pedido', urlencode( $numero ), get_permalink( $page_id ) );
        }
        return add_query_arg( 'pedido', urlencode( $numero ), home_url( '/' ) );
    }
}
