<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class GPI_Database {

    // ── PEDIDOS ────────────────────────────────────────────────────────────

    public static function get_pedidos( $args = [] ) {
        global $wpdb;
        $defaults = [
            'estado_id'  => null,
            'search'     => '',
            'per_page'   => 20,
            'paged'      => 1,
            'orderby'    => 'creado_en',
            'order'      => 'DESC',
        ];
        $args   = wp_parse_args( $args, $defaults );
        $offset = ( $args['paged'] - 1 ) * $args['per_page'];

        $where  = '1=1';
        $values = [];

        if ( ! empty( $args['estado_id'] ) ) {
            $where    .= ' AND p.estado_id = %d';
            $values[]  = $args['estado_id'];
        }
        if ( ! empty( $args['search'] ) ) {
            $where    .= ' AND (p.numero LIKE %s OR p.solicitante LIKE %s OR p.descripcion LIKE %s)';
            $like      = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $values[]  = $like;
            $values[]  = $like;
            $values[]  = $like;
        }

        $allowed_order  = [ 'ASC', 'DESC' ];
        $allowed_fields = [ 'creado_en', 'actualizado_en', 'numero', 'solicitante', 'estado_id' ];
        $order   = in_array( strtoupper( $args['order'] ),   $allowed_order,  true ) ? strtoupper( $args['order'] ) : 'DESC';
        $orderby = in_array( $args['orderby'], $allowed_fields, true ) ? 'p.' . $args['orderby'] : 'p.creado_en';

        $sql = "SELECT p.*, e.nombre AS estado_nombre, e.color AS estado_color, e.slug AS estado_slug,
                       u.display_name AS creador_nombre
                FROM {$wpdb->prefix}gpi_pedidos p
                LEFT JOIN {$wpdb->prefix}gpi_estados e ON p.estado_id = e.id
                LEFT JOIN {$wpdb->users} u ON p.creado_por = u.ID
                WHERE $where
                ORDER BY $orderby $order
                LIMIT %d OFFSET %d";

        $values[] = $args['per_page'];
        $values[] = $offset;

        if ( ! empty( $values ) ) {
            $sql = $wpdb->prepare( $sql, ...$values );
        }

        return $wpdb->get_results( $sql );
    }

    public static function count_pedidos( $args = [] ) {
        global $wpdb;
        $where  = '1=1';
        $values = [];

        if ( ! empty( $args['estado_id'] ) ) {
            $where    .= ' AND estado_id = %d';
            $values[]  = $args['estado_id'];
        }
        if ( ! empty( $args['search'] ) ) {
            $where    .= ' AND (numero LIKE %s OR solicitante LIKE %s OR descripcion LIKE %s)';
            $like      = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $values[]  = $like; $values[] = $like; $values[] = $like;
        }

        $sql = "SELECT COUNT(*) FROM {$wpdb->prefix}gpi_pedidos WHERE $where";
        if ( ! empty( $values ) ) $sql = $wpdb->prepare( $sql, ...$values );
        return (int) $wpdb->get_var( $sql );
    }

    public static function get_pedido( $id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT p.*, e.nombre AS estado_nombre, e.color AS estado_color, e.slug AS estado_slug,
                    u.display_name AS creador_nombre
             FROM {$wpdb->prefix}gpi_pedidos p
             LEFT JOIN {$wpdb->prefix}gpi_estados e ON p.estado_id = e.id
             LEFT JOIN {$wpdb->users} u ON p.creado_por = u.ID
             WHERE p.id = %d", $id
        ) );
    }

    public static function get_pedido_by_numero( $numero ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT p.*, e.nombre AS estado_nombre, e.color AS estado_color, e.slug AS estado_slug
             FROM {$wpdb->prefix}gpi_pedidos p
             LEFT JOIN {$wpdb->prefix}gpi_estados e ON p.estado_id = e.id
             WHERE p.numero = %s", sanitize_text_field( $numero )
        ) );
    }

    public static function insert_pedido( $data ) {
        global $wpdb;
        $result = $wpdb->insert( $wpdb->prefix . 'gpi_pedidos', [
            'numero'      => $data['numero'],
            'solicitante' => sanitize_text_field( $data['solicitante'] ),
            'descripcion' => sanitize_textarea_field( $data['descripcion'] ),
            'estado_id'   => 1,
            'notas'       => isset( $data['notas'] ) ? sanitize_textarea_field( $data['notas'] ) : '',
            'presupuesto' => isset( $data['presupuesto'] ) ? floatval( $data['presupuesto'] ) : 0,
            'pagado'      => isset( $data['pagado'] ) ? (int) $data['pagado'] : 0,
            'telefono'    => isset( $data['telefono'] ) ? sanitize_text_field( $data['telefono'] ) : '',
            'email'       => isset( $data['email'] ) ? sanitize_email( $data['email'] ) : '',
            'creado_por'  => get_current_user_id(),
        ] );
        if ( $result ) {
            $id = $wpdb->insert_id;
            self::add_historial( $id, 1, 'Pedido creado' );
            return $id;
        }
        if ( $wpdb->last_error ) {
            error_log( 'GPI insert_pedido error: ' . $wpdb->last_error );
        }
        return false;
    }

    public static function update_estado( $pedido_id, $estado_id, $nota = '' ) {
        global $wpdb;
        $updated = $wpdb->update(
            $wpdb->prefix . 'gpi_pedidos',
            [ 'estado_id' => absint( $estado_id ) ],
            [ 'id'        => absint( $pedido_id ) ]
        );
        if ( $updated !== false ) {
            self::add_historial( $pedido_id, $estado_id, $nota );
            return true;
        }
        return false;
    }

    public static function update_pedido( $id, $data ) {
        global $wpdb;
        $update = [];
        if ( isset( $data['solicitante'] ) ) {
            $update['solicitante'] = sanitize_text_field( $data['solicitante'] );
        }
        if ( isset( $data['descripcion'] ) ) {
            $update['descripcion'] = sanitize_textarea_field( $data['descripcion'] );
        }
        if ( isset( $data['notas'] ) ) {
            $update['notas'] = sanitize_textarea_field( $data['notas'] );
        }
        if ( isset( $data['presupuesto'] ) ) {
            $update['presupuesto'] = floatval( $data['presupuesto'] );
        }
        if ( isset( $data['pagado'] ) ) {
            $update['pagado'] = (int) $data['pagado'];
        }
        if ( isset( $data['telefono'] ) ) {
            $update['telefono'] = sanitize_text_field( $data['telefono'] );
        }
        if ( isset( $data['email'] ) ) {
            $update['email'] = sanitize_email( $data['email'] );
        }
        if ( empty( $update ) ) {
            return false;
        }
        $update['actualizado_en'] = current_time( 'mysql' );
        $result = $wpdb->update(
            $wpdb->prefix . 'gpi_pedidos',
            $update,
            [ 'id' => absint( $id ) ]
        );
        if ( $result === false && $wpdb->last_error ) {
            error_log( 'GPI update_pedido error: ' . $wpdb->last_error );
        }
        return $result !== false;
    }

    public static function delete_pedido( $id ) {
        global $wpdb;
        $wpdb->delete( $wpdb->prefix . 'gpi_historial', [ 'pedido_id' => absint( $id ) ] );
        return $wpdb->delete( $wpdb->prefix . 'gpi_pedidos', [ 'id' => absint( $id ) ] );
    }

    // ── HISTORIAL ─────────────────────────────────────────────────────────

    public static function add_historial( $pedido_id, $estado_id, $nota = '' ) {
        global $wpdb;
        $wpdb->insert( $wpdb->prefix . 'gpi_historial', [
            'pedido_id'  => absint( $pedido_id ),
            'estado_id'  => absint( $estado_id ),
            'nota'       => sanitize_textarea_field( $nota ),
            'usuario_id' => get_current_user_id(),
        ] );
    }

    public static function get_historial( $pedido_id ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT h.*, e.nombre AS estado_nombre, e.color AS estado_color, u.display_name AS usuario
             FROM {$wpdb->prefix}gpi_historial h
             LEFT JOIN {$wpdb->prefix}gpi_estados e ON h.estado_id = e.id
             LEFT JOIN {$wpdb->users} u ON h.usuario_id = u.ID
             WHERE h.pedido_id = %d
             ORDER BY h.fecha ASC", $pedido_id
        ) );
    }

    // ── ESTADOS ───────────────────────────────────────────────────────────

    public static function get_estados( $solo_activos = true ) {
        global $wpdb;
        $where = $solo_activos ? 'WHERE activo = 1' : '';
        return $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}gpi_estados $where ORDER BY orden ASC" );
    }

    public static function get_estado( $id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}gpi_estados WHERE id = %d", $id
        ) );
    }
}
