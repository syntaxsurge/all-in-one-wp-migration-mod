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
	public static function dispatch( $archive, $args = array() ) {
		$archive = self::normalize_archive( $archive );
		$args    = is_array( $args ) ? $args : array();
		$force_replace = ! empty( $args['force_replace'] );

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

		$settings   = Ai1wm_S3_Settings::get();
		$remote_key = self::build_remote_key( $archive, $settings );
		$remote_url = Ai1wm_S3_Settings::object_url( $remote_key, $settings );
		$message    = sprintf( __( 'Upload scheduled for %s.', AI1WM_PLUGIN_NAME ), $remote_key );

		if ( $force_replace ) {
			$message = sprintf( __( 'Replacing remote backup at %s. Upload scheduled.', AI1WM_PLUGIN_NAME ), $remote_key );
		}

		Ai1wm_S3_Status::update(
			$archive,
			'queued',
			$message,
			$remote_key,
			$remote_url
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
	public static function delete_remote( $archive, $remote_key = '' ) {
		$archive  = self::normalize_archive( $archive );
		$settings = Ai1wm_S3_Settings::get();
		$client   = new Ai1wm_S3_Client( $settings );

		$relative_key = self::resolve_relative_remote_key( $remote_key, $archive, $settings );
		if ( $relative_key === '' ) {
			$relative_key = $archive;
		}

		$client->delete_object( $relative_key );
	}

	/**
	 * Convert stored remote key into client-ready relative key.
	 *
	 * @param  string $remote_key Stored remote key (may include prefix).
	 * @param  string $archive    Normalised archive name.
	 * @param  array  $settings   S3 settings for prefix reference.
	 * @return string
	 */
	private static function resolve_relative_remote_key( $remote_key, $archive, $settings ) {
		$remote_key = ltrim( str_replace( '\\', '/', (string) $remote_key ), '/' );

		if ( empty( $remote_key ) ) {
			return $archive;
		}

		$prefix = isset( $settings['prefix'] ) ? ltrim( str_replace( '\\', '/', $settings['prefix'] ), '/' ) : '';
		if ( $prefix && strpos( $remote_key, $prefix ) === 0 ) {
			$remote_key = ltrim( substr( $remote_key, strlen( $prefix ) ), '/' );
		}

		return $remote_key ? $remote_key : $archive;
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
		$remote_url = Ai1wm_S3_Settings::object_url( $remote_key, $settings );

		try {
            Ai1wm_S3_Status::update(
                $archive,
                'in_progress',
                sprintf( __( 'Uploading %s to S3...', AI1WM_PLUGIN_NAME ), $remote_key ),
                $remote_key,
                $remote_url,
                array(
                    'bytes_total' => @filesize( $file ),
                    'bytes_done'  => 0,
                )
            );

            $client = new Ai1wm_S3_Client( $settings );
            $total   = @filesize( $file );
            $uploaded = 0;
            $progress = function ( $delta ) use ( $archive, $remote_key, $remote_url, $total, &$uploaded ) {
                $uploaded += (int) $delta;
                $pct = ( $total > 0 ) ? round( ( $uploaded / $total ) * 100 ) : 0;
                $msg = $total > 0
                    ? sprintf( __( 'Uploading to %1$s... %2$d%%', AI1WM_PLUGIN_NAME ), $remote_key, (int) $pct )
                    : sprintf( __( 'Uploading to %s...', AI1WM_PLUGIN_NAME ), $remote_key );

                Ai1wm_S3_Status::update( $archive, 'in_progress', $msg, $remote_key, $remote_url, array(
                    'bytes_total' => (int) $total,
                    'bytes_done'  => (int) $uploaded,
                ) );
            };

            $client->upload( $file, $archive, AI1WM_S3_MULTIPART_CHUNK_SIZE, AI1WM_S3_CONCURRENCY, $progress );

			Ai1wm_S3_Status::update(
				$archive,
				'success',
				sprintf( __( 'Upload to %s completed successfully.', AI1WM_PLUGIN_NAME ), $remote_key ),
				$remote_key,
				$remote_url
			);
		} catch ( Exception $e ) {
			Ai1wm_S3_Status::update(
				$archive,
				'failed',
				sprintf( __( 'Upload to %1$s failed: %2$s', AI1WM_PLUGIN_NAME ), $remote_key, $e->getMessage() ),
				$remote_key,
				$remote_url
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
