<?php
/**
 * Plugin Name: WP Image Format Converter
 * Description: Pro-level mass image conversion to WebP/AVIF with smart batch processing.
 * Version: 1.0.0
 * Author: David Caro Morales
 * Text Domain: wp-image-converter
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define Constants
define( 'WPIFC_VERSION', '1.0.0' );
define( 'WPIFC_PATH', plugin_dir_path( __FILE__ ) );
define( 'WPIFC_URL', plugin_dir_url( __FILE__ ) );
define( 'WPIFC_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Composer Autoloader
 */
if ( file_exists( WPIFC_PATH . 'vendor/autoload.php' ) ) {
	require_once WPIFC_PATH . 'vendor/autoload.php';
}

/**
 * Initialize the plugin
 */
add_action( 'plugins_loaded', function() {
	// Initialize Action Scheduler (if bundled)
	if ( file_exists( WPIFC_PATH . 'vendor/woocommerce/action-scheduler/action-scheduler.php' ) ) {
		require_once WPIFC_PATH . 'vendor/woocommerce/action-scheduler/action-scheduler.php';
	}

	// Initialize Settings
	new \WP_Image_Converter\Admin\Settings();
	
	// Initialize Hooks (Automation & Frontend)
	new \WP_Image_Converter\Hooks();
	
	// Initialize Admin Menu
	if ( is_admin() ) {
		new \WP_Image_Converter\Admin\Admin_Page();
	}
} );

/**
 * Activation Hook
 */
register_activation_hook( __FILE__, function() {
	// Future: Setup tables or default options
} );
