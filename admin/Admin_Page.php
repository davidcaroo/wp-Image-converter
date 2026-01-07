<?php
namespace WP_Image_Converter\Admin;

class Admin_Page {

	public function __construct() {
		add_action( 'admin_menu', [ $this, 'add_menu_page' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		
		// Media Library Actions
		add_filter( 'media_row_actions', [ $this, 'add_media_row_actions' ], 10, 2 );
		add_action( 'wp_ajax_wpifc_manual_convert', [ $this, 'handle_manual_convert' ] );

		// Bulk Conversion Actions
		add_action( 'wp_ajax_wpifc_start_bulk', [ $this, 'handle_start_bulk' ] );
		add_action( 'wp_ajax_wpifc_get_progress', [ $this, 'handle_get_progress' ] );
		add_action( 'wp_ajax_wpifc_cancel_bulk', [ $this, 'handle_cancel_bulk' ] );

		// Initialize Batch Processor hooks
		new \WP_Image_Converter\Batch_Processor();
	}

	/**
	 * Add menu page
	 */
	public function add_menu_page() {
		add_menu_page(
			__( 'Image Converter', 'wp-image-converter' ),
			__( 'Image Converter', 'wp-image-converter' ),
			'manage_options',
			'wp-image-converter',
			[ $this, 'render_admin_page' ],
			'dashicons-images-alt2'
		);
	}

	/**
	 * Enqueue admin assets
	 */
	public function enqueue_assets( $hook ) {
		if ( 'toplevel_page_wp-image-converter' !== $hook ) {
			return;
		}

		wp_enqueue_style( 'wpifc-admin-style', WPIFC_URL . 'assets/css/admin-style.css', [], WPIFC_VERSION );
		wp_enqueue_script( 'wpifc-admin-script', WPIFC_URL . 'assets/js/admin-script.js', [ 'jquery' ], WPIFC_VERSION, true );

		wp_localize_script( 'wpifc-admin-script', 'wpifc_vars', [
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'wpifc_nonce' ),
		] );
	}

	/**
	 * Add custom actions to Media Library rows
	 */
	public function add_media_row_actions( $actions, $post ) {
		if ( ! wp_attachment_is_image( $post->ID ) ) {
			return $actions;
		}

		$actions['wpifc_convert_webp'] = sprintf(
			'<a href="#" class="wpifc-manual-convert" data-id="%d" data-format="webp">%s</a>',
			$post->ID,
			__( 'Convert to WebP', 'wp-image-converter' )
		);

		$diagnostic = \WP_Image_Converter\Diagnostic_Tool::check_capabilities();
		if ( $diagnostic['avif'] ) {
			$actions['wpifc_convert_avif'] = sprintf(
				'<a href="#" class="wpifc-manual-convert" data-id="%d" data-format="avif">%s</a>',
				$post->ID,
				__( 'Convert to AVIF', 'wp-image-converter' )
			);
		}

		return $actions;
	}

	/**
	 * Handle AJAX manual conversion
	 */
	public function handle_manual_convert() {
		check_ajax_referer( 'wpifc_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'wp-image-converter' ) ] );
		}

		$attachment_id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$format = isset( $_POST['format'] ) ? sanitize_text_field( $_POST['format'] ) : 'webp';
		$settings = Settings::get_options();

		$converter = new \WP_Image_Converter\Converter();
		$result = $converter->convert_attachment( $attachment_id, $format, $settings['auto_quality'] );

		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}

	/**
	 * Handle Start Bulk Conversion
	 */
	public function handle_start_bulk() {
		check_ajax_referer( 'wpifc_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'wp-image-converter' ) ] );
		}

		$settings = Settings::get_options();
		$batch = new \WP_Image_Converter\Batch_Processor();
		
		$args = [
			'formats' => isset( $_POST['formats'] ) ? (array) $_POST['formats'] : $settings['auto_formats'],
			'quality' => isset( $_POST['quality'] ) ? absint( $_POST['quality'] ) : $settings['auto_quality'],
		];

		$result = $batch->start_batch( $args );
		wp_send_json_success( $result );
	}

	/**
	 * Get progress
	 */
	public function handle_get_progress() {
		check_ajax_referer( 'wpifc_nonce', 'nonce' );
		$progress = \WP_Image_Converter\Batch_Processor::get_progress();
		wp_send_json_success( $progress );
	}

	/**
	 * Cancel bulk
	 */
	public function handle_cancel_bulk() {
		check_ajax_referer( 'wpifc_nonce', 'nonce' );
		$batch = new \WP_Image_Converter\Batch_Processor();
		$batch->cancel_batch();
		wp_send_json_success( [ 'message' => __( 'Cancelled.', 'wp-image-converter' ) ] );
	}

	/**
	 * Render admin page
	 */
	public function render_admin_page() {
		$report = \WP_Image_Converter\Diagnostic_Tool::get_report();
		$settings = Settings::get_options();

		// Stats
		$total_images = count( get_posts( [
			'post_type'      => 'attachment',
			'post_mime_type' => 'image',
			'post_status'    => 'inherit',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		] ) );

		$converted_images = count( get_posts( [
			'post_type'      => 'attachment',
			'post_mime_type' => 'image',
			'post_status'    => 'inherit',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => [
				[
					'key'     => '_wpifc_converted',
					'compare' => 'EXISTS',
				],
			],
		] ) );
		
		include WPIFC_PATH . 'admin/views/admin-page.php';
	}
}
