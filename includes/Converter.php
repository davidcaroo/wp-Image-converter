<?php
namespace WP_Image_Converter;

class Converter {

	/**
	 * Convert a specific attachment to a target format
	 *
	 * @param int    $attachment_id
	 * @param string $target_format 'webp' or 'avif'
	 * @param int    $quality
	 * @return array
	 */
	public function convert_attachment( $attachment_id, $target_format, $quality = 85 ) {
		$file_path = get_attached_file( $attachment_id );

		if ( ! $file_path || ! file_exists( $file_path ) ) {
			return [ 'success' => false, 'message' => __( 'Original file not found.', 'wp-image-converter' ) ];
		}

		$results = [];
		
		// 1. Convert the main image
		$main_result = $this->convert_file( $file_path, $target_format, $quality );
		$results['full'] = $main_result;

		// 2. Convert all thumbnails/sizes
		$metadata = wp_get_attachment_metadata( $attachment_id );
		if ( ! empty( $metadata['sizes'] ) ) {
			$base_dir = dirname( $file_path );
			foreach ( $metadata['sizes'] as $size_name => $size_info ) {
				$size_path = $base_dir . '/' . $size_info['file'];
				if ( file_exists( $size_path ) ) {
					$results[$size_name] = $this->convert_file( $size_path, $target_format, $quality );
				}
			}
		}

		// 3. Update Meta
		$this->update_conversion_meta( $attachment_id, $target_format, $results );

		return [
			'success' => true,
			'message' => sprintf( __( 'Converted image and all sizes to %s.', 'wp-image-converter' ), strtoupper( $target_format ) ),
			'details' => $results
		];
	}

	/**
	 * Convert a single file
	 *
	 * @param string $source_path
	 * @param string $target_format
	 * @param int    $quality
	 * @return array
	 */
	public function convert_file( $source_path, $target_format, $quality ) {
		$dest_path = $this->get_destination_path( $source_path, $target_format );

		// Skip if already exists
		if ( file_exists( $dest_path ) ) {
			return [ 'success' => true, 'path' => $dest_path, 'status' => 'exists' ];
		}

		$caps = Diagnostic_Tool::check_capabilities();

		if ( $caps['imagick'] ) {
			return $this->convert_with_imagick( $source_path, $dest_path, $target_format, $quality );
		} elseif ( $caps['gd'] && 'webp' === $target_format ) {
			return $this->convert_with_gd( $source_path, $dest_path, $target_format, $quality );
		}

		return [ 'success' => false, 'message' => __( 'No supported conversion library found.', 'wp-image-converter' ) ];
	}

	/**
	 * Convert using ImageMagick
	 */
	private function convert_with_imagick( $source, $dest, $format, $quality ) {
		try {
			$image = new \Imagick( $source );
			
			// Handle transparency for non-supporting formats
			if ( 'jpg' === $format || 'jpeg' === $format ) {
				$image->setImageBackgroundColor( 'white' );
				$image = $image->flattenImages();
			}

			$image->setImageFormat( $format );
			$image->setImageCompressionQuality( $quality );
			
			// Optional: Preserve profiles if needed
			// $image->stripImage(); // Use if you want smaller files without meta

			$image->writeImage( $dest );
			$image->clear();
			$image->destroy();

			return [ 'success' => true, 'path' => $dest, 'size' => filesize( $dest ) ];
		} catch ( \Exception $e ) {
			return [ 'success' => false, 'message' => $e->getMessage() ];
		}
	}

	/**
	 * Convert using GD (WebP only)
	 */
	private function convert_with_gd( $source, $dest, $format, $quality ) {
		$info = getimagesize( $source );
		if ( ! $info ) return [ 'success' => false, 'message' => 'Invalid image.' ];

		switch ( $info[2] ) {
			case IMAGETYPE_JPEG: $img = imagecreatefromjpeg( $source ); break;
			case IMAGETYPE_PNG: 
				$img = imagecreatefrompng( $source ); 
				imagepalettetotruecolor( $img );
				imagealphablending( $img, true );
				imagesavealpha( $img, true );
				break;
			case IMAGETYPE_GIF: $img = imagecreatefromgif( $source ); break;
			default: return [ 'success' => false, 'message' => 'Unsupported source format.' ];
		}

		if ( ! $img ) return [ 'success' => false, 'message' => 'Failed to load image.' ];

		$success = imagewebp( $img, $dest, $quality );
		imagedestroy( $img );

		return $success ? [ 'success' => true, 'path' => $dest, 'size' => filesize( $dest ) ] : [ 'success' => false, 'message' => 'GD conversion failed.' ];
	}

	/**
	 * Get destination path
	 */
	private function get_destination_path( $source_path, $target_format ) {
		$path_info = pathinfo( $source_path );
		return $path_info['dirname'] . '/' . $path_info['filename'] . '.' . $target_format;
	}

	/**
	 * Update conversion metadata
	 */
	private function update_conversion_meta( $attachment_id, $format, $results ) {
		$meta = get_post_meta( $attachment_id, '_wpifc_converted', true );
		if ( ! is_array( $meta ) ) $meta = [];

		$meta[$format] = [
			'converted_at' => current_time( 'mysql' ),
			'results'      => $results
		];

		update_post_meta( $attachment_id, '_wpifc_converted', $meta );
	}
}
