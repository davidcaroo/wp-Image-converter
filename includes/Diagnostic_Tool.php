<?php
namespace WP_Image_Converter;

class Diagnostic_Tool {

	/**
	 * Check server capabilities for image conversion
	 *
	 * @return array
	 */
	public static function check_capabilities() {
		$capabilities = [
			'imagick' => extension_loaded( 'imagick' ),
			'gd'      => extension_loaded( 'gd' ),
			'webp'    => false,
			'avif'    => false,
		];

		if ( $capabilities['imagick'] ) {
			$imagick_formats = \Imagick::queryFormats();
			$capabilities['webp'] = in_array( 'WEBP', $imagick_formats );
			$capabilities['avif'] = in_array( 'AVIF', $imagick_formats );
		}

		if ( ! $capabilities['webp'] && $capabilities['gd'] ) {
			$gd_info = gd_info();
			$capabilities['webp'] = isset( $gd_info['WebP Support'] ) && $gd_info['WebP Support'];
		}

		return $capabilities;
	}

	/**
	 * Get a formatted report of capabilities
	 *
	 * @return array
	 */
	public static function get_report() {
		$caps = self::check_capabilities();
		$report = [];

		$report['imagick'] = [
			'label'   => __( 'ImageMagick Extension', 'wp-image-converter' ),
			'status'  => $caps['imagick'] ? 'success' : 'error',
			'message' => $caps['imagick'] ? __( 'Available', 'wp-image-converter' ) : __( 'Not installed. Highly recommended for AVIF support.', 'wp-image-converter' ),
		];

		$report['gd'] = [
			'label'   => __( 'GD Library', 'wp-image-converter' ),
			'status'  => $caps['gd'] ? 'success' : 'warning',
			'message' => $caps['gd'] ? __( 'Available', 'wp-image-converter' ) : __( 'Not installed. Fallback conversion won\'t work.', 'wp-image-converter' ),
		];

		$report['webp'] = [
			'label'   => __( 'WebP Support', 'wp-image-converter' ),
			'status'  => $caps['webp'] ? 'success' : 'error',
			'message' => $caps['webp'] ? __( 'Supported', 'wp-image-converter' ) : __( 'Not supported by your server.', 'wp-image-converter' ),
		];

		$report['avif'] = [
			'label'   => __( 'AVIF Support', 'wp-image-converter' ),
			'status'  => $caps['avif'] ? 'success' : 'warning',
			'message' => $caps['avif'] ? __( 'Supported', 'wp-image-converter' ) : __( 'Not supported. Requires ImageMagick 7.0.10-56+ or similar.', 'wp-image-converter' ),
		];

		return $report;
	}
}
