<?php
/**
 * Tracks remote upload status for backup archives.
 */
class Ai1wm_S3_Status {

	/**
	 * Fetch status for every tracked archive.
	 *
	 * @return array
	 */
	public static function all() {
		$stored = get_option( AI1WM_S3_STATUS_OPTION, array() );

		if ( ! is_array( $stored ) ) {
			return array();
		}

		$dirty = false;
		foreach ( $stored as $archive => $status ) {
			$normalized = self::prepare_status( $archive, $status );
			if ( $normalized !== $status ) {
				$stored[ $archive ] = $normalized;
				$dirty = true;
			}
		}

		if ( $dirty ) {
			update_option( AI1WM_S3_STATUS_OPTION, $stored, false );
		}

		return $stored;
	}

	/**
	 * Retrieve status for a single archive.
	 *
	 * @param  string $archive Relative archive path.
	 * @return array
	 */
	public static function get( $archive ) {
		$archive = self::normalize_archive( $archive );
		$statuses = self::all();

		if ( isset( $statuses[ $archive ] ) ) {
			return self::prepare_status( $archive, $statuses[ $archive ] );
		}

		return self::defaults();
	}

	/**
	 * Update state for archive in a single call.
	 *
	 * @param string $archive    Relative archive path.
	 * @param string $state      Status slug.
	 * @param string $message    Human readable message.
	 * @param string $remote_key Remote object key.
	 */
	public static function update( $archive, $state, $message = '', $remote_key = null, $remote_url = null ) {
		$archive = self::normalize_archive( $archive );
		$statuses = self::all();

		$current = isset( $statuses[ $archive ] ) ? self::prepare_status( $archive, $statuses[ $archive ] ) : self::defaults();
		$remote_key = is_null( $remote_key ) ? self::array_get( $current, 'remote_key' ) : $remote_key;

		if ( is_null( $remote_url ) ) {
			$existing_url = self::array_get( $current, 'remote_url' );
			$remote_url   = $remote_key ? Ai1wm_S3_Settings::object_url( $remote_key ) : $existing_url;
		}
		$remote_url = (string) $remote_url;

		$statuses[ $archive ] = array(
			'state'      => $state,
			'message'    => $message,
			'remote_key' => $remote_key,
			'remote_url' => $remote_url,
			'updated_at' => time(),
		);

		update_option( AI1WM_S3_STATUS_OPTION, $statuses, false );
	}

	/**
	 * Remove archive from registry.
	 *
	 * @param  string $archive Relative archive path.
	 * @return void
	 */
	public static function delete( $archive ) {
		$archive = self::normalize_archive( $archive );
		$statuses = self::all();

		if ( isset( $statuses[ $archive ] ) ) {
			unset( $statuses[ $archive ] );
			update_option( AI1WM_S3_STATUS_OPTION, $statuses, false );
		}
	}

	/**
	 * Access array key safely.
	 *
	 * @param  array  $data Input data.
	 * @param  string $key  Key name.
	 * @return mixed
	 */
	private static function array_get( $data, $key ) {
		return isset( $data[ $key ] ) ? $data[ $key ] : '';
}

	/**
	 * Ensure status entry has expected shape and computed values.
	 *
	 * @param  string $archive Archive key.
	 * @param  array  $status  Stored status.
	 * @return array
	 */
	private static function prepare_status( $archive, $status ) {
		if ( ! is_array( $status ) ) {
			$status = array();
		}

		$status = array_merge( self::defaults(), $status );

		if ( $status['remote_url'] === '' && $status['remote_key'] !== '' ) {
			$status['remote_url'] = Ai1wm_S3_Settings::object_url( $status['remote_key'] );
		}

		$status['remote_key'] = (string) $status['remote_key'];
		$status['remote_url'] = (string) $status['remote_url'];
		$status['message']    = (string) $status['message'];

		return $status;
	}

	/**
	 * Default structure for status entries.
	 *
	 * @return array
	 */
	private static function defaults() {
		return array(
			'state'      => '',
			'message'    => '',
			'remote_key' => '',
			'remote_url' => '',
			'updated_at' => 0,
		);
	}

	/**
	 * Ensure archive references are consistent.
	 *
	 * @param  string $archive Raw archive reference.
	 * @return string
	 */
	private static function normalize_archive( $archive ) {
		$archive = trim( (string) $archive );
		$archive = str_replace( '\\', '/', $archive );
		$archive = ltrim( $archive, '/' );

		return $archive;
	}
}
