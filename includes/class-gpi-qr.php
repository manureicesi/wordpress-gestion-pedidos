<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class GPI_QR {

    /**
     * Devuelve la URL de imagen del QR usando la API de QRServer.
     * No requiere librerías externas.
     *
     * @param  string $data   Texto o URL a codificar.
     * @param  int    $size   Tamaño en píxeles (ancho=alto).
     * @return string         URL de la imagen QR.
     */
    public static function url( $data, $size = 120 ) {
        return add_query_arg( [
            'size' => $size . 'x' . $size,
            'data' => rawurlencode( $data ),
        ], 'https://api.qrserver.com/v1/create-qr-code/' );
    }

    /**
     * Etiqueta <img> lista para usar en el resguardo impreso.
     */
    public static function img_tag( $data, $size = 120, $alt = 'QR' ) {
        $url = self::url( $data, $size );
        return sprintf(
            '<img src="%s" alt="%s" width="%d" height="%d" class="gpi-qr">',
            esc_url( $url ),
            esc_attr( $alt ),
            $size, $size
        );
    }

    /**
     * Descarga el QR como imagen base64 para incrustarlo en el HTML de impresión
     * (evita petición externa en el momento de imprimir).
     */
    public static function base64( $data, $size = 120 ) {
        $url      = self::url( $data, $size );
        $response = wp_remote_get( $url, [ 'timeout' => 8 ] );
        if ( is_wp_error( $response ) ) return '';
        $body = wp_remote_retrieve_body( $response );
        if ( ! $body ) return '';
        $mime = wp_remote_retrieve_header( $response, 'content-type' );
        $mime = explode( ';', $mime )[0];
        return 'data:' . $mime . ';base64,' . base64_encode( $body );
    }
}
