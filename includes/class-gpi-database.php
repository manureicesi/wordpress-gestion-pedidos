<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class GPI_Database {

    // ── PEDIDOS ────────────────────────────────────────────────────────────

    public static function get_pedidos( $args = [] ) {
        global $wpdb;
        $defaults = [
            'estado_id'          => null,
            'etiqueta_id'        => null,
            'excluir_estado_ids' => [],
            'search'             => '',
            'per_page'           => 20,
            'paged'              => 1,
            'orderby'            => 'creado_en',
            'order'              => 'DESC',
        ];
        $args   = wp_parse_args( $args, $defaults );
        $offset = ( $args['paged'] - 1 ) * $args['per_page'];

        $where  = '1=1';
        $values = [];

        if ( ! empty( $args['estado_id'] ) ) {
            $where    .= ' AND p.estado_id = %d';
            $values[]  = $args['estado_id'];
        }
        if ( ! empty( $args['etiqueta_id'] ) ) {
            $where    .= ' AND p.etiqueta_id = %d';
            $values[]  = $args['etiqueta_id'];
        }
        if ( ! empty( $args['excluir_estado_ids'] ) ) {
            $ids         = array_map( 'absint', (array) $args['excluir_estado_ids'] );
            $holders     = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
            $where      .= " AND p.estado_id NOT IN ($holders)";
            foreach ( $ids as $eid ) {
                $values[] = $eid;
            }
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
                       u.display_name AS creador_nombre,
                       et.nombre AS etiqueta_nombre, et.color AS etiqueta_color
                FROM {$wpdb->prefix}gpi_pedidos p
                LEFT JOIN {$wpdb->prefix}gpi_estados e ON p.estado_id = e.id
                LEFT JOIN {$wpdb->users} u ON p.creado_por = u.ID
                LEFT JOIN {$wpdb->prefix}gpi_etiquetas et ON p.etiqueta_id = et.id
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

    public static function get_all_pedidos_for_export( $args = [] ) {
        global $wpdb;
        $defaults = [
            'estado_id'          => null,
            'etiqueta_id'        => null,
            'excluir_estado_ids' => [],
            'search'             => '',
            'orderby'            => 'creado_en',
            'order'              => 'DESC',
        ];
        $args   = wp_parse_args( $args, $defaults );
        $where  = '1=1';
        $values = [];

        if ( ! empty( $args['estado_id'] ) ) {
            $where    .= ' AND p.estado_id = %d';
            $values[]  = $args['estado_id'];
        }
        if ( ! empty( $args['etiqueta_id'] ) ) {
            $where    .= ' AND p.etiqueta_id = %d';
            $values[]  = $args['etiqueta_id'];
        }
        if ( ! empty( $args['excluir_estado_ids'] ) ) {
            $ids     = array_map( 'absint', (array) $args['excluir_estado_ids'] );
            $holders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
            $where  .= " AND p.estado_id NOT IN ($holders)";
            foreach ( $ids as $eid ) {
                $values[] = $eid;
            }
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

        $sql = "SELECT p.*, e.nombre AS estado_nombre, et.nombre AS etiqueta_nombre
                FROM {$wpdb->prefix}gpi_pedidos p
                LEFT JOIN {$wpdb->prefix}gpi_estados e ON p.estado_id = e.id
                LEFT JOIN {$wpdb->prefix}gpi_etiquetas et ON p.etiqueta_id = et.id
                WHERE $where
                ORDER BY $orderby $order";

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
        if ( ! empty( $args['etiqueta_id'] ) ) {
            $where    .= ' AND etiqueta_id = %d';
            $values[]  = $args['etiqueta_id'];
        }
        if ( ! empty( $args['excluir_estado_ids'] ) ) {
            $ids     = array_map( 'absint', (array) $args['excluir_estado_ids'] );
            $holders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
            $where  .= " AND estado_id NOT IN ($holders)";
            foreach ( $ids as $eid ) {
                $values[] = $eid;
            }
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
                    u.display_name AS creador_nombre,
                    et.nombre AS etiqueta_nombre, et.color AS etiqueta_color
             FROM {$wpdb->prefix}gpi_pedidos p
             LEFT JOIN {$wpdb->prefix}gpi_estados e ON p.estado_id = e.id
             LEFT JOIN {$wpdb->users} u ON p.creado_por = u.ID
             LEFT JOIN {$wpdb->prefix}gpi_etiquetas et ON p.etiqueta_id = et.id
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
            'etiqueta_id' => isset( $data['etiqueta_id'] ) && $data['etiqueta_id'] > 0 ? absint( $data['etiqueta_id'] ) : null,
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
        if ( array_key_exists( 'etiqueta_id', $data ) ) {
            $update['etiqueta_id'] = ( $data['etiqueta_id'] > 0 ) ? absint( $data['etiqueta_id'] ) : null;
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

    public static function search_solicitantes( $q ) {
        global $wpdb;
        $like = '%' . $wpdb->esc_like( $q ) . '%';
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT solicitante, telefono, email, MAX(creado_en) AS ultimo
             FROM {$wpdb->prefix}gpi_pedidos
             WHERE solicitante LIKE %s
             GROUP BY solicitante, telefono, email
             ORDER BY ultimo DESC
             LIMIT 10",
            $like
        ) );
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

    public static function get_estados_ocultos_ids() {
        global $wpdb;
        $ids = $wpdb->get_col(
            "SELECT id FROM {$wpdb->prefix}gpi_estados WHERE ocultar_por_defecto = 1"
        );
        return array_map( 'intval', $ids );
    }

    public static function insert_estado_item( $data ) {
        global $wpdb;
        $nombre = sanitize_text_field( $data['nombre'] );
        $slug   = sanitize_title( $nombre );

        // Ensure unique slug
        $base = $slug;
        $i    = 1;
        while ( $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}gpi_estados WHERE slug = %s", $slug ) ) > 0 ) {
            $slug = $base . '-' . $i++;
        }

        $max_orden = (int) $wpdb->get_var( "SELECT MAX(orden) FROM {$wpdb->prefix}gpi_estados" );

        return $wpdb->insert( $wpdb->prefix . 'gpi_estados', [
            'nombre'              => $nombre,
            'slug'                => $slug,
            'color'               => sanitize_hex_color( $data['color'] ?? '#cccccc' ) ?: '#cccccc',
            'orden'               => $max_orden + 1,
            'activo'              => isset( $data['activo'] ) ? (int) (bool) $data['activo'] : 1,
            'ocultar_por_defecto' => isset( $data['ocultar_por_defecto'] ) ? (int) (bool) $data['ocultar_por_defecto'] : 0,
        ] );
    }

    public static function update_estado_item( $id, $data ) {
        global $wpdb;
        $update = [];
        if ( isset( $data['nombre'] ) ) {
            $update['nombre'] = sanitize_text_field( $data['nombre'] );
        }
        if ( isset( $data['color'] ) ) {
            $update['color'] = sanitize_hex_color( $data['color'] ) ?: '#cccccc';
        }
        if ( isset( $data['activo'] ) ) {
            $update['activo'] = (int) (bool) $data['activo'];
        }
        if ( isset( $data['ocultar_por_defecto'] ) ) {
            $update['ocultar_por_defecto'] = (int) (bool) $data['ocultar_por_defecto'];
        }
        if ( empty( $update ) ) return false;
        return $wpdb->update( $wpdb->prefix . 'gpi_estados', $update, [ 'id' => absint( $id ) ] ) !== false;
    }

    public static function delete_estado_item( $id ) {
        global $wpdb;
        $id    = absint( $id );
        $count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}gpi_pedidos WHERE estado_id = %d", $id
        ) );
        if ( $count > 0 ) {
            return new WP_Error( 'en_uso', "No se puede eliminar: {$count} pedido(s) usan este estado." );
        }
        $wpdb->delete( $wpdb->prefix . 'gpi_historial', [ 'estado_id' => $id ] );
        return $wpdb->delete( $wpdb->prefix . 'gpi_estados', [ 'id' => $id ] ) !== false;
    }

    public static function mover_estado( $id, $direction ) {
        global $wpdb;
        $id        = absint( $id );
        $table     = $wpdb->prefix . 'gpi_estados';
        $current   = $wpdb->get_row( $wpdb->prepare( "SELECT id, orden FROM $table WHERE id = %d", $id ) );
        if ( ! $current ) return false;

        if ( $direction === 'up' ) {
            $swap = $wpdb->get_row( $wpdb->prepare(
                "SELECT id, orden FROM $table WHERE orden < %d ORDER BY orden DESC LIMIT 1",
                $current->orden
            ) );
        } else {
            $swap = $wpdb->get_row( $wpdb->prepare(
                "SELECT id, orden FROM $table WHERE orden > %d ORDER BY orden ASC LIMIT 1",
                $current->orden
            ) );
        }

        if ( ! $swap ) return false;

        // Use transaction to ensure both updates happen atomically
        $wpdb->query( 'START TRANSACTION' );
        $result1 = $wpdb->update( $table, [ 'orden' => $swap->orden ],    [ 'id' => $current->id ] );
        $result2 = $wpdb->update( $table, [ 'orden' => $current->orden ], [ 'id' => $swap->id ] );
        
        if ( $result1 !== false && $result2 !== false ) {
            $wpdb->query( 'COMMIT' );
            return true;
        } else {
            $wpdb->query( 'ROLLBACK' );
            return false;
        }
    }

    // ── ETIQUETAS ─────────────────────────────────────────────────────────

    public static function get_etiquetas( $solo_activos = true ) {
        global $wpdb;
        $where = $solo_activos ? 'WHERE activo = 1' : '';
        return $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}gpi_etiquetas $where ORDER BY nombre ASC" );
    }

    public static function get_etiqueta( $id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}gpi_etiquetas WHERE id = %d", $id
        ) );
    }

    public static function insert_etiqueta( $data ) {
        global $wpdb;
        return $wpdb->insert( $wpdb->prefix . 'gpi_etiquetas', [
            'nombre' => sanitize_text_field( $data['nombre'] ),
            'color'  => sanitize_hex_color( $data['color'] ?? '#6b7280' ) ?: '#6b7280',
            'activo' => isset( $data['activo'] ) ? (int) (bool) $data['activo'] : 1,
        ] );
    }

    public static function update_etiqueta( $id, $data ) {
        global $wpdb;
        $update = [];
        if ( isset( $data['nombre'] ) ) {
            $update['nombre'] = sanitize_text_field( $data['nombre'] );
        }
        if ( isset( $data['color'] ) ) {
            $update['color'] = sanitize_hex_color( $data['color'] ) ?: '#6b7280';
        }
        if ( isset( $data['activo'] ) ) {
            $update['activo'] = (int) (bool) $data['activo'];
        }
        if ( empty( $update ) ) return false;
        return $wpdb->update( $wpdb->prefix . 'gpi_etiquetas', $update, [ 'id' => absint( $id ) ] ) !== false;
    }

    public static function delete_etiqueta( $id ) {
        global $wpdb;
        $id    = absint( $id );
        $count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}gpi_pedidos WHERE etiqueta_id = %d", $id
        ) );
        if ( $count > 0 ) {
            return new WP_Error( 'en_uso', "No se puede eliminar: {$count} pedido(s) usan esta etiqueta." );
        }
        return $wpdb->delete( $wpdb->prefix . 'gpi_etiquetas', [ 'id' => $id ] ) !== false;
    }
}
