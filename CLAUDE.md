# Gestión de Pedidos Internos — Contexto del Proyecto

## Descripción
Plugin de WordPress para gestión interna de pedidos. Permite crear, editar y hacer seguimiento de pedidos con historial de estados, resguardos imprimibles y página de tracking pública para clientes.

## Stack
- PHP (WordPress plugin)
- jQuery (sin frameworks JS adicionales)
- CSS puro
- MySQL vía `$wpdb`

## Estructura de archivos
```
gestion-pedidos-internos.php        Plugin entry point, constants, hooks
includes/
  class-gpi-activator.php           Activación, tablas DB, migraciones (maybe_upgrade)
  class-gpi-database.php            Toda la lógica de acceso a datos (estático)
  class-gpi-estados.php             Helper para badges de estado (contraste color)
  class-gpi-pedido.php              Generación de número de pedido, URL tracking
  class-gpi-qr.php                  Generación de QR para tickets
  class-gpi-print.php               Renderizado del ticket de impresión
admin/
  class-gpi-admin.php               Menús WP, AJAX handlers, lógica admin
  views/
    lista-pedidos.php               Vista principal con tabla + filtros
    nuevo-pedido.php                Formulario de creación
    editar-pedido.php               Formulario de edición + historial
    ajustes.php                     Config general + gestión estados + etiquetas
public/
  class-gpi-public.php              Shortcode [gpi_estado_pedido] para tracking
templates/
  shortcode-tracking.php            Plantilla pública del shortcode
  ticket.php                        Plantilla del ticket imprimible
assets/
  css/admin.css                     Estilos admin
  css/public.css                    Estilos frontend
  js/admin.js                       JS admin (jQuery AJAX)
```

## Tablas en la BD
- `{prefix}gpi_pedidos` — pedidos con campos: id, numero, solicitante, descripcion, estado_id, etiqueta_id, notas, creado_por, creado_en, actualizado_en, presupuesto, pagado, telefono, email
- `{prefix}gpi_estados` — estados con: id, nombre, slug, color, orden, activo, ocultar_por_defecto
- `{prefix}gpi_etiquetas` — etiquetas con: id, nombre, color, activo
- `{prefix}gpi_historial` — historial de cambios de estado por pedido

## Constantes
- `GPI_VERSION` — versión del plugin
- `GPI_PLUGIN_DIR` — ruta absoluta del directorio
- `GPI_PLUGIN_URL` — URL del directorio

## Permisos
- Capacidad requerida: `manage_woocommerce`
- Rol personalizado `gpi_viewer` para visualización

## AJAX actions (admin)
- `gpi_crear_pedido` — crear pedido nuevo
- `gpi_editar_pedido` — actualizar datos de pedido
- `gpi_cambiar_estado` — cambiar estado de un pedido (registra historial)
- `gpi_eliminar_pedido` — eliminar pedido y su historial
- `gpi_imprimir_ticket` — renderizar ticket (ventana popup)
- `gpi_toggle_pagado` — toggle campo pagado
- `gpi_save_etiqueta` — crear/actualizar etiqueta
- `gpi_delete_etiqueta` — eliminar etiqueta
- `gpi_save_estado_item` — crear/actualizar estado
- `gpi_delete_estado_item` — eliminar estado (rechaza si tiene pedidos)
- `gpi_mover_estado` — reordenar estado (up/down)

## admin-post actions
- `gpi_save_settings` — guardar ajustes generales
- `gpi_export_csv` — descargar CSV de pedidos filtrados

## Flujo de datos principal
1. `plugins_loaded` → `gpi_run()` → `GPI_Activator::maybe_upgrade()` (migraciones) + init Admin + Public
2. Admin pages: `GPI_Admin::page_*()` carga datos con `GPI_Database::*()` e incluye la vista PHP correspondiente
3. AJAX: JS en admin.js envía POST a admin-ajax.php → handler en `GPI_Admin` → `GPI_Database` → JSON response

## Opciones de WordPress (wp_options)
- `gpi_ticket_ancho` — ancho del ticket: '58mm', '80mm', '112mm'
- `gpi_tracking_page_id` — ID de página con el shortcode de tracking

## Patrones a seguir
- Todo acceso a DB pasa por `GPI_Database` (métodos estáticos)
- Nonce para AJAX: `gpi_nonce` (verificado con `check_ajax_referer`)
- Nonce para admin-post: nombre de la acción
- Sanitización: `sanitize_text_field`, `sanitize_textarea_field`, `sanitize_email`, `absint`, `floatval`
- No usar `echo` directo en vistas — usar `esc_html()`, `esc_attr()`, `esc_url()`
- Migraciones de columnas en `GPI_Activator::maybe_upgrade()`

## Comportamiento especial
- Los estados con `ocultar_por_defecto = 1` (ej. "Listo") se ocultan de la lista por defecto
- Para verlos: usar filtro de estado específico o checkbox "Mostrar listos" en la lista
- El CSV exportado respeta los mismos filtros que la vista actual (estado, etiqueta, búsqueda, mostrar_listos)
- Las etiquetas son opcionales en los pedidos (etiqueta_id nullable)
