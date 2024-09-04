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

function download_file_from_url($url, $destination) {
    $response = wp_remote_get($url, array(
        'timeout' => 300,
        'stream' => true,
        'filename' => $destination,
    ));

    if (is_wp_error($response)) {
        return false;
    }

    return true;
}


if (isset($_POST['download_backup']) && !empty($_POST['backup_file_url'])) {
    if (!isset($_POST['download_backup_nonce_field']) || !wp_verify_nonce($_POST['download_backup_nonce_field'], 'download_backup_nonce')) {
        die('Invalid request.');
    }

    $url = esc_url_raw($_POST['backup_file_url']);
    $filename = basename($url);
    $file_extension = pathinfo($filename, PATHINFO_EXTENSION);

    if ($file_extension === 'wpress') {
        $destination = WP_CONTENT_DIR . '/ai1wm-backups/' . $filename;

        if (download_file_from_url($url, $destination)) {
            echo '<div class="notice notice-success is-dismissible"><p>File downloaded successfully and saved in /wp-content/ai1wm-backups/</p></div>';
        } else {
            echo '<div class="notice notice-error is-dismissible"><p>Error downloading the file. Please check the URL and try again.</p></div>';
        }
    } else {
        echo '<div class="notice notice-error is-dismissible"><p>Invalid file extension. Please provide a .wpress file URL.</p></div>';
    }
}


?>

<div class="ai1wm-container">
	<div class="ai1wm-row">
		<div class="ai1wm-left">
			<div class="ai1wm-holder">
				<h1>
					<i class="ai1wm-icon-publish"></i>
					Download Backup File
				</h1>
				
				<form method="post" class="ai1wm-clear">
					<?php wp_nonce_field('download_backup_nonce', 'download_backup_nonce_field'); ?>
					<p>
						<label for="backup_file_url">Enter the URL of the .wpress file:</label>
					</p>
					<p>
						<input type="text" id="backup_file_url" name="backup_file_url" size="100" style="max-width: 100%;" required>
					</p>
					<div class="ai1wm-button-main">
						<input type="submit" value="Download Backup" name="download_backup" class="ai1wm-button-green">
					</div>
				</form>
			</div>
			
			<div class="ai1wm-holder">
				<h1>
					<i class="ai1wm-icon-publish"></i>
					<?php _e( 'Import Site', AI1WM_PLUGIN_NAME ); ?>
				</h1>

				<?php include AI1WM_TEMPLATES_PATH . '/common/report-problem.php'; ?>

				<form action="" method="post" id="ai1wm-import-form" class="ai1wm-clear" enctype="multipart/form-data">

					<p>
						<?php _e( 'Use the box below to upload a wpress file.', AI1WM_PLUGIN_NAME ); ?><br />
					</p>

					<?php do_action( 'ai1wm_import_left_options' ); ?>

					<?php include AI1WM_TEMPLATES_PATH . '/import/import-buttons.php'; ?>

					<input type="hidden" name="ai1wm_manual_import" value="1" />

				</form>

				<?php do_action( 'ai1wm_import_left_end' ); ?>

			</div>
			
		</div>
		<div class="ai1wm-right">
			<div class="ai1wm-sidebar">
				<div class="ai1wm-segment">
					<?php if ( ! AI1WM_DEBUG ) : ?>
						<?php include AI1WM_TEMPLATES_PATH . '/common/share-buttons.php'; ?>
					<?php endif; ?>

					<h2><?php _e( 'Leave Feedback', AI1WM_PLUGIN_NAME ); ?></h2>

					<?php include AI1WM_TEMPLATES_PATH . '/common/leave-feedback.php'; ?>
				</div>
			</div>
		</div>
	</div>
</div>
