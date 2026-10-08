# Gestión de Pedidos Internos — Plugin WordPress

Plugin WordPress para gestión de pedidos internos con control de estados, 
resguardo imprimible en impresora de tickets (thermal) y shortcode público de seguimiento.

Comando para crear directorio del plugin e instalarlo:

```
PLUGIN=gestion-pedidos-internos && DEST="$(pwd)/${PLUGIN}.zip" && zip -r "$DEST" . --exclude "*.git*" --exclude ".gitignore" --exclude "*.DS_Store" --exclude "README.md" && echo "✓ ZIP creado en $DEST"
```

---

## Características

- **Crear pedidos** con número único automático (`PED-YYYYMMDD-XXXX`)
- **Estados personalizables** (Pendiente → En Proceso → Listo → Entregado / Cancelado)
- **Cambio de estado inline** desde el listado de pedidos
- **Historial de estados** con fecha, usuario y nota
- **Resguardo de ticket térmico** optimizado para impresoras de 58mm / 80mm / 112mm
- **QR en el resguardo** con enlace a la página de seguimiento
- **Impresión ESC/POS directa con QZ Tray** (acentos, columnas, QR, código de barras, corte, cajón), con página de prueba y fallback al diálogo del navegador
- **Shortcode `[gpi_estado_pedido]`** para consulta pública por número de pedido
- **Filtros y búsqueda** en el panel de administración

---

## Instalación

1. Copia la carpeta `gestion-pedidos-internos` a `/wp-content/plugins/`
2. Activa el plugin desde **WordPress → Plugins**
3. El plugin creará automáticamente las tablas necesarias en la base de datos

---

## Configuración inicial

### 1. Página de seguimiento (shortcode)

1. Crea una nueva página en WordPress (p. ej. «Estado de mi Pedido»)
2. Añade el shortcode en el contenido: `[gpi_estado_pedido]`
3. Publica la página

### 2. Ajustes del plugin

Ve a **Pedidos → Ajustes**:

| Ajuste | Descripción |
|--------|-------------|
| Ancho del ticket | 58mm, 80mm (por defecto) o 112mm según tu impresora |
| Página de seguimiento | Selecciona la página donde añadiste el shortcode |

El ancho de la página de seguimiento se usará para generar el QR correcto en el ticket.

---

## Uso

### Crear un pedido

1. Ve a **Pedidos → Nuevo Pedido**
2. Rellena el formulario y guarda
3. El pedido aparece en el listado con estado **Pendiente**

### Cambiar el estado

Desde el listado de pedidos, usa el selector desplegable junto al badge de estado.
El cambio se aplica con AJAX al instante.

### Imprimir resguardo

Haz clic en el botón 🖨️ de la fila del pedido.
Se abrirá una ventana emergente con el ticket optimizado para impresora térmica.
El ticket incluye:

- Número de pedido
- Estado actual
- Solicitante y fecha
- Descripción y notas
- Historial de estados
- Código QR con enlace al seguimiento online

### Imprimir desde el navegador en una impresora de tickets

En la ventana del ticket:
1. Haz clic en el botón **«🖨️ Imprimir»**
2. En el diálogo de impresión del navegador:
   - Selecciona tu impresora de tickets (Epson TM, Star, Bixolon, etc.)
   - Desactiva «Cabeceras y pies de página»
   - Desactiva «Márgenes» (o ponlos a cero)
   - Activa «Gráficos de fondo» (para que imprima el color del estado)
3. Imprime

> **Tip**: Si usas la URL `?autoprint=1` la ventana lanzará el diálogo de impresión automáticamente.
> El botón del plugin ya lo añade.

---

## Shortcode de seguimiento

```
[gpi_estado_pedido]
```

Los visitantes pueden:
- Introducir el número de pedido en el formulario
- O acceder directamente mediante el enlace del QR: `https://tu-web.com/estado-pedido/?pedido=PED-20240101-0001`

---

## Tablas de base de datos

| Tabla | Descripción |
|-------|-------------|
| `wp_gpi_pedidos` | Pedidos con todos sus datos |
| `wp_gpi_estados` | Estados configurables |
| `wp_gpi_historial` | Registro de cambios de estado |

---

## Requisitos

- WordPress 6.0+
- PHP 7.4+
- MySQL 5.7+ o MariaDB 10.3+

---

## Personalización

### Añadir nuevos estados

Actualmente los estados se crean en la instalación. Para personalizarlos,
modifica el método `insert_default_estados()` en `includes/class-gpi-activator.php`
antes de activar el plugin por primera vez.

### Modificar el diseño del ticket

Edita `templates/ticket.php`. El CSS inline está diseñado para ser compatible
con los motores de impresión de los navegadores modernos y con impresoras térmicas.

---

## Estructura del proyecto

```
gestion-pedidos-internos/
├── gestion-pedidos-internos.php   # Archivo principal del plugin
├── includes/
│   ├── class-gpi-activator.php    # Activación: tablas y datos iniciales
│   ├── class-gpi-database.php     # Capa de acceso a base de datos
│   ├── class-gpi-pedido.php       # Helpers de pedido (numeración, URL)
│   ├── class-gpi-estados.php      # Helpers de estados (badge, contraste)
│   ├── class-gpi-qr.php           # Generador de QR (Google Charts API)
│   └── class-gpi-print.php        # Renderizador del ticket de impresión
├── admin/
│   ├── class-gpi-admin.php        # Menús, AJAX, ajustes
│   └── views/
│       ├── lista-pedidos.php      # Listado con filtros
│       ├── nuevo-pedido.php       # Formulario de creación
│       └── ajustes.php            # Página de ajustes
├── public/
│   └── class-gpi-public.php       # Shortcode público
├── templates/
│   ├── ticket.php                 # HTML del resguardo térmico
│   └── shortcode-tracking.php    # HTML del tracking público
└── assets/
    ├── css/
    │   ├── admin.css              # Estilos del panel
    │   └── public.css             # Estilos del shortcode
    └── js/
        └── admin.js               # AJAX del panel (jQuery)
```

---

## Impresión directa con QZ Tray

1. Instala y abre [QZ Tray](https://qz.io/download/) en el PC con la impresora térmica.
2. **Pedidos → Ajustes → Impresora térmica**: método «QZ Tray», pulsa **Detectar impresoras** y elige la tuya.
3. Pulsa **Imprimir página de prueba** y ajusta columnas y codificación hasta que la regla y los acentos salgan bien. Después, **Guardar ajustes**.

Sin certificado, QZ Tray pedirá permiso («Allow») en cada conexión. Para evitarlo, genera un certificado
(QZ Tray → Advanced → Site Manager / o el certificado comprado a QZ) y define en `wp-config.php` rutas fuera del directorio público:

```php
define( 'GPI_QZ_CERT_FILE', '/ruta/segura/digital-certificate.txt' );
define( 'GPI_QZ_KEY_FILE',  '/ruta/segura/private-key.pem' );
// define( 'GPI_QZ_KEY_PASS', 'contraseña' ); // si la clave está cifrada
```
