<?php
namespace WP_Image_Converter;

class Hooks {

	public function __construct() {
		// Auto-convert on upload
		add_action( 'add_attachment', [ $this, 'auto_convert_on_upload' ] );
		
		// Global Frontend filtering via Output Buffer
		add_action( 'template_redirect', [ $this, 'start_buffer' ] );
	}

	public function start_buffer() {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}
		ob_start( [ $this, 'replace_images_with_picture' ] );
	}

	/**
	 * Auto-convert on upload
	 */
	public function auto_convert_on_upload( $attachment_id ) {
		if ( ! wp_attachment_is_image( $attachment_id ) ) {
			return;
		}

		$settings = Admin\Settings::get_options();
		if ( ! $settings['auto_convert'] ) {
			return;
		}

		$converter = new Converter();
		foreach ( $settings['auto_formats'] as $format ) {
			$converter->convert_attachment( $attachment_id, $format, $settings['auto_quality'] );
		}
	}

	/**
	 * Robust frontend replacement of <img> with <picture>
	 */
	public function replace_images_with_picture( $content ) {
		if ( empty( $content ) || ! strpos( $content, '<img' ) ) {
			return $content;
		}

		$dom = new \DOMDocument();
		libxml_use_internal_errors( true );
		
		$is_full_page = strpos( $content, '<html' ) !== false;
		if ( $is_full_page ) {
			$dom->loadHTML( '<?xml encoding="utf-8" ?>' . $content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		} else {
			$dom->loadHTML( '<?xml encoding="utf-8" ?><div>' . $content . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		}
		libxml_clear_errors();

		$images = $dom->getElementsByTagName( 'img' );
		if ( $images->length === 0 ) {
			return $content;
		}

		for ( $i = $images->length - 1; $i >= 0; $i-- ) {
			$img = $images->item( $i );
			$src = $img->getAttribute( 'src' );
			
			// Skip if already optimized or inside picture
			if ( $img->parentNode->tagName === 'picture' || strpos( $src, 'data:image' ) === 0 ) {
				continue;
			}

			// Try to find if versions exist (even if not in library)
			$sources = [];
			foreach ( [ 'avif', 'webp' ] as $format ) {
				$new_src = $this->get_optimized_url_if_exists( $src, $format );
				if ( $new_src ) {
					$sources[ $format ] = $new_src;
				}
			}

			if ( empty( $sources ) ) {
				continue;
			}

			$picture = $dom->createElement( 'picture' );
			foreach ( $sources as $format => $url ) {
				$source = $dom->createElement( 'source' );
				$source->setAttribute( 'srcset', $url );
				$source->setAttribute( 'type', 'image/' . $format );
				$picture->appendChild( $source );
			}

			$fallback_img = $img->cloneNode( true );
			$picture->appendChild( $fallback_img );
			$img->parentNode->replaceChild( $picture, $img );
		}

		if ( $is_full_page ) {
			$new_content = $dom->saveHTML();
			return str_replace( '<?xml encoding="utf-8" ?>', '', $new_content );
		} else {
			$new_content = $dom->saveHTML( $dom->getElementsByTagName( 'div' )->item( 0 ) );
			return substr( $new_content, 5, -6 );
		}
	}

	/**
	 * Helper to check if an optimized version exists on disk
	 */
	private function get_optimized_url_if_exists( $url, $format ) {
		// Only handle local images
		$home_url = home_url();
		if ( strpos( $url, $home_url ) === false && strpos( $url, '/' ) !== 0 ) {
			return false;
		}

		$new_url = str_replace( [ '.jpg', '.jpeg', '.png' ], '.' . $format, $url );
		
		// Map URL to file path
		$path = str_replace( home_url(), ABSPATH, $new_url );
		$path = str_replace( '//', '/', $path ); 

		if ( file_exists( $path ) ) {
			return $new_url;
		}

		return false;
	}

	/**
	 * Helper to get attachment ID by URL
	 */
	private function get_attachment_id_by_url( $url ) {
		global $wpdb;
		$attachment_id = attachment_url_to_postid( $url );
		
		if ( ! $attachment_id ) {
			// Fallback for cropped images or edited URLs
			$path_info = pathinfo( $url );
			$name = $path_info['filename'];
			$attachment_id = $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_wp_attached_file' AND meta_value LIKE %s", '%' . $wpdb->esc_like( $name ) . '%' ) );
		}

		return $attachment_id;
	}
}
