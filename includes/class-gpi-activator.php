<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class GPI_Activator {

    public static function activate() {
        self::create_tables();
        self::insert_default_estados();
        flush_rewrite_rules();
    }

    public static function deactivate() {
        flush_rewrite_rules();
    }

    public static function maybe_upgrade() {
        global $wpdb;
        $table = $wpdb->prefix . 'gpi_pedidos';
        $cols  = $wpdb->get_col( "DESCRIBE $table" );
        if ( ! in_array( 'presupuesto', $cols ) ) {
            $wpdb->query( "ALTER TABLE $table ADD COLUMN presupuesto DECIMAL(10,2) NOT NULL DEFAULT 0" );
        }
        if ( ! in_array( 'pagado', $cols ) ) {
            $wpdb->query( "ALTER TABLE $table ADD COLUMN pagado TINYINT(1) NOT NULL DEFAULT 0" );
        }
        if ( ! in_array( 'telefono', $cols ) ) {
            $wpdb->query( "ALTER TABLE $table ADD COLUMN telefono VARCHAR(30) NOT NULL DEFAULT ''" );
            error_log( 'GPI upgrade: columna telefono añadida' );
        }
        if ( ! in_array( 'email', $cols ) ) {
            $wpdb->query( "ALTER TABLE $table ADD COLUMN email VARCHAR(150) NOT NULL DEFAULT ''" );
            error_log( 'GPI upgrade: columna email añadida' );
        }
    }

    private static function create_tables() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        $sql_pedidos = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}gpi_pedidos (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            numero      VARCHAR(20)     NOT NULL UNIQUE,
            solicitante VARCHAR(150)    NOT NULL,
            descripcion TEXT            NOT NULL,
            estado_id   SMALLINT UNSIGNED NOT NULL DEFAULT 1,
            notas       TEXT,
            creado_por  BIGINT UNSIGNED NOT NULL,
            creado_en   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            actualizado_en DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            presupuesto DECIMAL(10,2)   NOT NULL DEFAULT 0,
            pagado      TINYINT(1)      NOT NULL DEFAULT 0,
            telefono    VARCHAR(30)     NOT NULL DEFAULT '',
            email       VARCHAR(150)    NOT NULL DEFAULT '',
            PRIMARY KEY (id),
            KEY idx_numero (numero),
            KEY idx_estado (estado_id),
            KEY idx_creado (creado_en)
        ) $charset;";

        $sql_estados = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}gpi_estados (
            id          SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
            nombre      VARCHAR(80)  NOT NULL,
            slug        VARCHAR(80)  NOT NULL UNIQUE,
            color       VARCHAR(7)   NOT NULL DEFAULT '#cccccc',
            orden       TINYINT      NOT NULL DEFAULT 0,
            activo      TINYINT(1)   NOT NULL DEFAULT 1,
            PRIMARY KEY (id)
        ) $charset;";

        $sql_historial = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}gpi_historial (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            pedido_id   BIGINT UNSIGNED NOT NULL,
            estado_id   SMALLINT UNSIGNED NOT NULL,
            nota        TEXT,
            usuario_id  BIGINT UNSIGNED NOT NULL,
            fecha       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_pedido (pedido_id)
        ) $charset;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql_pedidos );
        dbDelta( $sql_estados );
        dbDelta( $sql_historial );
    }

    private static function insert_default_estados() {
        global $wpdb;
        $table = $wpdb->prefix . 'gpi_estados';
        if ( (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table" ) > 0 ) return;

        $estados = [
            [ 'Pendiente',  'pendiente',  '#f59e0b', 1 ],
            [ 'En Curso',   'en-curso',   '#3b82f6', 2 ],
            [ 'En Taller',  'en-taller',  '#8b5cf6', 3 ],
            [ 'Listo',      'listo',      '#10b981', 4 ],
            [ 'Entregado',  'entregado',  '#6b7280', 5 ],
            [ 'Cancelado',  'cancelado',  '#ef4444', 6 ],
        ];

        foreach ( $estados as $e ) {
            $wpdb->insert( $table, [
                'nombre' => $e[0],
                'slug'   => $e[1],
                'color'  => $e[2],
                'orden'  => $e[3],
            ] );
        }
    }
}
