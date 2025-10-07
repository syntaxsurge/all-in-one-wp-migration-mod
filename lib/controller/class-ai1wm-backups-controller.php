<?php
/**
 * Copyright (C) 2014-2018 ServMask Inc.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * ███████╗███████╗██████╗ ██╗   ██╗███╗   ███╗ █████╗ ███████╗██╗  ██╗
 * ██╔════╝██╔════╝██╔══██╗██║   ██║████╗ ████║██╔══██╗██╔════╝██║ ██╔╝
 * ███████╗█████╗  ██████╔╝██║   ██║██╔████╔██║███████║███████╗█████╔╝
 * ╚════██║██╔══╝  ██╔══██╗╚██╗ ██╔╝██║╚██╔╝██║██╔══██║╚════██║██╔═██╗
 * ███████║███████╗██║  ██║ ╚████╔╝ ██║ ╚═╝ ██║██║  ██║███████║██║  ██╗
 * ╚══════╝╚══════╝╚═╝  ╚═╝  ╚═══╝  ╚═╝     ╚═╝╚═╝  ╚═╝╚══════╝╚═╝  ╚═╝
 */

class Ai1wm_Backups_Controller {

	public static function index() {
		$model = new Ai1wm_Backups;
		$settings = Ai1wm_S3_Settings::get();

		Ai1wm_Template::render(
			'backups/index',
			array(
				'backups'        => $model->get_files(),
				'username'       => get_option( AI1WM_AUTH_USER ),
				'password'       => get_option( AI1WM_AUTH_PASSWORD ),
				's3_settings'    => $settings,
				's3_configured'  => Ai1wm_S3_Settings::is_configured(),
				's3_statuses'    => Ai1wm_S3_Status::all(),
				's3_chunk_size'  => AI1WM_S3_MULTIPART_CHUNK_SIZE,
				's3_max_retries' => AI1WM_S3_MAX_RETRIES,
				's3_concurrency' => AI1WM_S3_CONCURRENCY,
			)
		);
	}

	public static function delete( $params = array() ) {
		$errors = array();

		// Set params
		if ( empty( $params ) ) {
			$params = stripslashes_deep( $_POST );
		}

		// Set secret key
		$secret_key = null;
		if ( isset( $params['secret_key'] ) ) {
			$secret_key = trim( $params['secret_key'] );
		}

		// Set archive
		$archive = null;
		if ( isset( $params['archive'] ) ) {
			$archive = trim( $params['archive'] );
		}

		try {
			// Ensure that unauthorized people cannot access delete action
			ai1wm_verify_secret_key( $secret_key );
		} catch ( Ai1wm_Not_Valid_Secret_Key_Exception $e ) {
			exit;
		}

		$model = new Ai1wm_Backups;

		try {
			// Delete file
			if ( $model->delete_file( $archive ) ) {
				Ai1wm_S3_Uploader::forget( $archive );
			}
		} catch ( Exception $e ) {
			$errors[] = $e->getMessage();
		}

		echo json_encode( array( 'errors' => $errors ) );
		exit;
	}

	public static function save_s3_settings() {
		if ( ! current_user_can( 'import' ) ) {
			wp_die( __( 'You are not allowed to perform this action.', AI1WM_PLUGIN_NAME ) );
		}

		check_admin_referer( 'ai1wm_s3_settings' );

		$settings = array(
			'endpoint'       => isset( $_POST['ai1wm_s3_endpoint'] ) ? wp_unslash( $_POST['ai1wm_s3_endpoint'] ) : '',
			'region'         => isset( $_POST['ai1wm_s3_region'] ) ? wp_unslash( $_POST['ai1wm_s3_region'] ) : '',
			'bucket'         => isset( $_POST['ai1wm_s3_bucket'] ) ? wp_unslash( $_POST['ai1wm_s3_bucket'] ) : '',
			'prefix'         => isset( $_POST['ai1wm_s3_prefix'] ) ? wp_unslash( $_POST['ai1wm_s3_prefix'] ) : '',
			'access_key'     => isset( $_POST['ai1wm_s3_access_key'] ) ? wp_unslash( $_POST['ai1wm_s3_access_key'] ) : '',
			'secret_key'     => isset( $_POST['ai1wm_s3_secret_key'] ) ? wp_unslash( $_POST['ai1wm_s3_secret_key'] ) : '',
			'use_path_style' => isset( $_POST['ai1wm_s3_use_path_style'] ) ? wp_unslash( $_POST['ai1wm_s3_use_path_style'] ) : '',
		);

		Ai1wm_S3_Settings::update( $settings );

		$redirect = add_query_arg(
			array(
				'page'                 => 'ai1wm_backups',
				'ai1wm_s3_settings'    => 'saved',
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $redirect );
		exit;
	}

	public static function upload_to_s3() {
		if ( ! current_user_can( 'import' ) ) {
			wp_send_json_error( array( 'errors' => array( __( 'You are not allowed to perform this action.', AI1WM_PLUGIN_NAME ) ) ) );
		}

		$params = stripslashes_deep( $_POST );

		$secret_key = isset( $params['secret_key'] ) ? trim( $params['secret_key'] ) : null;
		$archive    = isset( $params['archive'] ) ? trim( $params['archive'] ) : '';

		try {
			ai1wm_verify_secret_key( $secret_key );
		} catch ( Ai1wm_Not_Valid_Secret_Key_Exception $e ) {
			wp_send_json_error( array( 'errors' => array( $e->getMessage() ) ) );
		}

		$status       = Ai1wm_S3_Status::get( $archive );
		$current_state = isset( $status['state'] ) ? strtolower( $status['state'] ) : '';
		if ( in_array( $current_state, array( 'queued', 'in_progress' ), true ) ) {
			wp_send_json_error( array( 'errors' => array( __( 'An upload is already queued or running for this backup. Please wait for it to finish.', AI1WM_PLUGIN_NAME ) ) ) );
		}

		$force_replace = ! empty( $params['force_replace'] );

		if ( $force_replace ) {
			try {
				$remote_key = isset( $status['remote_key'] ) ? $status['remote_key'] : '';
				Ai1wm_S3_Uploader::delete_remote( $archive, $remote_key );
			} catch ( Exception $e ) {
				wp_send_json_error( array( 'errors' => array( sprintf( __( 'Unable to delete existing remote backup: %s', AI1WM_PLUGIN_NAME ), $e->getMessage() ) ) ) );
			}
		}

		try {
			Ai1wm_S3_Uploader::dispatch( $archive, array( 'force_replace' => $force_replace ) );
			$status = Ai1wm_S3_Status::get( $archive );
			$status['filename'] = basename( $archive );
			wp_send_json_success( array( 'status' => $status ) );
		} catch ( Exception $e ) {
			wp_send_json_error( array( 'errors' => array( $e->getMessage() ) ) );
		}
	}

	/**
	 * AJAX: Get upload status for given archive.
	 */
	public static function upload_status() {
		if ( ! current_user_can( 'import' ) ) {
			wp_send_json_error( array( 'errors' => array( __( 'You are not allowed to perform this action.', AI1WM_PLUGIN_NAME ) ) ) );
		}

		$params  = stripslashes_deep( $_GET );
		$archive = isset( $params['archive'] ) ? trim( (string) $params['archive'] ) : '';

		if ( $archive === '' ) {
			wp_send_json_error( array( 'errors' => array( __( 'Missing archive.', AI1WM_PLUGIN_NAME ) ) ) );
		}

		$status = Ai1wm_S3_Status::get( $archive );
		$status['filename'] = basename( $archive );
		wp_send_json_success( array( 'status' => $status ) );
	}

	/**
	 * AJAX: List S3 objects/prefixes under configured bucket/prefix.
	 */
	public static function list_s3_objects() {
		if ( ! current_user_can( 'import' ) ) {
			wp_send_json_error( array( 'errors' => array( __( 'You are not allowed to perform this action.', AI1WM_PLUGIN_NAME ) ) ) );
		}

		$missing = Ai1wm_S3_Settings::missing_required_fields();
		if ( ! empty( $missing ) ) {
			wp_send_json_error( array( 'errors' => array( sprintf( __( 'Missing required S3 settings: %s.', AI1WM_PLUGIN_NAME ), implode( ', ', $missing ) ) ) ) );
		}

		$params  = stripslashes_deep( $_GET );
		$path    = isset( $params['path'] ) ? trim( (string) $params['path'] ) : '';
		$token   = isset( $params['token'] ) ? (string) $params['token'] : '';
		$max     = isset( $params['max'] ) ? (int) $params['max'] : 200;

		try {
			$client  = new Ai1wm_S3_Client( Ai1wm_S3_Settings::get() );
			$result  = $client->list_objects( $path, $token, $max );

			// Mark .wpress files
			foreach ( $result['objects'] as &$obj ) {
				$k = isset( $obj['key'] ) ? (string) $obj['key'] : '';
				$obj['is_backup'] = (bool) preg_match( '/\.wpress$/i', $k );
			}
			unset( $obj );

			wp_send_json_success( array( 'path' => $path, 'result' => $result ) );
		} catch ( Exception $e ) {
			wp_send_json_error( array( 'errors' => array( $e->getMessage() ) ) );
		}
	}

	/**
	 * AJAX: Download a selected S3 object (.wpress) to local backups folder.
	 */
	public static function download_from_s3() {
		if ( ! current_user_can( 'import' ) ) {
			wp_send_json_error( array( 'errors' => array( __( 'You are not allowed to perform this action.', AI1WM_PLUGIN_NAME ) ) ) );
		}

		$params     = stripslashes_deep( $_POST );
		$secret_key = isset( $params['secret_key'] ) ? trim( $params['secret_key'] ) : null;
		$key        = isset( $params['key'] ) ? ltrim( str_replace( '\\', '/', $params['key'] ), '/' ) : '';

		try {
			ai1wm_verify_secret_key( $secret_key );
		} catch ( Ai1wm_Not_Valid_Secret_Key_Exception $e ) {
			wp_send_json_error( array( 'errors' => array( $e->getMessage() ) ) );
		}

		if ( $key === '' ) {
			wp_send_json_error( array( 'errors' => array( __( 'Missing object key.', AI1WM_PLUGIN_NAME ) ) ) );
		}

		if ( ! preg_match( '/\.wpress$/i', $key ) ) {
			wp_send_json_error( array( 'errors' => array( __( 'Only .wpress files can be downloaded.', AI1WM_PLUGIN_NAME ) ) ) );
		}

		// Ensure backups folder exists and writable
		if ( ! is_dir( AI1WM_BACKUPS_PATH ) ) {
			if ( ! Ai1wm_Directory::create( AI1WM_BACKUPS_PATH ) ) {
				wp_send_json_error( array( 'errors' => array( sprintf( __( 'Unable to create backups folder: %s', AI1WM_PLUGIN_NAME ), AI1WM_BACKUPS_PATH ) ) ) );
			}
		}

		if ( ! is_writable( AI1WM_BACKUPS_PATH ) ) {
			wp_send_json_error( array( 'errors' => array( sprintf( __( 'Backups folder is not writable: %s', AI1WM_PLUGIN_NAME ), AI1WM_BACKUPS_PATH ) ) ) );
		}

		$filename   = basename( $key );
		$target     = AI1WM_BACKUPS_PATH . DIRECTORY_SEPARATOR . $filename;

		// If exists, add suffix to avoid overwrite
		if ( file_exists( $target ) ) {
			$base = preg_replace( '/\.wpress$/i', '', $filename );
			$i    = 1;
			do {
				$alt = sprintf( '%s-(%d).wpress', $base, $i );
				$target = AI1WM_BACKUPS_PATH . DIRECTORY_SEPARATOR . $alt;
				$i++;
			} while ( file_exists( $target ) && $i < 1000 );
		}

		try {
			// Switch to background job using downloader
			Ai1wm_S3_Downloader::dispatch( $key, basename( $target ) );

			$status = Ai1wm_S3_Download_Status::get( $key );
			wp_send_json_success( array( 'job' => $status ) );
		} catch ( Exception $e ) {
			wp_send_json_error( array( 'errors' => array( $e->getMessage() ) ) );
		}
	}

	/**
	 * AJAX: Get status for a download job (by key).
	 */
	public static function download_status() {
		if ( ! current_user_can( 'import' ) ) {
			wp_send_json_error( array( 'errors' => array( __( 'You are not allowed to perform this action.', AI1WM_PLUGIN_NAME ) ) ) );
		}

		$params = stripslashes_deep( $_GET );
		$key    = isset( $params['key'] ) ? ltrim( str_replace( '\\', '/', $params['key'] ), '/' ) : '';

		if ( $key === '' ) {
			wp_send_json_error( array( 'errors' => array( __( 'Missing object key.', AI1WM_PLUGIN_NAME ) ) ) );
		}

		$status = Ai1wm_S3_Download_Status::get( $key );
		wp_send_json_success( array( 'job' => $status ) );
	}

	/**
	 * AJAX: Cancel a background download job.
	 */
	public static function download_cancel() {
		if ( ! current_user_can( 'import' ) ) {
			wp_send_json_error( array( 'errors' => array( __( 'You are not allowed to perform this action.', AI1WM_PLUGIN_NAME ) ) ) );
		}

		$params     = stripslashes_deep( $_POST );
		$secret_key = isset( $params['secret_key'] ) ? trim( $params['secret_key'] ) : null;
		$key        = isset( $params['key'] ) ? ltrim( str_replace( '\\', '/', $params['key'] ), '/' ) : '';

		try {
			ai1wm_verify_secret_key( $secret_key );
		} catch ( Ai1wm_Not_Valid_Secret_Key_Exception $e ) {
			wp_send_json_error( array( 'errors' => array( $e->getMessage() ) ) );
		}

		if ( $key === '' ) {
			wp_send_json_error( array( 'errors' => array( __( 'Missing object key.', AI1WM_PLUGIN_NAME ) ) ) );
		}

		Ai1wm_S3_Downloader::cancel( $key );
		$status = Ai1wm_S3_Download_Status::get( $key );
		wp_send_json_success( array( 'job' => $status ) );
	}

	/**
	 * AJAX: Return rendered HTML rows for the local backups table body.
	 */
	public static function list_local_backups() {
		if ( ! current_user_can( 'import' ) ) {
			wp_send_json_error( array( 'errors' => array( __( 'You are not allowed to perform this action.', AI1WM_PLUGIN_NAME ) ) ) );
		}

		$model        = new Ai1wm_Backups;
		$backups      = $model->get_files();
		$s3_configured = Ai1wm_S3_Settings::is_configured();
		$statuses     = Ai1wm_S3_Status::all();

		ob_start();
		foreach ( $backups as $backup ) {
			$archive_key     = ltrim( str_replace( '\\', '/', $backup['filename'] ), '/' );
			$archive_status  = isset( $statuses[ $archive_key ] ) ? $statuses[ $archive_key ] : array();
			$archive_state   = isset( $archive_status['state'] ) ? $archive_status['state'] : '';
			$archive_message = isset( $archive_status['message'] ) ? $archive_status['message'] : '';
			$archive_remote  = isset( $archive_status['remote_key'] ) ? $archive_status['remote_key'] : '';
			$archive_updated = isset( $archive_status['updated_at'] ) ? (int) $archive_status['updated_at'] : 0;
			$archive_payload = wp_json_encode( array(
				'archive'    => $archive_key,
				'filename'   => basename( $backup['filename'] ),
				'path'       => $backup['path'],
				'state'      => $archive_state,
				'message'    => $archive_message,
				'remote_key' => $archive_remote,
				'updated_at' => $archive_updated,
				'remote_url' => isset( $archive_status['remote_url'] ) ? $archive_status['remote_url'] : '',
			) );
			?>
			<tr>
				<td class="ai1wm-column-name">
					<?php if ( $backup['path'] ) : ?>
						<i class="ai1wm-icon-folder"></i>
						<?php echo esc_html( $backup['path'] ); ?><br />
					<?php endif; ?>
					<i class="ai1wm-icon-file-zip"></i>
					<?php echo esc_html( basename( $backup['filename'] ) ); ?>
				</td>
				<td class="ai1wm-column-date"><?php echo esc_html( sprintf( __( '%s ago', AI1WM_PLUGIN_NAME ), human_time_diff( $backup['mtime'] ) ) ); ?></td>
				<td class="ai1wm-column-size">
					<?php if ( is_null( $backup['size'] ) ) : ?>
						<?php _e( '2GB+', AI1WM_PLUGIN_NAME ); ?>
					<?php else : ?>
						<?php echo size_format( $backup['size'], 2 ); ?>
					<?php endif; ?>
				</td>
				<td class="ai1wm-column-actions ai1wm-backup-actions">
					<a data-dyn="1" href="<?php echo esc_url( ai1wm_backup_url( array( 'archive' => $backup['filename'] ) ) ); ?>" class="ai1wm-button-green ai1wm-backup-download" title="<?php esc_attr_e( 'Download', AI1WM_PLUGIN_NAME ); ?>">
						<i class="ai1wm-icon-arrow-down"></i>
						<span><?php _e( 'Download', AI1WM_PLUGIN_NAME ); ?></span>
					</a>
					<a data-dyn="1" href="#" data-archive="<?php echo esc_attr( $backup['filename'] ); ?>" class="ai1wm-button-gray ai1wm-backup-restore" title="<?php esc_attr_e( 'Restore', AI1WM_PLUGIN_NAME ); ?>">
						<i class="ai1wm-icon-cloud-upload"></i>
						<span><?php _e( 'Restore', AI1WM_PLUGIN_NAME ); ?></span>
					</a>
					<a data-dyn="1" href="#" class="ai1wm-button-blue ai1wm-button-icon ai1wm-backup-s3" data-toggle="modal" data-target="#ai1wmS3LogModal" data-type="upload" data-archive="<?php echo esc_attr( $backup['filename'] ); ?>" data-filename="<?php echo esc_attr( basename( $backup['filename'] ) ); ?>" data-state="<?php echo esc_attr( $archive_state ); ?>" data-log="<?php echo esc_attr( $archive_payload ); ?>" data-remote-url="<?php echo esc_attr( isset( $archive_status['remote_url'] ) ? $archive_status['remote_url'] : '' ); ?>" title="<?php echo esc_attr( $s3_configured ? __( 'Copy this backup to your S3 storage.', AI1WM_PLUGIN_NAME ) : __( 'Configure S3 storage to enable uploads.', AI1WM_PLUGIN_NAME ) ); ?>" <?php echo $s3_configured ? '' : ' disabled="disabled" aria-disabled="true"'; ?>>
						<i class="ai1wm-icon-export"></i>
						<span><?php _e( 'Copy to S3', AI1WM_PLUGIN_NAME ); ?></span>
					</a>
					<a data-dyn="1" href="#" class="ai1wm-button-gray ai1wm-button-icon ai1wm-backup-log-button" data-toggle="modal" data-target="#ai1wmS3LogModal" data-type="log" data-archive="<?php echo esc_attr( $backup['filename'] ); ?>" data-filename="<?php echo esc_attr( basename( $backup['filename'] ) ); ?>" data-log="<?php echo esc_attr( $archive_payload ); ?>" data-remote-url="<?php echo esc_attr( isset( $archive_status['remote_url'] ) ? $archive_status['remote_url'] : '' ); ?>" title="<?php esc_attr_e( 'View remote storage log', AI1WM_PLUGIN_NAME ); ?>">
						<i class="ai1wm-icon-notification"></i>
						<span><?php _e( 'View log', AI1WM_PLUGIN_NAME ); ?></span>
					</a>
					<a data-dyn="1" href="#" data-archive="<?php echo esc_attr( $backup['filename'] ); ?>" class="ai1wm-button-red ai1wm-backup-delete" title="<?php esc_attr_e( 'Delete', AI1WM_PLUGIN_NAME ); ?>">
						<i class="ai1wm-icon-close"></i>
						<span><?php _e( 'Delete', AI1WM_PLUGIN_NAME ); ?></span>
					</a>
					<div class="ai1wm-backup-status ai1wm-hide" data-archive="<?php echo esc_attr( $backup['filename'] ); ?>" data-filename="<?php echo esc_attr( basename( $backup['filename'] ) ); ?>" data-state="<?php echo esc_attr( $archive_state ); ?>" data-remote="<?php echo esc_attr( $archive_remote ); ?>" data-remote-url="<?php echo esc_attr( isset( $archive_status['remote_url'] ) ? $archive_status['remote_url'] : '' ); ?>" data-updated="<?php echo esc_attr( $archive_updated ); ?>" data-log="<?php echo esc_attr( $archive_payload ); ?>" data-message="<?php echo esc_attr( $archive_message ); ?>" aria-live="polite" style="display:none;"></div>
				</td>
			</tr>
			<?php
		}
		$html = ob_get_clean();

		wp_send_json_success( array( 'html' => $html ) );
	}
}
