<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class GPI_Public {

    public function init() {
        add_shortcode( 'gpi_estado_pedido', [ $this, 'shortcode_estado' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    public function enqueue_assets() {
        if ( ! is_singular() ) return;
        global $post;
        if ( ! $post || ! has_shortcode( $post->post_content, 'gpi_estado_pedido' ) ) return;
        wp_enqueue_style( 'gpi-public', GPI_PLUGIN_URL . 'assets/css/public.css', [], GPI_VERSION );
    }

    /**
     * Shortcode [gpi_estado_pedido]
     */
    public function shortcode_estado( $atts ) {
        ob_start();
        $numero  = isset( $_GET['pedido'] ) ? sanitize_text_field( wp_unslash( $_GET['pedido'] ) ) : '';
        $pedido  = null;
        $error   = '';

        if ( ! empty( $numero ) ) {
            $pedido = GPI_Database::get_pedido_by_numero( $numero );
            if ( ! $pedido ) $error = 'No se encontró ningún pedido con ese número.';
        }

        include GPI_PLUGIN_DIR . 'templates/shortcode-tracking.php';
        return ob_get_clean();
    }
}
