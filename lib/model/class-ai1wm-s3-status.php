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

		return is_array( $stored ) ? $stored : array();
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
			return $statuses[ $archive ];
		}

		return array(
			'state'      => '',
			'message'    => '',
			'remote_key' => '',
			'updated_at' => 0,
		);
	}

	/**
	 * Update state for archive in a single call.
	 *
	 * @param string $archive    Relative archive path.
	 * @param string $state      Status slug.
	 * @param string $message    Human readable message.
	 * @param string $remote_key Remote object key.
	 */
	public static function update( $archive, $state, $message = '', $remote_key = null ) {
		$archive = self::normalize_archive( $archive );
		$statuses = self::all();

		$current = isset( $statuses[ $archive ] ) ? $statuses[ $archive ] : array();

		$statuses[ $archive ] = array(
			'state'      => $state,
			'message'    => $message,
			'remote_key' => is_null( $remote_key ) ? self::array_get( $current, 'remote_key' ) : $remote_key,
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
