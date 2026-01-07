# Plugin WordPress - Conversor Masivo de Imágenes a WebP/AVIF

## Información General del Plugin

**Nombre del plugin:** WP Image Format Converter  
**Descripción:** Plugin para conversión masiva de imágenes a formatos modernos (WebP, AVIF) con procesamiento por lotes inteligente  
**Versión inicial:** 1.0.0  
**Requisitos mínimos:** WordPress 5.8+, PHP 7.4+
**Autor:** David Caro 

---

## Arquitectura Técnica

### 1. Estructura de Archivos del Plugin

```
wp-image-format-converter/
├── wp-image-format-converter.php (archivo principal)
├── includes/
│   ├── class-converter.php (lógica de conversión)
│   ├── class-batch-processor.php (procesamiento por lotes)
│   ├── class-image-analyzer.php (detectar formatos, validaciones)
│   └── class-settings.php (configuración y opciones)
├── admin/
│   ├── class-admin-page.php (página de administración)
│   ├── ajax-handlers.php (manejadores AJAX)
│   └── views/
│       └── admin-page.php (template HTML del admin)
├── assets/
│   ├── css/
│   │   └── admin-style.css
│   └── js/
│       └── admin-script.js
└── languages/ (internacionalización)
```

---

## 2. Archivo Principal (wp-image-format-converter.php)

### Encabezado del plugin:
```php
/**
 * Plugin Name: WP Image Format Converter
 * Description: Convierte masivamente imágenes a WebP y AVIF con procesamiento inteligente
 * Version: 1.0.0
 * Author: David Caro
 * Text Domain: wp-image-converter
 */
```

### Funcionalidades:
- Definir constantes del plugin (ruta, URL, versión)
- Require de todas las clases necesarias
- Hook de activación para crear tablas/opciones si es necesario
- Hook de desactivación para limpiar cron jobs
- Inicializar el plugin con `plugins_loaded`

---

## 3. Clase Converter (class-converter.php)

### Responsabilidad:
Manejar la conversión real de imágenes usando ImageMagick o GD Library.

### Métodos principales:

#### `check_conversion_support()`
- Detectar si ImageMagick está disponible
- Verificar soporte de WebP y AVIF
- Retornar array con capacidades: `['imagick' => bool, 'webp' => bool, 'avif' => bool]`

#### `convert_image($attachment_id, $format, $quality)`
**Parámetros:**
- `$attachment_id` (int): ID del attachment de WordPress
- `$format` (string): 'webp' o 'avif'
- `$quality` (int): 1-100, calidad de compresión

**Proceso:**
1. Obtener ruta del archivo original con `get_attached_file()`
2. Validar que el archivo existe
3. Verificar que es una imagen (MIME type)
4. Detectar si usar Imagick o GD
5. Cargar imagen en memoria
6. Convertir al formato destino
7. Guardar nueva imagen en mismo directorio con sufijo (ej: `imagen.webp`)
8. Crear metadata para el nuevo archivo
9. Retornar: `['success' => bool, 'file_path' => string, 'message' => string]`

#### `convert_with_imagick($source_path, $dest_path, $format, $quality)`
- Usar clase `Imagick` de PHP
- Aplicar formato: `$image->setImageFormat($format)`
- Configurar calidad: `$image->setImageCompressionQuality($quality)`
- Preservar perfil de color y metadatos EXIF si se configuró
- Escribir archivo: `$image->writeImage($dest_path)`

#### `convert_with_gd($source_path, $dest_path, $format, $quality)`
- Cargar imagen con `imagecreatefromjpeg/png/gif()`
- Convertir a WebP: `imagewebp()`
- Para AVIF, mostrar error ya que GD no lo soporta nativamente
- Liberar memoria: `imagedestroy()`

#### `generate_converted_filename($original_path, $format)`
- Tomar ruta original: `/uploads/2024/01/foto.jpg`
- Generar nueva: `/uploads/2024/01/foto.webp`
- Retornar la ruta completa

---

## 4. Clase Batch Processor (class-batch-processor.php)

### Responsabilidad:
Gestionar la conversión masiva sin saturar el servidor.

### Dependencia:
Usar **Action Scheduler** (incluido en WooCommerce, o incluir librería standalone)

### Métodos principales:

#### `start_batch_conversion($args)`
**Parámetros en $args:**
```php
[
    'formats' => ['webp', 'avif'], // formatos a generar
    'quality' => 85,
    'keep_original' => true,
    'preserve_metadata' => true,
    'batch_size' => 5 // imágenes por lote
]
```

**Proceso:**
1. Obtener todos los attachments de tipo imagen
2. Filtrar ya procesados (guardar en post_meta)
3. Dividir en lotes de 5 imágenes
4. Programar trabajos con Action Scheduler:
   ```php
   as_enqueue_async_action(
       'wpifc_process_batch',
       ['attachment_ids' => [1,2,3,4,5], 'args' => $args],
       'wpifc-conversion'
   )
   ```

#### `process_batch($attachment_ids, $args)`
- Recibir lote de IDs
- Iterar cada uno y llamar a `Converter::convert_image()`
- Guardar resultado en post_meta: `_wpifc_converted`
- Actualizar contador de progreso en opciones de WP
- Si falla alguna conversión, registrar en log

#### `get_conversion_progress()`
- Retornar: `['total' => int, 'processed' => int, 'failed' => int, 'percentage' => float]`
- Leer de opciones de WP que se actualizan en tiempo real

#### `cancel_batch_conversion()`
- Cancelar todos los trabajos pendientes en Action Scheduler
- Limpiar opciones de progreso

---

## 5. Clase Admin Page (class-admin-page.php)

### Responsabilidad:
Crear la interfaz de administración.

### Hook principal:
```php
add_action('admin_menu', [$this, 'add_menu_page']);
```

### Métodos:

#### `add_menu_page()`
```php
add_menu_page(
    'Image Converter',
    'Image Converter',
    'manage_options',
    'wp-image-converter',
    [$this, 'render_admin_page'],
    'dashicons-images-alt2'
);
```

#### `render_admin_page()`
- Incluir el template `views/admin-page.php`
- Pasar datos: total de imágenes, formatos soportados, progreso actual

---

## 6. Template Admin (views/admin-page.php)

### Estructura HTML:

```html
<div class="wrap wpifc-admin">
    <h1>Conversor Masivo de Imágenes</h1>
    
    <!-- Sección 1: Estadísticas -->
    <div class="wpifc-stats">
        <div class="stat-box">
            <span class="stat-number"><?php echo $total_images; ?></span>
            <span class="stat-label">Imágenes en biblioteca</span>
        </div>
        <div class="stat-box">
            <span class="stat-number"><?php echo $converted_images; ?></span>
            <span class="stat-label">Ya convertidas</span>
        </div>
    </div>
    
    <!-- Sección 2: Configuración -->
    <div class="wpifc-settings">
        <h2>Configuración de Conversión</h2>
        
        <label>
            <input type="checkbox" name="format_webp" checked> 
            Convertir a WebP
        </label>
        
        <label>
            <input type="checkbox" name="format_avif"> 
            Convertir a AVIF
            <?php if (!$avif_supported): ?>
                <span class="warning">⚠️ AVIF no soportado en este servidor</span>
            <?php endif; ?>
        </label>
        
        <label>
            Calidad de compresión:
            <input type="range" name="quality" min="1" max="100" value="85">
            <span id="quality-value">85</span>
        </label>
        
        <label>
            <input type="checkbox" name="keep_original" checked>
            Mantener imágenes originales
        </label>
        
        <label>
            <input type="checkbox" name="preserve_metadata" checked>
            Preservar metadatos EXIF
        </label>
    </div>
    
    <!-- Sección 3: Acciones -->
    <div class="wpifc-actions">
        <button id="start-conversion" class="button button-primary button-large">
            Iniciar Conversión Masiva
        </button>
        
        <button id="cancel-conversion" class="button button-secondary" style="display:none;">
            Cancelar
        </button>
    </div>
    
    <!-- Sección 4: Progreso -->
    <div id="conversion-progress" style="display:none;">
        <h3>Conversión en progreso...</h3>
        <div class="progress-bar">
            <div class="progress-fill" style="width: 0%"></div>
        </div>
        <p class="progress-text">0 de 0 imágenes procesadas (0%)</p>
        <div class="progress-log"></div>
    </div>
</div>
```

---

## 7. JavaScript (assets/js/admin-script.js)

### Funcionalidades:

#### Iniciar conversión:
```javascript
jQuery('#start-conversion').on('click', function() {
    // Recoger configuración
    const config = {
        formats: [],
        quality: jQuery('input[name="quality"]').val(),
        keep_original: jQuery('input[name="keep_original"]').is(':checked'),
        preserve_metadata: jQuery('input[name="preserve_metadata"]').is(':checked')
    };
    
    if (jQuery('input[name="format_webp"]').is(':checked')) {
        config.formats.push('webp');
    }
    if (jQuery('input[name="format_avif"]').is(':checked')) {
        config.formats.push('avif');
    }
    
    // Validar
    if (config.formats.length === 0) {
        alert('Selecciona al menos un formato');
        return;
    }
    
    // Llamada AJAX
    jQuery.post(ajaxurl, {
        action: 'wpifc_start_conversion',
        nonce: wpifc_vars.nonce,
        config: config
    }, function(response) {
        if (response.success) {
            jQuery('#conversion-progress').show();
            startProgressPolling();
        }
    });
});
```

#### Polling de progreso:
```javascript
function startProgressPolling() {
    const interval = setInterval(function() {
        jQuery.post(ajaxurl, {
            action: 'wpifc_get_progress',
            nonce: wpifc_vars.nonce
        }, function(response) {
            if (response.success) {
                const data = response.data;
                
                // Actualizar barra
                const percentage = data.percentage;
                jQuery('.progress-fill').css('width', percentage + '%');
                jQuery('.progress-text').text(
                    `${data.processed} de ${data.total} imágenes procesadas (${percentage}%)`
                );
                
                // Si terminó
                if (data.processed >= data.total) {
                    clearInterval(interval);
                    jQuery('.progress-text').text('¡Conversión completada!');
                }
            }
        });
    }, 2000); // cada 2 segundos
}
```

---

## 8. AJAX Handlers (admin/ajax-handlers.php)

### Endpoints necesarios:

#### `wpifc_start_conversion`
```php
add_action('wp_ajax_wpifc_start_conversion', 'wpifc_handle_start_conversion');

function wpifc_handle_start_conversion() {
    check_ajax_referer('wpifc_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permisos insuficientes');
    }
    
    $config = $_POST['config'];
    
    $batch_processor = new Batch_Processor();
    $result = $batch_processor->start_batch_conversion($config);
    
    wp_send_json_success($result);
}
```

#### `wpifc_get_progress`
```php
add_action('wp_ajax_wpifc_get_progress', 'wpifc_handle_get_progress');

function wpifc_handle_get_progress() {
    check_ajax_referer('wpifc_nonce', 'nonce');
    
    $batch_processor = new Batch_Processor();
    $progress = $batch_processor->get_conversion_progress();
    
    wp_send_json_success($progress);
}
```

#### `wpifc_cancel_conversion`
```php
add_action('wp_ajax_wpifc_cancel_conversion', 'wpifc_handle_cancel_conversion');

function wpifc_handle_cancel_conversion() {
    check_ajax_referer('wpifc_nonce', 'nonce');
    
    $batch_processor = new Batch_Processor();
    $batch_processor->cancel_batch_conversion();
    
    wp_send_json_success(['message' => 'Conversión cancelada']);
}
```

---

## 9. Conversión Automática en Nuevas Subidas

### Hook:
```php
add_filter('wp_handle_upload', [$this, 'auto_convert_on_upload']);
```

### Implementación:
```php
public function auto_convert_on_upload($upload) {
    // Verificar si está habilitada la conversión automática
    $auto_convert = get_option('wpifc_auto_convert', false);
    
    if (!$auto_convert) {
        return $upload;
    }
    
    // Obtener configuración guardada
    $formats = get_option('wpifc_auto_formats', ['webp']);
    $quality = get_option('wpifc_auto_quality', 85);
    
    // Obtener attachment ID (disponible después de wp_insert_attachment)
    $attachment_id = $upload['attachment_id'];
    
    // Convertir
    $converter = new Converter();
    foreach ($formats as $format) {
        $converter->convert_image($attachment_id, $format, $quality);
    }
    
    return $upload;
}
```

---

## 10. Sistema de Fallback para Servir Imágenes

### Opción A: Usar elemento `<picture>` (Recomendado)

Crear un filtro para `the_content` y `post_thumbnail_html`:

```php
add_filter('the_content', 'wpifc_replace_images_with_picture');

function wpifc_replace_images_with_picture($content) {
    // Buscar todas las etiquetas <img>
    preg_match_all('/<img[^>]+>/i', $content, $matches);
    
    foreach ($matches[0] as $img_tag) {
        // Extraer src
        preg_match('/src="([^"]+)"/i', $img_tag, $src_match);
        $original_src = $src_match[1];
        
        // Generar rutas WebP/AVIF
        $webp_src = str_replace(
            ['.jpg', '.jpeg', '.png'],
            '.webp',
            $original_src
        );
        
        // Verificar si existe
        if (file_exists(get_home_path() . parse_url($webp_src, PHP_URL_PATH))) {
            // Reemplazar con <picture>
            $picture = '<picture>';
            $picture .= '<source srcset="' . $webp_src . '" type="image/webp">';
            $picture .= $img_tag;
            $picture .= '</picture>';
            
            $content = str_replace($img_tag, $picture, $content);
        }
    }
    
    return $content;
}
```

### Opción B: Reescritura con .htaccess (Para WebP)

Agregar en activación del plugin:

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{HTTP_ACCEPT} image/webp
    RewriteCond %{REQUEST_FILENAME} (.*)\.(jpe?g|png)$
    RewriteCond %1.webp -f
    RewriteRule ^ %1.webp [T=image/webp,E=accept:1,L]
</IfModule>

<IfModule mod_headers.c>
    Header append Vary Accept env=REDIRECT_accept
</IfModule>

AddType image/webp .webp
```

---

## 11. Almacenamiento de Metadata

### Post Meta para cada imagen:
```php
// Guardar información de conversión
update_post_meta($attachment_id, '_wpifc_converted', [
    'webp' => [
        'path' => '/path/to/image.webp',
        'size' => filesize($webp_path),
        'converted_at' => current_time('mysql')
    ],
    'avif' => [
        'path' => '/path/to/image.avif',
        'size' => filesize($avif_path),
        'converted_at' => current_time('mysql')
    ]
]);
```

### Opciones generales del plugin:
```php
// Configuración
update_option('wpifc_settings', [
    'auto_convert' => true,
    'auto_formats' => ['webp'],
    'auto_quality' => 85,
    'keep_original' => true,
    'preserve_metadata' => true
]);

// Progreso de conversión masiva
update_option('wpifc_batch_progress', [
    'total' => 1000,
    'processed' => 250,
    'failed' => 5,
    'status' => 'running' // 'running', 'completed', 'cancelled'
]);
```

---

## 12. Manejo de Errores y Logging

### Crear sistema simple de logs:

```php
class Logger {
    private $log_file;
    
    public function __construct() {
        $upload_dir = wp_upload_dir();
        $this->log_file = $upload_dir['basedir'] . '/wpifc-conversion.log';
    }
    
    public function log($message, $level = 'info') {
        $timestamp = current_time('Y-m-d H:i:s');
        $log_entry = "[{$timestamp}] [{$level}] {$message}\n";
        
        error_log($log_entry, 3, $this->log_file);
    }
    
    public function get_logs($lines = 50) {
        if (!file_exists($this->log_file)) {
            return [];
        }
        
        $logs = file($this->log_file);
        return array_slice($logs, -$lines);
    }
}
```

### Uso:
```php
$logger = new Logger();
$logger->log("Iniciando conversión de imagen ID: {$attachment_id}");
$logger->log("Error: No se pudo convertir imagen ID: {$attachment_id}", 'error');
```

---

## 13. Página de Configuración Adicional

### Crear submenú:
```php
add_submenu_page(
    'wp-image-converter',
    'Configuración',
    'Configuración',
    'manage_options',
    'wp-image-converter-settings',
    [$this, 'render_settings_page']
);
```

### Opciones a incluir:
- Conversión automática en nuevas subidas (sí/no)
- Formatos por defecto para auto-conversión
- Calidad por defecto
- Mantener originales (sí/no)
- Preservar metadatos (sí/no)
- Límite de tamaño de archivo para procesar
- Excluir ciertos tipos de imagen (ej: SVG, GIF animados)

---

## 14. Optimizaciones de Performance

### Evitar timeouts:
```php
// Al inicio de cada conversión
set_time_limit(300); // 5 minutos por imagen
ini_set('memory_limit', '256M'); // Aumentar memoria
```

### Procesar solo imágenes grandes:
```php
$file_size = filesize($image_path);
$min_size = get_option('wpifc_min_file_size', 10240); // 10KB por defecto

if ($file_size < $min_size) {
    return; // No convertir imágenes muy pequeñas
}
```

### Saltar imágenes ya optimizadas:
```php
$converted = get_post_meta($attachment_id, '_wpifc_converted', true);
if (!empty($converted['webp'])) {
    return; // Ya está convertida
}
```

---

## 15. Tests y Validación

### Checklist de pruebas:

1. **Conversión individual:**
   - ✓ JPG → WebP
   - ✓ PNG → WebP (con transparencia)
   - ✓ JPG → AVIF
   - ✓ Verificar calidad de salida

2. **Conversión masiva:**
   - ✓ 10 imágenes
   - ✓ 100 imágenes
   - ✓ 1000+ imágenes
   - ✓ Cancelar a mitad de proceso

3. **Fallback de servido:**
   - ✓ Chrome (soporta WebP y AVIF)
   - ✓ Safari (soporta WebP desde v14)
   - ✓ Firefox
   - ✓ Navegadores antiguos sin soporte

4. **Conversión automática:**
   - ✓ Subir nueva imagen
   - ✓ Verificar que se convierte automáticamente
   - ✓ Deshabilitar función y verificar que no convierte

5. **Compatibilidad:**
   - ✓ Servidor con solo GD (sin Imagick)
   - ✓ Servidor con Imagick
   - ✓ Servidor sin soporte AVIF

---

## 16. Mejoras Futuras (Fase 2)

- Comparador visual antes/después de conversión
- Estadísticas de ahorro de espacio
- Reversión masiva (volver a originales)
- Integración con CDN
- Conversión selectiva por carpeta/fecha
- API REST para desarrolladores
- Soporte para GIF animados → WebP animado
- Compresión inteligente según tipo de contenido (foto, logo, ilustración)

---

## Orden de Implementación Sugerido

1. Crear estructura de archivos y archivo principal
2. Implementar clase `Converter` con soporte ImageMagick
3. Crear página de admin básica (sin funcionalidad)
4. Implementar conversión individual de prueba
5. Agregar clase `Batch_Processor` con Action Scheduler
6. Conectar admin page con AJAX handlers
7. Implementar barra de progreso y polling
8. Agregar conversión automática en uploads
9. Implementar sistema de fallback (picture o .htaccess)
10. Agregar página de configuración
11. Implementar logging y manejo de errores
12. Testing exhaustivo
13. Optimizaciones finales

---

## Notas Importantes

- **Seguridad:** Siempre validar nonces en AJAX, verificar capabilities de usuario
- **Sanitización:** Sanitizar todos los inputs del usuario
- **Internacionalización:** Usar `__()` y `_e()` para todos los textos
- **Compatibilidad:** Probar en PHP 7.4, 8.0, 8.1, 8.2
- **Documentación:** Incluir PHPDoc en todas las funciones
- **WordPress Coding Standards:** Seguir las guías oficiales de WP

---

Este documento es la especificación completa para implementar el plugin. Úsalo como referencia paso a paso con Antigravity.