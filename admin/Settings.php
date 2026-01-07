<?php
namespace WP_Image_Converter\Admin;

class Settings {

	public function __construct() {
		add_action( 'admin_init', [ $this, 'register_settings' ] );
	}

	/**
	 * Register plugin settings
	 */
	public function register_settings() {
		register_setting( 'wpifc_settings_group', 'wpifc_settings', [
			'type'              => 'array',
			'sanitize_callback' => [ $this, 'sanitize_settings' ],
			'default'           => [
				'auto_convert'      => false,
				'auto_formats'      => [ 'webp' ],
				'auto_quality'      => 85,
				'keep_original'     => true,
				'preserve_metadata' => true,
				'min_file_size'     => 10240, // 10KB
			],
		] );
	}

	/**
	 * Sanitize settings
	 *
	 * @param array $input
	 * @return array
	 */
	public function sanitize_settings( $input ) {
		$output = [];
		$output['auto_convert'] = isset( $input['auto_convert'] ) ? (bool) $input['auto_convert'] : false;
		$output['auto_formats'] = isset( $input['auto_formats'] ) && is_array( $input['auto_formats'] ) ? array_map( 'sanitize_text_field', $input['auto_formats'] ) : [];
		$output['auto_quality'] = isset( $input['auto_quality'] ) ? absint( $input['auto_quality'] ) : 85;
		$output['keep_original'] = isset( $input['keep_original'] ) ? (bool) $input['keep_original'] : true;
		$output['preserve_metadata'] = isset( $input['preserve_metadata'] ) ? (bool) $input['preserve_metadata'] : true;
		$output['min_file_size'] = isset( $input['min_file_size'] ) ? absint( $input['min_file_size'] ) : 10240;

		return $output;
	}

	/**
	 * Get defaults
	 */
	public static function get_defaults() {
		return [
			'auto_convert'      => false,
			'auto_formats'      => [ 'webp' ],
			'auto_quality'      => 85,
			'keep_original'     => true,
			'preserve_metadata' => true,
			'min_file_size'     => 10240,
		];
	}

	/**
	 * Get all settings
	 *
	 * @return array
	 */
	public static function get_options() {
		$options = get_option( 'wpifc_settings', [] );
		return wp_parse_args( $options, self::get_defaults() );
	}
}
