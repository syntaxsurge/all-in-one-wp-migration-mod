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
				Ai1wm_S3_Uploader::delete_remote( $archive );
			} catch ( Exception $e ) {
				wp_send_json_error( array( 'errors' => array( sprintf( __( 'Unable to delete existing remote backup: %s', AI1WM_PLUGIN_NAME ), $e->getMessage() ) ) ) );
			}
		}

		try {
			Ai1wm_S3_Uploader::dispatch( $archive );
			$status = Ai1wm_S3_Status::get( $archive );
			$status['filename'] = basename( $archive );
			wp_send_json_success( array( 'status' => $status ) );
		} catch ( Exception $e ) {
			wp_send_json_error( array( 'errors' => array( $e->getMessage() ) ) );
		}
	}
}
