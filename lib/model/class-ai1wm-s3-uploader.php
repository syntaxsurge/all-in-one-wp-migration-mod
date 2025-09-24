<?php
/**
 * Dispatches and executes uploads from local backups to S3-compatible storage.
 */
class Ai1wm_S3_Uploader {

	/**
	 * Queue a new upload job for the given archive.
	 *
	 * @param  string $archive Relative archive path.
	 * @throws Ai1wm_S3_Exception When configuration or archive is invalid.
	 */
	public static function dispatch( $archive ) {
		$archive = self::normalize_archive( $archive );

		if ( validate_file( $archive ) !== 0 ) {
			throw new Ai1wm_S3_Exception( __( 'Invalid backup archive path.', AI1WM_PLUGIN_NAME ) );
		}

		$missing = Ai1wm_S3_Settings::missing_required_fields();
		if ( ! empty( $missing ) ) {
			throw new Ai1wm_S3_Exception(
				sprintf(
					__( 'Missing required S3 settings: %s.', AI1WM_PLUGIN_NAME ),
					implode( ', ', $missing )
				)
			);
		}

		$remote_key = self::build_remote_key( $archive );

		Ai1wm_S3_Status::update(
			$archive,
			'queued',
			sprintf( __( 'Upload scheduled for %s.', AI1WM_PLUGIN_NAME ), $remote_key ),
			$remote_key
		);

		if ( ! wp_next_scheduled( AI1WM_S3_CRON_HOOK, array( $archive ) ) ) {
			wp_schedule_single_event( time(), AI1WM_S3_CRON_HOOK, array( $archive ) );
		}

		if ( function_exists( 'spawn_cron' ) ) {
			spawn_cron();
		}
	}

	/**
	 * Delete existing remote object for archive.
	 *
	 * @param  string $archive Relative archive path.
	 * @return void
	 */
	public static function delete_remote( $archive ) {
		$archive  = self::normalize_archive( $archive );
		$settings = Ai1wm_S3_Settings::get();
		$client   = new Ai1wm_S3_Client( $settings );

		$client->delete_object( $archive );
	}

	/**
	 * Run upload job immediately (cron handler).
	 *
	 * @param  string $archive Relative archive path.
	 * @return void
	 */
	public static function run( $archive ) {
		$archive = self::normalize_archive( $archive );

		ignore_user_abort( true );
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 0 );
		}

		try {
			$file = ai1wm_backup_path( array( 'archive' => $archive ) );
		} catch ( Exception $e ) {
			Ai1wm_S3_Status::update( $archive, 'failed', $e->getMessage(), '' );
			return;
		}

		if ( ! is_file( $file ) ) {
			Ai1wm_S3_Status::update( $archive, 'failed', __( 'Backup file no longer exists.', AI1WM_PLUGIN_NAME ), '' );
			return;
		}

		$missing = Ai1wm_S3_Settings::missing_required_fields();
		if ( ! empty( $missing ) ) {
			Ai1wm_S3_Status::update(
				$archive,
				'failed',
				sprintf( __( 'Missing required S3 settings: %s.', AI1WM_PLUGIN_NAME ), implode( ', ', $missing ) ),
				''
			);
			return;
		}

		$settings   = Ai1wm_S3_Settings::get();
		$remote_key = self::build_remote_key( $archive, $settings );

		try {
			Ai1wm_S3_Status::update(
				$archive,
				'in_progress',
				sprintf( __( 'Uploading %s to S3...', AI1WM_PLUGIN_NAME ), $remote_key ),
				$remote_key
			);

			$client = new Ai1wm_S3_Client( $settings );
			$client->upload( $file, $archive );

			Ai1wm_S3_Status::update(
				$archive,
				'success',
				sprintf( __( 'Upload to %s completed successfully.', AI1WM_PLUGIN_NAME ), $remote_key ),
				$remote_key
			);
		} catch ( Exception $e ) {
			Ai1wm_S3_Status::update(
				$archive,
				'failed',
				sprintf( __( 'Upload to %1$s failed: %2$s', AI1WM_PLUGIN_NAME ), $remote_key, $e->getMessage() ),
				$remote_key
			);
		}
	}

	/**
	 * Create the remote object key using prefix configuration.
	 *
	 * @param  string $archive  Relative archive path.
	 * @param  array  $settings Optional settings to avoid refetching.
	 * @return string
	 */
	public static function build_remote_key( $archive, $settings = null ) {
		$archive = self::normalize_archive( $archive );

		if ( is_null( $settings ) ) {
			$settings = Ai1wm_S3_Settings::get();
		}

		$prefix = isset( $settings['prefix'] ) ? $settings['prefix'] : '';

		return ltrim( $prefix . $archive, '/' );
	}

	/**
	 * Remove entry from status registry when archive deleted.
	 *
	 * @param  string $archive Relative archive path.
	 * @return void
	 */
	public static function forget( $archive ) {
		Ai1wm_S3_Status::delete( self::normalize_archive( $archive ) );
	}

	/**
	 * Ensure archive value always uses forward slashes and no leading slash.
	 *
	 * @param  string $archive
	 * @return string
	 */
	private static function normalize_archive( $archive ) {
		$archive = str_replace( '\\', '/', (string) $archive );
		return ltrim( $archive, '/' );
	}
}
