<?php
/**
 * Handles persistence and sanitization for S3-compatible storage settings.
 */
class Ai1wm_S3_Settings {

	/**
	 * Retrieve persisted settings merged with defaults.
	 *
	 * @return array
	 */
	public static function get() {
		$defaults = array(
			'endpoint'       => '',
			'region'         => '',
			'bucket'         => '',
			'prefix'         => '',
			'access_key'     => '',
			'secret_key'     => '',
			'use_path_style' => true,
		);

		$stored = get_option( AI1WM_S3_SETTINGS_OPTION, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$settings = array_merge( $defaults, $stored );

		$settings['endpoint']       = self::normalize_endpoint( $settings['endpoint'] );
		$settings['region']         = self::normalize_region( $settings['region'] );
		$settings['bucket']         = self::normalize_bucket( $settings['bucket'] );
		$settings['prefix']         = self::normalize_prefix( $settings['prefix'] );
		$settings['access_key']     = self::normalize_access_key( $settings['access_key'] );
		$settings['secret_key']     = self::normalize_secret_key( $settings['secret_key'] );
		$settings['use_path_style'] = self::normalize_flag( $settings['use_path_style'] );

		return $settings;
	}

	/**
	 * Persist settings in one operation.
	 *
	 * @param  array $value Raw input values.
	 * @return void
	 */
	public static function update( $value ) {
		if ( ! is_array( $value ) ) {
			$value = array();
		}

		$settings = array(
			'endpoint'       => self::normalize_endpoint( self::array_get( $value, 'endpoint' ) ),
			'region'         => self::normalize_region( self::array_get( $value, 'region' ) ),
			'bucket'         => self::normalize_bucket( self::array_get( $value, 'bucket' ) ),
			'prefix'         => self::normalize_prefix( self::array_get( $value, 'prefix' ) ),
			'access_key'     => self::normalize_access_key( self::array_get( $value, 'access_key' ) ),
			'secret_key'     => self::normalize_secret_key( self::array_get( $value, 'secret_key' ) ),
			'use_path_style' => self::normalize_flag( self::array_get( $value, 'use_path_style' ) ),
		);

		update_option( AI1WM_S3_SETTINGS_OPTION, $settings, false );
	}

	/**
	 * Determine whether the configuration contains all required values.
	 *
	 * @return boolean
	 */
	public static function is_configured() {
		$settings = self::get();

		return empty( self::missing_required_fields() );
	}

	/**
	 * Return human-friendly labels for required fields.
	 *
	 * @return array
	 */
	public static function field_labels() {
		return array(
			'endpoint'   => __( 'Endpoint URL', AI1WM_PLUGIN_NAME ),
			'region'     => __( 'Region', AI1WM_PLUGIN_NAME ),
			'bucket'     => __( 'Bucket', AI1WM_PLUGIN_NAME ),
			'access_key' => __( 'Access Key ID', AI1WM_PLUGIN_NAME ),
			'secret_key' => __( 'Secret Access Key', AI1WM_PLUGIN_NAME ),
		);
	}

	/**
	 * Determine which required fields are empty.
	 *
	 * @return array
	 */
	public static function missing_required_fields() {
		$settings = self::get();
		$missing  = array();

		foreach ( self::field_labels() as $field => $label ) {
			if ( empty( $settings[ $field ] ) ) {
				$missing[] = $label;
			}
		}

		return $missing;
	}

	/**
	 * Access array value with graceful fallback.
	 *
	 * @param  array  $array Input array.
	 * @param  string $key   Key to fetch.
	 * @return mixed
	 */
	private static function array_get( $array, $key ) {
		return isset( $array[ $key ] ) ? $array[ $key ] : '';
	}

	private static function normalize_endpoint( $endpoint ) {
		$endpoint = trim( (string) $endpoint );
		if ( $endpoint !== '' ) {
			$endpoint = untrailingslashit( $endpoint );
		}

		return $endpoint;
	}

	private static function normalize_region( $region ) {
		return sanitize_text_field( (string) $region );
	}

	private static function normalize_bucket( $bucket ) {
		return sanitize_text_field( (string) $bucket );
	}

	private static function normalize_prefix( $prefix ) {
		$prefix = trim( (string) $prefix );
		$prefix = ltrim( $prefix, '/' );

		return $prefix === '' ? '' : trailingslashit( $prefix );
	}

	private static function normalize_access_key( $key ) {
		return trim( preg_replace( '/\s+/', '', (string) $key ) );
	}

	private static function normalize_secret_key( $key ) {
		return trim( (string) $key );
	}

	private static function normalize_flag( $flag ) {
		if ( is_bool( $flag ) ) {
			return $flag;
		}

		return ! empty( $flag ) && $flag !== '0' && $flag !== 'false';
	}
}
