<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class GPI_Estados {

    public static function get_all() {
        return GPI_Database::get_estados( false );
    }

    public static function get_activos() {
        return GPI_Database::get_estados( true );
    }

    public static function badge( $nombre, $color ) {
        $color   = esc_attr( $color );
        $nombre  = esc_html( $nombre );
        $text_c  = self::contraste( $color );
        return "<span class='gpi-badge' style='background:{$color};color:{$text_c}'>{$nombre}</span>";
    }

    private static function contraste( $hex ) {
        $hex = ltrim( $hex, '#' );
        if ( strlen( $hex ) === 3 ) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        $r = hexdec( substr( $hex, 0, 2 ) );
        $g = hexdec( substr( $hex, 2, 2 ) );
        $b = hexdec( substr( $hex, 4, 2 ) );
        $luminance = ( 0.299 * $r + 0.587 * $g + 0.114 * $b ) / 255;
        return $luminance > 0.5 ? '#1f2937' : '#ffffff';
    }
}
