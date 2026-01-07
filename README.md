# WP Image Format Converter

A professional WordPress plugin for mass image conversion to modern formats (WebP/AVIF) with intelligent batch processing and automatic frontend delivery.

## Features

- **Smart Batch Processing**: Convert your entire media library with Action Scheduler integration
- **Modern Formats**: Support for WebP and AVIF with automatic fallback
- **Server Diagnostics**: Real-time capability detection (ImageMagick/GD Library)
- **Auto-Conversion**: Automatically convert new uploads
- **Frontend Optimization**: Automatic `<picture>` tag injection with progressive fallback
- **Premium Dashboard**: Live statistics, progress tracking, and bulk conversion controls
- **Non-Destructive**: Keeps original images for maximum compatibility

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- ImageMagick or GD Library with WebP support
- Composer (for development)

## Installation

1. Download the plugin ZIP file
2. Upload to WordPress via Plugins > Add New > Upload Plugin
3. Activate the plugin
4. Navigate to WP Image Converter in the admin menu
5. Configure your settings and start converting!

## Usage

### Bulk Conversion
1. Go to WP Image Converter dashboard
2. Select target formats (WebP/AVIF)
3. Click "Start Mass Conversion"
4. Monitor progress in real-time

### Auto-Conversion
Enable "Auto-convert on upload" in settings to automatically generate optimized versions for new images.

### Frontend Delivery
The plugin automatically replaces `<img>` tags with `<picture>` elements for optimal format delivery:

```html
<picture>
  <source srcset="image.avif" type="image/avif">
  <source srcset="image.webp" type="image/webp">
  <img src="image.jpg" alt="Fallback">
</picture>
```

## Development

### Setup
```bash
composer install
```

### Structure
```
wp-image-format-converter/
├── admin/              # Admin interface
├── assets/             # CSS/JS assets
├── includes/           # Core functionality
├── languages/          # Translations
└── vendor/             # Composer dependencies
```

## Author

**David Caro Morales**

## License

This project is proprietary software developed for production use.

## Changelog

### 1.0.0
- Initial release
- Bulk conversion with Action Scheduler
- WebP and AVIF support
- Automatic frontend delivery
- Premium admin dashboard
