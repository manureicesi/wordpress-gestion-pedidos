<?php
/**
 * Plugin Name:       Gestión de Pedidos Internos
 * Plugin URI:        https://reices.com/gestion-pedidos-internos
 * Description:       Sistema de gestión de pedidos internos con control de estados, impresión de resguardos en impresora de tickets y shortcode de seguimiento.
 * Version:           1.2.0
 * Author:            Manuel Reices
 * Text Domain:       gpi
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'GPI_VERSION',     '1.1.0' );
define( 'GPI_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'GPI_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );
define( 'GPI_PLUGIN_FILE', __FILE__ );

require_once GPI_PLUGIN_DIR . 'includes/class-gpi-activator.php';
require_once GPI_PLUGIN_DIR . 'includes/class-gpi-database.php';
require_once GPI_PLUGIN_DIR . 'includes/class-gpi-pedido.php';
require_once GPI_PLUGIN_DIR . 'includes/class-gpi-estados.php';
require_once GPI_PLUGIN_DIR . 'includes/class-gpi-qr.php';
require_once GPI_PLUGIN_DIR . 'admin/class-gpi-admin.php';
require_once GPI_PLUGIN_DIR . 'public/class-gpi-public.php';
require_once GPI_PLUGIN_DIR . 'includes/class-gpi-print.php';

register_activation_hook( __FILE__,   [ 'GPI_Activator', 'activate'   ] );
register_deactivation_hook( __FILE__, [ 'GPI_Activator', 'deactivate' ] );

function gpi_run() {
    GPI_Activator::maybe_upgrade();
    $admin  = new GPI_Admin();
    $public = new GPI_Public();
    $admin->init();
    $public->init();
}
add_action( 'plugins_loaded', 'gpi_run' );
