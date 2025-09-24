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

class Ai1wm_Main_Controller {

	/**
	 * Main Application Controller
	 *
	 * @return Ai1wm_Main_Controller
	 */
	public function __construct() {
		register_activation_hook( AI1WM_PLUGIN_BASENAME, array( $this, 'activation_hook' ) );

		// Activate hooks
		$this->activate_actions();
		$this->activate_filters();
	}

	/**
	 * Activation hook callback
	 *
	 * @return void
	 */
	public function activation_hook() {
		if ( is_dir( AI1WM_BACKUPS_PATH ) ) {
			$this->create_backups_htaccess( AI1WM_BACKUPS_HTACCESS );
			$this->create_backups_webconfig( AI1WM_BACKUPS_WEBCONFIG );
			$this->create_backups_index( AI1WM_BACKUPS_INDEX );
		}

		if ( extension_loaded( 'litespeed' ) ) {
			$this->create_litespeed_htaccess( AI1WM_WORDPRESS_HTACCESS );
		}
	}

	/**
	 * Initializes language domain for the plugin
	 *
	 * @return void
	 */
	public function load_text_domain() {
		load_plugin_textdomain( AI1WM_PLUGIN_NAME, false, false );
	}

	/**
	 * Register listeners for actions
	 *
	 * @return void
	 */
	private function activate_actions() {
		// Init
		add_action( 'admin_init', array( $this, 'init' ) );

		// Router
		add_action( 'admin_init', array( $this, 'router' ) );

		// Setup folders
		add_action( 'admin_init', array( $this, 'setup_folders' ) );

		// Load text domain
		add_action( 'admin_init', array( $this, 'load_text_domain' ) );

		// Admin header
		add_action( 'admin_head', array( $this, 'admin_head' ) );

		// All in One WP Migration
		add_action( 'plugins_loaded', array( $this, 'ai1wm_loaded' ), 10 );

		// Export and import commands
		add_action( 'plugins_loaded', array( $this, 'ai1wm_commands' ), 10 );

		// Export and import buttons
		add_action( 'plugins_loaded', array( $this, 'ai1wm_buttons' ), 10 );

		// Register scripts and styles
		add_action( 'admin_enqueue_scripts', array( $this, 'register_scripts_and_styles' ), 5 );

		// Enqueue export scripts and styles
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_export_scripts_and_styles' ), 5 );

		// Enqueue import scripts and styles
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_import_scripts_and_styles' ), 5 );

		// Enqueue backups scripts and styles
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_backups_scripts_and_styles' ), 5 );

		// Enqueue updater scripts and styles
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_updater_scripts_and_styles' ), 5 );

		// Process S3 upload jobs
		add_action( AI1WM_S3_CRON_HOOK, array( 'Ai1wm_S3_Uploader', 'run' ) );
	}

	/**
	 * Register listeners for filters
	 *
	 * @return void
	 */
	private function activate_filters() {
		// Add links to plugin list page
		add_filter( 'plugin_row_meta', array( $this, 'plugin_row_meta' ), 10, 2 );

		// Add custom schedules
		add_filter( 'cron_schedules', array( $this, 'add_cron_schedules' ), 9999 );
	}

	/**
	 * Export and import commands
	 *
	 * @return void
	 */
	public function ai1wm_commands() {
		// Add export commands
		add_filter( 'ai1wm_export', 'Ai1wm_Export_Init::execute', 5 );
		add_filter( 'ai1wm_export', 'Ai1wm_Export_Compatibility::execute', 5 );
		add_filter( 'ai1wm_export', 'Ai1wm_Export_Archive::execute', 10 );
		add_filter( 'ai1wm_export', 'Ai1wm_Export_Config::execute', 50 );
		add_filter( 'ai1wm_export', 'Ai1wm_Export_Config_File::execute', 60 );
		add_filter( 'ai1wm_export', 'Ai1wm_Export_Enumerate::execute', 100 );
		add_filter( 'ai1wm_export', 'Ai1wm_Export_Content::execute', 150 );
		add_filter( 'ai1wm_export', 'Ai1wm_Export_Database::execute', 200 );
		add_filter( 'ai1wm_export', 'Ai1wm_Export_Database_File::execute', 220 );
		add_filter( 'ai1wm_export', 'Ai1wm_Export_Download::execute', 250 );
		add_filter( 'ai1wm_export', 'Ai1wm_Export_Clean::execute', 300 );

		// Add import commands
		add_filter( 'ai1wm_import', 'Ai1wm_Import_Upload::execute', 5 );
		add_filter( 'ai1wm_import', 'Ai1wm_Import_Compatibility::execute', 10 );
		add_filter( 'ai1wm_import', 'Ai1wm_Import_Validate::execute', 50 );
		add_filter( 'ai1wm_import', 'Ai1wm_Import_Confirm::execute', 100 );
		add_filter( 'ai1wm_import', 'Ai1wm_Import_Blogs::execute', 150 );
		add_filter( 'ai1wm_import', 'Ai1wm_Import_Enumerate::execute', 200 );
		add_filter( 'ai1wm_import', 'Ai1wm_Import_Content::execute', 250 );
		add_filter( 'ai1wm_import', 'Ai1wm_Import_Mu_Plugins::execute', 270 );
		add_filter( 'ai1wm_import', 'Ai1wm_Import_Database::execute', 300 );
		add_filter( 'ai1wm_import', 'Ai1wm_Import_Done::execute', 350 );
		add_filter( 'ai1wm_import', 'Ai1wm_Import_Clean::execute', 400 );
	}

	/**
	 * Export and import buttons
	 *
	 * @return void
	 */
	public function ai1wm_buttons() {
		// Add export buttons
		add_filter( 'ai1wm_export_buttons', 'Ai1wm_Export_Controller::buttons' );

		// Add import buttons
		add_filter( 'ai1wm_import_buttons', 'Ai1wm_Import_Controller::buttons' );
	}

	/**
	 * All in One WP Migration loaded
	 *
	 * @return void
	 */
	public function ai1wm_loaded() {
		if ( ! defined( 'AI1WMME_PLUGIN_NAME' ) ) {
			if ( is_multisite() ) {
				add_action( 'network_admin_notices', array( $this, 'multisite_notice' ) );
			} else {
				add_action( 'admin_menu', array( $this, 'admin_menu' ) );
			}
		} else {
			if ( is_multisite() ) {
				add_action( 'network_admin_menu', array( $this, 'admin_menu' ) );
			} else {
				add_action( 'admin_menu', array( $this, 'admin_menu' ) );
			}
		}

		// Add automatic plugins update
		add_action( 'wp_maybe_auto_update', 'Ai1wm_Updater_Controller::check_for_updates' );

		// Add HTTP export headers
		add_filter( 'ai1wm_http_export_headers', 'Ai1wm_Export_Controller::http_export_headers' );

		// Add HTTP import headers
		add_filter( 'ai1wm_http_import_headers', 'Ai1wm_Import_Controller::http_import_headers' );

		// Add chunk size limit
		add_filter( 'ai1wm_max_chunk_size', 'Ai1wm_Import_Controller::max_chunk_size' );

		// Add plugins api
		add_filter( 'plugins_api', 'Ai1wm_Updater_Controller::plugins_api', 20, 3 );

		// Add plugins updates
		add_filter( 'pre_set_site_transient_update_plugins', 'Ai1wm_Updater_Controller::pre_update_plugins' );

		// Add plugins metadata
		add_filter( 'site_transient_update_plugins', 'Ai1wm_Updater_Controller::update_plugins' );

		// Add "Check for updates" link to plugin list page
		add_filter( 'plugin_row_meta', 'Ai1wm_Updater_Controller::plugin_row_meta', 10, 2 );
	}

	/**
	 * Create folders and files needed for plugin operation, if they don't exist
	 *
	 * @return void
	 */
	public function setup_folders() {
		// Check if storage folder is created
		if ( ! is_dir( AI1WM_STORAGE_PATH ) ) {
			$this->create_storage_folder( AI1WM_STORAGE_PATH );
		}

		// Check if backups folder is created
		if ( ! is_dir( AI1WM_BACKUPS_PATH ) ) {
			$this->create_backups_folder( AI1WM_BACKUPS_PATH );
		}

		// Check if index.php is created in storage folder
		if ( ! is_file( AI1WM_STORAGE_INDEX ) ) {
			$this->create_storage_index( AI1WM_STORAGE_INDEX );
		}

		// Check if index.php is created in backups folder
		if ( ! is_file( AI1WM_BACKUPS_INDEX ) ) {
			$this->create_backups_index( AI1WM_BACKUPS_INDEX );
		}

		// Check if .htaccess is created in backups folder
		if ( ! is_file( AI1WM_BACKUPS_HTACCESS ) ) {
			$this->create_backups_htaccess( AI1WM_BACKUPS_HTACCESS );
		}

		// Check if web.config is created in backups folder
		if ( ! is_file( AI1WM_BACKUPS_WEBCONFIG ) ) {
			$this->create_backups_webconfig( AI1WM_BACKUPS_WEBCONFIG );
		}
	}

	/**
	 * Create storage folder
	 *
	 * @param  string Path to folder
	 * @return void
	 */
	public function create_storage_folder( $path ) {
		if ( ! Ai1wm_Directory::create( $path ) ) {
			if ( is_multisite() ) {
				return add_action( 'network_admin_notices', array( $this, 'storage_path_notice' ) );
			} else {
				return add_action( 'admin_notices', array( $this, 'storage_path_notice' ) );
			}
		}
	}

	/**
	 * Create backups folder
	 *
	 * @param  string Path to folder
	 * @return void
	 */
	public function create_backups_folder( $path ) {
		if ( ! Ai1wm_Directory::create( $path ) ) {
			if ( is_multisite() ) {
				return add_action( 'network_admin_notices', array( $this, 'backups_path_notice' ) );
			} else {
				return add_action( 'admin_notices', array( $this, 'backups_path_notice' ) );
			}
		}
	}

	/**
	 * Create storage index.php file
	 *
	 * @param  string Path to file
	 * @return void
	 */
	public function create_storage_index( $path ) {
		if ( ! Ai1wm_File_Index::create( $path ) ) {
			if ( is_multisite() ) {
				return add_action( 'network_admin_notices', array( $this, 'storage_index_notice' ) );
			} else {
				return add_action( 'admin_notices', array( $this, 'storage_index_notice' ) );
			}
		}
	}

	/**
	 * Create backups .htaccess file
	 *
	 * @param  string Path to file
	 * @return void
	 */
	public function create_backups_htaccess( $path ) {
		if ( ! Ai1wm_File_Htaccess::create( $path ) ) {
			if ( is_multisite() ) {
				return add_action( 'network_admin_notices', array( $this, 'backups_htaccess_notice' ) );
			} else {
				return add_action( 'admin_notices', array( $this, 'backups_htaccess_notice' ) );
			}
		}
	}

	/**
	 * Create backups web.config file
	 *
	 * @param  string Path to file
	 * @return void
	 */
	public function create_backups_webconfig( $path ) {
		if ( ! Ai1wm_File_Webconfig::create( $path ) ) {
			if ( is_multisite() ) {
				return add_action( 'network_admin_notices', array( $this, 'backups_webconfig_notice' ) );
			} else {
				return add_action( 'admin_notices', array( $this, 'backups_webconfig_notice' ) );
			}
		}
	}

	/**
	 * Create backups index.php file
	 *
	 * @param  string Path to file
	 * @return void
	 */
	public function create_backups_index( $path ) {
		if ( ! Ai1wm_File_Index::create( $path ) ) {
			if ( is_multisite() ) {
				return add_action( 'network_admin_notices', array( $this, 'backups_index_notice' ) );
			} else {
				return add_action( 'admin_notices', array( $this, 'backups_index_notice' ) );
			}
		}
	}

	/**
	 * If the "noabort" environment variable has been set,
	 * the script will continue to run even though the connection has been broken
	 *
	 * @return void
	 */
	public function create_litespeed_htaccess( $path ) {
		if ( ! Ai1wm_File_Htaccess::litespeed( $path ) ) {
			if ( is_multisite() ) {
				return add_action( 'network_admin_notices', array( $this, 'wordpress_htaccess_notice' ) );
			} else {
				return add_action( 'admin_notices', array( $this, 'wordpress_htaccess_notice' ) );
			}
		}
	}

	/**
	 * Display multisite notice
	 *
	 * @return void
	 */
	public function multisite_notice() {
		Ai1wm_Template::render( 'main/multisite-notice' );
	}

	/**
	 * Display notice for storage directory
	 *
	 * @return void
	 */
	public function storage_path_notice() {
		Ai1wm_Template::render( 'main/storage-path-notice' );
	}

	/**
	 * Display notice for index file in storage directory
	 *
	 * @return void
	 */
	public function storage_index_notice() {
		Ai1wm_Template::render( 'main/storage-index-notice' );
	}

	/**
	 * Display notice for backups directory
	 *
	 * @return void
	 */
	public function backups_path_notice() {
		Ai1wm_Template::render( 'main/backups-path-notice' );
	}

	/**
	 * Display notice for .htaccess file in backups directory
	 *
	 * @return void
	 */
	public function backups_htaccess_notice() {
		Ai1wm_Template::render( 'main/backups-htaccess-notice' );
	}

	/**
	 * Display notice for web.config file in backups directory
	 *
	 * @return void
	 */
	public function backups_webconfig_notice() {
		Ai1wm_Template::render( 'main/backups-webconfig-notice' );
	}

	/**
	 * Display notice for index file in backups directory
	 *
	 * @return void
	 */
	public function backups_index_notice() {
		Ai1wm_Template::render( 'main/backups-index-notice' );
	}

	/**
	 * Display notice for .htaccess file in WordPress directory
	 *
	 * @return void
	 */
	public function wordpress_htaccess_notice() {
		Ai1wm_Template::render( 'main/wordpress-htaccess-notice' );
	}

	/**
	 * Add links to plugin list page
	 *
	 * @return array
	 */
	public function plugin_row_meta( $links, $file ) {
		if ( $file == AI1WM_PLUGIN_BASENAME ) {
			$links[] = Ai1wm_Template::get_content( 'main/get-support' );
		}

		return $links;
	}

	/**
	 * Register plugin menus
	 *
	 * @return void
	 */
	public function admin_menu() {
		// Top-level WP Migration menu
		add_menu_page(
			'All-in-One WP Migration',
			'All-in-One WP Migration',
			'export',
			'ai1wm_export',
			'Ai1wm_Export_Controller::index',
			'',
			'76.295'
		);

		// Sub-level Export menu
		add_submenu_page(
			'ai1wm_export',
			__( 'Export', AI1WM_PLUGIN_NAME ),
			__( 'Export', AI1WM_PLUGIN_NAME ),
			'export',
			'ai1wm_export',
			'Ai1wm_Export_Controller::index'
		);

		// Sub-level Import menu
		add_submenu_page(
			'ai1wm_export',
			__( 'Import', AI1WM_PLUGIN_NAME ),
			__( 'Import', AI1WM_PLUGIN_NAME ),
			'import',
			'ai1wm_import',
			'Ai1wm_Import_Controller::index'
		);

		// Sub-level Backups menu
		add_submenu_page(
			'ai1wm_export',
			__( 'Backups', AI1WM_PLUGIN_NAME ),
			__( 'Backups', AI1WM_PLUGIN_NAME ),
			'import',
			'ai1wm_backups',
			'Ai1wm_Backups_Controller::index'
		);
	}

	/**
	 * Register scripts and styles
	 *
	 * @return void
	 */
	public function register_scripts_and_styles() {
		if ( is_rtl() ) {
			wp_register_style(
				'ai1wm_servmask',
				Ai1wm_Template::asset_link( 'css/servmask.min.rtl.css' )
			);
		} else {
			wp_register_style(
				'ai1wm_servmask',
				Ai1wm_Template::asset_link( 'css/servmask.min.css' )
			);
		}

		wp_register_script(
			'ai1wm_util',
			Ai1wm_Template::asset_link( 'javascript/util.min.js' ),
			array( 'jquery' )
		);

		wp_register_script(
			'ai1wm_feedback',
			Ai1wm_Template::asset_link( 'javascript/feedback.min.js' ),
			array( 'ai1wm_util' )
		);

		wp_register_script(
			'ai1wm_report',
			Ai1wm_Template::asset_link( 'javascript/report.min.js' ),
			array( 'ai1wm_util' )
		);
	}

	/**
	 * Enqueue scripts and styles for Export Controller
	 *
	 * @param  string $hook Hook suffix
	 * @return void
	 */
	public function enqueue_export_scripts_and_styles( $hook ) {
		if ( stripos( 'toplevel_page_ai1wm_export', $hook ) === false ) {
			return;
		}

		// We don't want heartbeat to occur when exporting
		wp_deregister_script( 'heartbeat' );

		// We don't want auth check for monitoring whether the user is still logged in
		remove_action( 'admin_enqueue_scripts', 'wp_auth_check_load' );

		if ( is_rtl() ) {
			wp_enqueue_style(
				'ai1wm_export',
				Ai1wm_Template::asset_link( 'css/export.min.rtl.css' )
			);
		} else {
			wp_enqueue_style(
				'ai1wm_export',
				Ai1wm_Template::asset_link( 'css/export.min.css' )
			);
		}

		wp_enqueue_script(
			'ai1wm_export',
			Ai1wm_Template::asset_link( 'javascript/export.min.js' ),
			array( 'ai1wm_util' )
		);

		wp_localize_script( 'ai1wm_export', 'ai1wm_feedback', array(
			'ajax'       => array(
				'url' => wp_make_link_relative( admin_url( 'admin-ajax.php?action=ai1wm_feedback' ) ),
			),
			'secret_key' => get_option( AI1WM_SECRET_KEY ),
		) );

		wp_localize_script( 'ai1wm_export', 'ai1wm_report', array(
			'ajax'       => array(
				'url' => wp_make_link_relative( admin_url( 'admin-ajax.php?action=ai1wm_report' ) ),
			),
			'secret_key' => get_option( AI1WM_SECRET_KEY ),
		) );

		wp_localize_script( 'ai1wm_export', 'ai1wm_export', array(
			'ajax'       => array(
				'url' => wp_make_link_relative( admin_url( 'admin-ajax.php?action=ai1wm_export' ) ),
			),
			'status'     => array(
				'url' => wp_make_link_relative( add_query_arg( array( 'secret_key' => get_option( AI1WM_SECRET_KEY ) ), admin_url( 'admin-ajax.php?action=ai1wm_status' ) ) ),
			),
			'secret_key' => get_option( AI1WM_SECRET_KEY ),
		) );

		wp_localize_script( 'ai1wm_export', 'ai1wm_locale', array(
			'stop_exporting_your_website'         => __( 'You are about to stop exporting your website, are you sure?', AI1WM_PLUGIN_NAME ),
			'preparing_to_export'                 => __( 'Preparing to export...', AI1WM_PLUGIN_NAME ),
			'unable_to_export'                    => __( 'Unable to export', AI1WM_PLUGIN_NAME ),
			'unable_to_start_the_export'          => __( 'Unable to start the export. Refresh the page and try again', AI1WM_PLUGIN_NAME ),
			'unable_to_run_the_export'            => __( 'Unable to run the export. Refresh the page and try again', AI1WM_PLUGIN_NAME ),
			'unable_to_stop_the_export'           => __( 'Unable to stop the export. Refresh the page and try again', AI1WM_PLUGIN_NAME ),
			'please_wait_stopping_the_export'     => __( 'Please wait, stopping the export...', AI1WM_PLUGIN_NAME ),
			'close_export'                        => __( 'Close', AI1WM_PLUGIN_NAME ),
			'stop_export'                         => __( 'Stop export', AI1WM_PLUGIN_NAME ),
			'leave_feedback'                      => __( 'Leave plugin developers any feedback here', AI1WM_PLUGIN_NAME ),
			'how_may_we_help_you'                 => __( 'How may we help you?', AI1WM_PLUGIN_NAME ),
			'thanks_for_submitting_your_feedback' => __( 'Thanks for submitting your feedback!', AI1WM_PLUGIN_NAME ),
			'thanks_for_submitting_your_request'  => __( 'Thanks for submitting your request!', AI1WM_PLUGIN_NAME ),
		) );
	}

	/**
	 * Enqueue scripts and styles for Import Controller
	 *
	 * @param  string $hook Hook suffix
	 * @return void
	 */
	public function enqueue_import_scripts_and_styles( $hook ) {
		if ( stripos( 'all-in-one-wp-migration_page_ai1wm_import', $hook ) === false ) {
			return;
		}

		// We don't want heartbeat to occur when importing
		wp_deregister_script( 'heartbeat' );

		// We don't want auth check for monitoring whether the user is still logged in
		remove_action( 'admin_enqueue_scripts', 'wp_auth_check_load' );

		if ( is_rtl() ) {
			wp_enqueue_style(
				'ai1wm_import',
				Ai1wm_Template::asset_link( 'css/import.min.rtl.css' )
			);
		} else {
			wp_enqueue_style(
				'ai1wm_import',
				Ai1wm_Template::asset_link( 'css/import.min.css' )
			);
		}

		wp_enqueue_script(
			'ai1wm_import',
			Ai1wm_Template::asset_link( 'javascript/import.min.js' ),
			array( 'ai1wm_util' )
		);

		wp_localize_script( 'ai1wm_import', 'ai1wm_feedback', array(
			'ajax'       => array(
				'url' => wp_make_link_relative( admin_url( 'admin-ajax.php?action=ai1wm_feedback' ) ),
			),
			'secret_key' => get_option( AI1WM_SECRET_KEY ),
		) );

		wp_localize_script( 'ai1wm_import', 'ai1wm_report', array(
			'ajax'       => array(
				'url' => wp_make_link_relative( admin_url( 'admin-ajax.php?action=ai1wm_report' ) ),
			),
			'secret_key' => get_option( AI1WM_SECRET_KEY ),
		) );

		wp_localize_script( 'ai1wm_import', 'ai1wm_uploader', array(
			'chunk_size'  => apply_filters( 'ai1wm_max_chunk_size', AI1WM_MAX_CHUNK_SIZE ),
			'max_retries' => apply_filters( 'ai1wm_max_chunk_retries', AI1WM_MAX_CHUNK_RETRIES ),
			'url'         => wp_make_link_relative( admin_url( 'admin-ajax.php?action=ai1wm_import' ) ),
			'params'      => array(
				'priority'   => 5,
				'secret_key' => get_option( AI1WM_SECRET_KEY ),
			),
			'filters'     => array(
				'ai1wm_archive_extension' => array( 'wpress' ),
				'ai1wm_archive_size'      => apply_filters( 'ai1wm_max_file_size', AI1WM_MAX_FILE_SIZE ),
			),
		) );

		wp_localize_script( 'ai1wm_import', 'ai1wm_import', array(
			'ajax'       => array(
				'url' => wp_make_link_relative( admin_url( 'admin-ajax.php?action=ai1wm_import' ) ),
			),
			'status'     => array(
				'url' => wp_make_link_relative( add_query_arg( array( 'secret_key' => get_option( AI1WM_SECRET_KEY ) ), admin_url( 'admin-ajax.php?action=ai1wm_status' ) ) ),
			),
			'secret_key' => get_option( AI1WM_SECRET_KEY ),
		) );

		wp_localize_script( 'ai1wm_import', 'ai1wm_locale', array(
			'stop_importing_your_website'         => __( 'You are about to stop importing your website, are you sure?', AI1WM_PLUGIN_NAME ),
			'preparing_to_import'                 => __( 'Preparing to import...', AI1WM_PLUGIN_NAME ),
			'unable_to_import'                    => __( 'Unable to import', AI1WM_PLUGIN_NAME ),
			'unable_to_start_the_import'          => __( 'Unable to start the import. Refresh the page and try again', AI1WM_PLUGIN_NAME ),
			'unable_to_confirm_the_import'        => __( 'Unable to confirm the import. Refresh the page and try again', AI1WM_PLUGIN_NAME ),
			'unable_to_prepare_blogs_on_import'   => __( 'Unable to prepare blogs on import. Refresh the page and try again', AI1WM_PLUGIN_NAME ),
			'unable_to_stop_the_import'           => __( 'Unable to stop the import. Refresh the page and try again', AI1WM_PLUGIN_NAME ),
			'please_wait_stopping_the_export'     => __( 'Please wait, stopping the import...', AI1WM_PLUGIN_NAME ),
			'close_import'                        => __( 'Close', AI1WM_PLUGIN_NAME ),
			'stop_import'                         => __( 'Stop import', AI1WM_PLUGIN_NAME ),
			'confirm_import'                      => __( 'Proceed', AI1WM_PLUGIN_NAME ),
			'continue_import'                     => __( 'Continue', AI1WM_PLUGIN_NAME ),
			'please_do_not_close_this_browser'    => __( 'Please do not close this browser window or your import will fail', AI1WM_PLUGIN_NAME ),
			'leave_feedback'                      => __( 'Leave plugin developers any feedback here', AI1WM_PLUGIN_NAME ),
			'how_may_we_help_you'                 => __( 'How may we help you?', AI1WM_PLUGIN_NAME ),
			'thanks_for_submitting_your_feedback' => __( 'Thanks for submitting your feedback!', AI1WM_PLUGIN_NAME ),
			'thanks_for_submitting_your_request'  => __( 'Thanks for submitting your request!', AI1WM_PLUGIN_NAME ),
			'problem_while_uploading_your_file'   => __( 'We are sorry, there seems to be a problem while uploading your file. Follow <a href="https://www.youtube.com/watch?v=mRp7qTFYKgs" target="_blank">this guide</a> to resolve it.', AI1WM_PLUGIN_NAME ),
			'invalid_archive_extension'           => __(
				'The file type that you have tried to upload is not compatible with this plugin. ' .
				'Please ensure that your file is a <strong>.wpress</strong> file that was created with the All-in-One WP migration plugin. ' .
				'<a href="https://help.servmask.com/knowledgebase/invalid-backup-file/" target="_blank">Technical details</a>',
				AI1WM_PLUGIN_NAME
			),
			'invalid_archive_size'                => sprintf(
				__(
					'The file that you are trying to import is over the maximum upload file size limit of <strong>%s</strong>.<br />' .
					'You can remove this restriction by purchasing our ' .
					'<a href="https://servmask.com/products/unlimited-extension" target="_blank">Unlimited Extension</a>.',
					AI1WM_PLUGIN_NAME
				),
				size_format( apply_filters( 'ai1wm_max_file_size', AI1WM_MAX_FILE_SIZE ) )
			),
		) );
	}

	/**
	 * Enqueue scripts and styles for Backups Controller
	 *
	 * @param  string $hook Hook suffix
	 * @return void
	 */
	public function enqueue_backups_scripts_and_styles( $hook ) {
		if ( stripos( 'all-in-one-wp-migration_page_ai1wm_backups', $hook ) === false ) {
			return;
		}

		// We don't want heartbeat to occur when restoring
		wp_deregister_script( 'heartbeat' );

		// We don't want auth check for monitoring whether the user is still logged in
		remove_action( 'admin_enqueue_scripts', 'wp_auth_check_load' );

		if ( is_rtl() ) {
			wp_enqueue_style(
				'ai1wm_backups',
				Ai1wm_Template::asset_link( 'css/backups.min.rtl.css' )
			);
		} else {
			wp_enqueue_style(
				'ai1wm_backups',
				Ai1wm_Template::asset_link( 'css/backups.min.css' )
			);
		}

		// Bootstrap 4 (requested): CSS + JS enqueued only on Backups page
		wp_enqueue_style(
			'ai1wm_bootstrap4',
			'https://cdn.jsdelivr.net/npm/bootstrap@4.0.0/dist/css/bootstrap.min.css',
			array(),
			'4.0.0'
		);

		// Ensure jQuery is present first (WordPress core)
		wp_enqueue_script( 'jquery' );

		// Popper + Bootstrap JS (depend on jQuery)
		wp_enqueue_script(
			'ai1wm_popper',
			'https://cdn.jsdelivr.net/npm/popper.js@1.12.9/dist/umd/popper.min.js',
			array( 'jquery' ),
			'1.12.9',
			true
		);

		wp_enqueue_script(
			'ai1wm_bootstrap4',
			'https://cdn.jsdelivr.net/npm/bootstrap@4.0.0/dist/js/bootstrap.min.js',
			array( 'jquery', 'ai1wm_popper' ),
			'4.0.0',
			true
		);

		$custom_css = 'body #ai1wm-s3-settings .ai1wm-backups-s3__heading, '
			. 'body #ai1wm-s3-settings .ai1wm-backups-s3__heading h2, '
			. 'body #ai1wm-s3-settings .ai1wm-backups-s3__description { text-align:left !important; }'
			. 'body .ai1wm-backups-s3__heading { text-align:left; margin:15px 0; }'
			. 'body .ai1wm-backups-s3__heading h2 { margin:0; display:flex; align-items:center; gap:8px; font-size:20px; }'
			. 'body .ai1wm-backups-s3__heading .ai1wm-icon-cloud-upload { font-size:20px; }'
			. 'body .ai1wm-s3-config-heading .ai1wm-icon-export { font-size:20px; }'
			. 'body .ai1wm-backups-s3__description { margin:4px 0 0; color:#4c4c4c; text-align:left; }'
			. 'body .ai1wm-s3-form .description { margin-top:6px; font-size:12px; color:#6d6d6d; }'
			. 'body .ai1wm-backups-s3-activity { margin-top:32px; }'
			. 'body .ai1wm-backups-s3-activity h3 { margin-bottom:12px; }'
			. 'body .ai1wm-backups-s3-activity .ai1wm-backups { margin-bottom:0; }'
			. 'body .ai1wm-backups-s3-activity .ai1wm-backups td, body .ai1wm-backups-s3-activity .ai1wm-backups th { vertical-align:middle; }'
			. 'body .ai1wm-backups-logs-empty { margin:0; color:#707070; }'
			. 'body .ai1wm-button-icon { display:inline-flex; align-items:center; justify-content:center; gap:8px; border-radius:999px; padding:0; min-width:36px; height:36px; transition:padding 0.18s ease, min-width 0.18s ease, background-color 0.2s ease, color 0.2s ease; overflow:hidden; line-height:1; }'
			. 'body .ai1wm-button-icon i { flex-shrink:0; }'
			. 'body .ai1wm-button-icon span { opacity:0; max-width:0; overflow:hidden; white-space:nowrap; transition:opacity 0.18s ease, max-width 0.18s ease; }'
			. 'body .ai1wm-button-icon:hover, body .ai1wm-button-icon:focus, body .ai1wm-button-icon:focus-visible { padding:0 14px 0 12px; }'
			. 'body .ai1wm-button-icon:hover span, body .ai1wm-button-icon:focus span, body .ai1wm-button-icon:focus-visible span { opacity:1; max-width:160px; }'
			. 'body .ai1wm-backup-actions .ai1wm-button-icon { min-width:42px; height:42px; margin-right:8px; }'
			. 'body .ai1wm-backup-actions .ai1wm-button-icon:last-child { margin-right:0; }'
			. 'body .ai1wm-backups-logs .ai1wm-button-icon { min-width:36px; height:36px; margin-right:0; }'
			. 'body .ai1wm-backup-log-content { margin:0; max-height:260px; overflow:auto; background:#f8f9f9; border-radius:6px; padding:16px; font-size:13px; line-height:1.5; }'
			. 'body .ai1wm-backup-status { display:none !important; }'
			. 'body .ai1wm-secret-input { position:relative; display:flex; align-items:center; }'
			. 'body .ai1wm-secret-input input { flex:1 1 auto; padding-right:44px; }'
			. 'body .ai1wm-secret-toggle { position:absolute; right:10px; background:transparent; border:0; padding:4px; display:inline-flex; align-items:center; justify-content:center; cursor:pointer; color:#4c4c4c; }'
			. 'body .ai1wm-secret-toggle .dashicons { font-size:18px; width:auto; height:auto; line-height:1; }'
			. 'body .ai1wm-secret-toggle:focus { outline:2px solid #2271b1; outline-offset:2px; }'
			. 'body .ai1wm-secret-toggle[data-visible="true"] .dashicons { color:#2271b1; }'
			. 'body .ai1wm-backup-log-content a { color:#2271b1; text-decoration:underline; display:inline-block; margin-top:8px; }'
			. 'body .ai1wm-s3-config-actions { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:8px; }'
			. 'body .ai1wm-s3-config-input { width:100%; resize:vertical; min-height:72px; font-family:Menlo,Monaco,monospace; font-size:12px; padding:10px; }'
			. 'body .ai1wm-s3-config-feedback { margin:6px 0 0; font-size:12px; color:#2271b1; }'
			. 'body .ai1wm-s3-config-feedback.ai1wm-error { color:#d63638; }'
			. 'body .ai1wm-s3-config-feedback.ai1wm-success { color:#1a7f37; }'
			. 'body #ai1wm-s3-settings .ai1wm-message { margin-top:0; }';

		wp_add_inline_style( 'ai1wm_backups', $custom_css );

		wp_enqueue_script(
			'ai1wm_backups',
			Ai1wm_Template::asset_link( 'javascript/backups.min.js' ),
			array( 'ai1wm_util' )
		);


		wp_enqueue_script(
			'ai1wm_backups_s3',
			Ai1wm_Template::asset_link( 'javascript/backups-s3.js' ),
			array( 'ai1wm_backups', 'jquery', 'ai1wm_bootstrap4' ),
			AI1WM_VERSION . '-s3-config-1',
			true
		);

		wp_localize_script( 'ai1wm_backups', 'ai1wm_feedback', array(
			'ajax'       => array(
				'url' => wp_make_link_relative( admin_url( 'admin-ajax.php?action=ai1wm_feedback' ) ),
			),
			'secret_key' => get_option( AI1WM_SECRET_KEY ),
		) );

		wp_localize_script( 'ai1wm_backups', 'ai1wm_report', array(
			'ajax'       => array(
				'url' => wp_make_link_relative( admin_url( 'admin-ajax.php?action=ai1wm_report' ) ),
			),
			'secret_key' => get_option( AI1WM_SECRET_KEY ),
		) );

		wp_localize_script( 'ai1wm_backups', 'ai1wm_import', array(
			'ajax'       => array(
				'url' => wp_make_link_relative( admin_url( 'admin-ajax.php?action=ai1wm_import' ) ),
			),
			'status'     => array(
				'url' => wp_make_link_relative( add_query_arg( array( 'secret_key' => get_option( AI1WM_SECRET_KEY ) ), admin_url( 'admin-ajax.php?action=ai1wm_status' ) ) ),
			),
			'secret_key' => get_option( AI1WM_SECRET_KEY ),
		) );

		wp_localize_script( 'ai1wm_backups', 'ai1wm_backups', array(
			'ajax'       => array(
				'url' => wp_make_link_relative( admin_url( 'admin-ajax.php?action=ai1wm_backups' ) ),
			),
			'secret_key' => get_option( AI1WM_SECRET_KEY ),
		) );

		wp_localize_script( 'ai1wm_backups', 'ai1wm_locale', array(
			'stop_importing_your_website'         => __( 'You are about to stop importing your website, are you sure?', AI1WM_PLUGIN_NAME ),
			'preparing_to_import'                 => __( 'Preparing to import...', AI1WM_PLUGIN_NAME ),
			'unable_to_import'                    => __( 'Unable to import', AI1WM_PLUGIN_NAME ),
			'unable_to_start_the_import'          => __( 'Unable to start the import. Refresh the page and try again', AI1WM_PLUGIN_NAME ),
			'unable_to_confirm_the_import'        => __( 'Unable to confirm the import. Refresh the page and try again', AI1WM_PLUGIN_NAME ),
			'unable_to_prepare_blogs_on_import'   => __( 'Unable to prepare blogs on import. Refresh the page and try again', AI1WM_PLUGIN_NAME ),
			'unable_to_stop_the_import'           => __( 'Unable to stop the import. Refresh the page and try again', AI1WM_PLUGIN_NAME ),
			'please_wait_stopping_the_export'     => __( 'Please wait, stopping the import...', AI1WM_PLUGIN_NAME ),
			'close_import'                        => __( 'Close', AI1WM_PLUGIN_NAME ),
			'stop_import'                         => __( 'Stop import', AI1WM_PLUGIN_NAME ),
			'confirm_import'                      => __( 'Proceed', AI1WM_PLUGIN_NAME ),
			'continue_import'                     => __( 'Continue', AI1WM_PLUGIN_NAME ),
			'please_do_not_close_this_browser'    => __( 'Please do not close this browser window or your import will fail', AI1WM_PLUGIN_NAME ),
			'leave_feedback'                      => __( 'Leave plugin developers any feedback here', AI1WM_PLUGIN_NAME ),
			'how_may_we_help_you'                 => __( 'How may we help you?', AI1WM_PLUGIN_NAME ),
			'thanks_for_submitting_your_feedback' => __( 'Thanks for submitting your feedback!', AI1WM_PLUGIN_NAME ),
			'thanks_for_submitting_your_request'  => __( 'Thanks for submitting your request!', AI1WM_PLUGIN_NAME ),
			'want_to_delete_this_file'            => __( 'Are you sure you want to delete this file?', AI1WM_PLUGIN_NAME ),
		) );

		$ai1wm_s3_statuses = Ai1wm_S3_Status::all();
		if ( is_array( $ai1wm_s3_statuses ) ) {
			foreach ( $ai1wm_s3_statuses as $activity_archive => &$activity_status ) {
				if ( is_array( $activity_status ) ) {
					$activity_status['filename'] = basename( $activity_archive );
				}
			}
			unset( $activity_status );
		}

		wp_localize_script( 'ai1wm_backups_s3', 'ai1wm_s3', array(
			'ajax'       => array(
				'url' => wp_make_link_relative( admin_url( 'admin-ajax.php?action=ai1wm_s3_upload' ) ),
			),
			'secret_key' => get_option( AI1WM_SECRET_KEY ),
			'configured' => Ai1wm_S3_Settings::is_configured(),
			'statuses'   => $ai1wm_s3_statuses,
			'strings'    => array(
				'not_configured' => __( 'Configure S3 storage to enable uploads.', AI1WM_PLUGIN_NAME ),
				'saving_required'=> __( 'Save your S3 settings before copying a backup.', AI1WM_PLUGIN_NAME ),
				'missing_fields' => __( 'Please complete: %s', AI1WM_PLUGIN_NAME ),
				'preparing'      => __( 'Scheduling upload...', AI1WM_PLUGIN_NAME ),
				'failed_prefix'  => __( 'Upload failed:', AI1WM_PLUGIN_NAME ),
				'generic_error'  => __( 'Unexpected error while uploading to S3.', AI1WM_PLUGIN_NAME ),
				'view_log'       => __( 'View log', AI1WM_PLUGIN_NAME ),
				'copy_blocked_active' => __( 'An upload is already %s for this backup. Please wait for it to finish before copying again.', AI1WM_PLUGIN_NAME ),
				'confirm_replace' => __( 'A remote copy already exists (%s). Replace it? This will delete the existing remote backup before uploading again.', AI1WM_PLUGIN_NAME ),
				'replace_cancelled' => __( 'Upload cancelled.', AI1WM_PLUGIN_NAME ),
				'replacing'      => __( 'Replacing remote backup...', AI1WM_PLUGIN_NAME ),
				'replace_message' => __( 'The existing remote backup (%s) will be deleted before uploading a fresh copy.', AI1WM_PLUGIN_NAME ),
				'show_secret'    => __( 'Show secret access key', AI1WM_PLUGIN_NAME ),
				'hide_secret'    => __( 'Hide secret access key', AI1WM_PLUGIN_NAME ),
				'remote_url_text'=> __( 'Open remote backup (%s)', AI1WM_PLUGIN_NAME ),
				'config_export_success' => __( 'Configuration copied to clipboard.', AI1WM_PLUGIN_NAME ),
				'config_export_error'   => __( 'Unable to copy configuration. Please copy manually.', AI1WM_PLUGIN_NAME ),
				'config_import_success' => __( 'Configuration applied. Review and save to persist.', AI1WM_PLUGIN_NAME ),
				'config_import_error'   => __( 'Paste configuration JSON before applying.', AI1WM_PLUGIN_NAME ),
				'config_import_invalid' => __( 'Configuration format is invalid. Please check the JSON.', AI1WM_PLUGIN_NAME ),
				'col_backup'     => __( 'Backup', AI1WM_PLUGIN_NAME ),
				'col_destination'=> __( 'Destination', AI1WM_PLUGIN_NAME ),
				'col_status'     => __( 'Status', AI1WM_PLUGIN_NAME ),
				'col_updated'    => __( 'Updated', AI1WM_PLUGIN_NAME ),
				'col_logs'       => __( 'Logs', AI1WM_PLUGIN_NAME ),
				'destination'    => __( 'Destination: %s', AI1WM_PLUGIN_NAME ),
				'updated'        => __( 'Updated %s ago', AI1WM_PLUGIN_NAME ),
				'no_log'         => __( 'No log message available yet.', AI1WM_PLUGIN_NAME ),
				'modal_title'    => __( 'Remote storage log', AI1WM_PLUGIN_NAME ),
				'modal_title_with_name' => __( 'Remote storage log: %s', AI1WM_PLUGIN_NAME ),
				'time_second'    => __( 'a second', AI1WM_PLUGIN_NAME ),
				'time_seconds'   => __( '%s seconds', AI1WM_PLUGIN_NAME ),
				'time_minute'    => __( 'a minute', AI1WM_PLUGIN_NAME ),
				'time_minutes'   => __( '%s minutes', AI1WM_PLUGIN_NAME ),
				'time_hour'      => __( 'an hour', AI1WM_PLUGIN_NAME ),
				'time_hours'     => __( '%s hours', AI1WM_PLUGIN_NAME ),
				'time_day'       => __( 'a day', AI1WM_PLUGIN_NAME ),
				'time_days'      => __( '%s days', AI1WM_PLUGIN_NAME ),
				'state_labels'   => array(
					'queued'      => __( 'Queued', AI1WM_PLUGIN_NAME ),
					'in_progress' => __( 'In progress', AI1WM_PLUGIN_NAME ),
					'success'     => __( 'Completed', AI1WM_PLUGIN_NAME ),
					'failed'      => __( 'Failed', AI1WM_PLUGIN_NAME ),
					'pending'     => __( 'Pending', AI1WM_PLUGIN_NAME ),
				),
			),
		) );
	}

	/**
	 * Enqueue scripts and styles for Updater Controller
	 *
	 * @param  string $hook Hook suffix
	 * @return void
	 */
	public function enqueue_updater_scripts_and_styles( $hook ) {
		if ( 'plugins.php' !== strtolower( $hook ) ) {
			return;
		}

		if ( is_rtl() ) {
			wp_enqueue_style(
				'ai1wm_updater',
				Ai1wm_Template::asset_link( 'css/updater.min.rtl.css' )
			);
		} else {
			wp_enqueue_style(
				'ai1wm_updater',
				Ai1wm_Template::asset_link( 'css/updater.min.css' )
			);
		}

		wp_enqueue_script(
			'ai1wm_updater',
			Ai1wm_Template::asset_link( 'javascript/updater.min.js' ),
			array( 'ai1wm_util' )
		);

		wp_localize_script( 'ai1wm_updater', 'ai1wm_updater', array(
			'ajax' => array(
				'url' => wp_make_link_relative( admin_url( 'admin-ajax.php?action=ai1wm_updater' ) ),
			),
		) );

		wp_localize_script( 'ai1wm_updater', 'ai1wm_locale', array(
			'check_for_updates'   => __( 'Check for updates', AI1WM_PLUGIN_NAME ),
			'invalid_purchase_id' => __( 'Your purchase ID is invalid, please <a href="mailto:support@servmask.com">contact us</a>', AI1WM_PLUGIN_NAME ),
		) );
	}

	/**
	 * Outputs menu icon between head tags
	 *
	 * @return void
	 */
	public function admin_head() {
		global $wp_version;

		// Admin header
		Ai1wm_Template::render( 'main/admin-head', array( 'version' => $wp_version ) );
	}

	/**
	 * Register initial parameters
	 *
	 * @return void
	 */
	public function init() {

		// Set secret key
		if ( ! get_option( AI1WM_SECRET_KEY ) ) {
			update_option( AI1WM_SECRET_KEY, wp_generate_password( 12, false ) );
		}

		// Set username
		if ( isset( $_SERVER['PHP_AUTH_USER'] ) ) {
			update_option( AI1WM_AUTH_USER, $_SERVER['PHP_AUTH_USER'] );
		} elseif ( isset( $_SERVER['REMOTE_USER'] ) ) {
			update_option( AI1WM_AUTH_USER, $_SERVER['REMOTE_USER'] );
		}

		// Set password
		if ( isset( $_SERVER['PHP_AUTH_PW'] ) ) {
			update_option( AI1WM_AUTH_PASSWORD, $_SERVER['PHP_AUTH_PW'] );
		}

		// Check for updates
		if ( isset( $_GET['ai1wm_updater'] ) ) {
			if ( current_user_can( 'update_plugins' ) ) {
				Ai1wm_Updater::check_for_updates();
			}
		}
	}

	/**
	 * Register initial router
	 *
	 * @return void
	 */
	public function router() {
		// Public actions
		add_action( 'wp_ajax_nopriv_ai1wm_export', 'Ai1wm_Export_Controller::export' );
		add_action( 'wp_ajax_nopriv_ai1wm_import', 'Ai1wm_Import_Controller::import' );
		add_action( 'wp_ajax_nopriv_ai1wm_status', 'Ai1wm_Status_Controller::status' );
		add_action( 'wp_ajax_nopriv_ai1wm_backups', 'Ai1wm_Backups_Controller::delete' );
		add_action( 'wp_ajax_nopriv_ai1wm_feedback', 'Ai1wm_Feedback_Controller::feedback' );
		add_action( 'wp_ajax_nopriv_ai1wm_report', 'Ai1wm_Report_Controller::report' );

		// Private actions
		add_action( 'wp_ajax_ai1wm_export', 'Ai1wm_Export_Controller::export' );
		add_action( 'wp_ajax_ai1wm_import', 'Ai1wm_Import_Controller::import' );
		add_action( 'wp_ajax_ai1wm_status', 'Ai1wm_Status_Controller::status' );
		add_action( 'wp_ajax_ai1wm_backups', 'Ai1wm_Backups_Controller::delete' );
		add_action( 'wp_ajax_ai1wm_s3_upload', 'Ai1wm_Backups_Controller::upload_to_s3' );
		add_action( 'wp_ajax_ai1wm_feedback', 'Ai1wm_Feedback_Controller::feedback' );
		add_action( 'wp_ajax_ai1wm_report', 'Ai1wm_Report_Controller::report' );
		add_action( 'admin_post_ai1wm_save_s3_settings', 'Ai1wm_Backups_Controller::save_s3_settings' );

		// Update actions
		if ( current_user_can( 'update_plugins' ) ) {
			add_action( 'wp_ajax_ai1wm_updater', 'Ai1wm_Updater_Controller::updater' );
		}
	}

	/**
	 * Add custom cron schedules
	 *
	 * @param  array $schedules List of schedules
	 * @return array
	 */
	public function add_cron_schedules( $schedules ) {
		$schedules['weekly']  = array(
			'display'  => __( 'Weekly', AI1WM_PLUGIN_NAME ),
			'interval' => 60 * 60 * 24 * 7,
		);
		$schedules['monthly'] = array(
			'display'  => __( 'Monthly', AI1WM_PLUGIN_NAME ),
			'interval' => ( strtotime( '+1 month' ) - time() ),
		);

		return $schedules;
	}
}
