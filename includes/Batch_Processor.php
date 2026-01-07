<?php
namespace WP_Image_Converter;

class Batch_Processor {

	public function __construct() {
		add_action( 'wpifc_process_image', [ $this, 'process_single_image' ], 10, 3 );
	}

	/**
	 * Start a batch conversion process
	 *
	 * @param array $args
	 * @return array
	 */
	public function start_batch( $args ) {
		$images = get_posts( [
			'post_type'      => 'attachment',
			'post_mime_type' => 'image',
			'post_status'    => 'inherit',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		] );

		if ( empty( $images ) ) {
			return [ 'success' => false, 'message' => __( 'No images found in library.', 'wp-image-converter' ) ];
		}

		$total = count( $images );
		$formats = isset( $args['formats'] ) ? $args['formats'] : [ 'webp' ];
		$quality = isset( $args['quality'] ) ? $args['quality'] : 85;

		// Reset progress
		update_option( 'wpifc_batch_progress', [
			'total'     => $total,
			'processed' => 0,
			'failed'    => 0,
			'status'    => 'running',
		] );

		// Schedule actions
		foreach ( $images as $image_id ) {
			foreach ( $formats as $format ) {
				as_enqueue_async_action( 'wpifc_process_image', [
					'attachment_id' => $image_id,
					'format'        => $format,
					'quality'       => $quality,
				], 'wpifc-conversions' );
			}
		}

		return [ 'success' => true, 'total' => $total ];
	}

	/**
	 * Process a single image from the queue
	 */
	public function process_single_image( $attachment_id, $format, $quality ) {
		$converter = new Converter();
		$result = $converter->convert_attachment( $attachment_id, $format, $quality );

		$progress = get_option( 'wpifc_batch_progress', [] );
		$progress['processed']++;
		
		if ( ! $result['success'] ) {
			$progress['failed']++;
			// Log error if logger exists
		}

		if ( $progress['processed'] >= $progress['total'] ) {
			$progress['status'] = 'completed';
		}

		update_option( 'wpifc_batch_progress', $progress );
	}

	/**
	 * Get current progress
	 */
	public static function get_progress() {
		return get_option( 'wpifc_batch_progress', [
			'total'     => 0,
			'processed' => 0,
			'failed'    => 0,
			'status'    => 'idle',
		] );
	}

	/**
	 * Cancel all pending tasks
	 */
	public function cancel_batch() {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( 'wpifc_process_image' );
		}
		
		update_option( 'wpifc_batch_progress', [ 'status' => 'cancelled' ] );
	}
}
