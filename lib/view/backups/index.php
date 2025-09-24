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
?>

<div class="ai1wm-container">
	<div class="ai1wm-row">
		<div class="ai1wm-left">
			<div class="ai1wm-holder">
					<!-- Backups heading moved near table below -->

				<?php
				$ai1wm_s3_settings    = isset( $s3_settings ) ? $s3_settings : array();
				$ai1wm_s3_chunk_size  = isset( $s3_chunk_size ) ? $s3_chunk_size : AI1WM_S3_MULTIPART_CHUNK_SIZE;
				$ai1wm_s3_max_retries = isset( $s3_max_retries ) ? $s3_max_retries : AI1WM_S3_MAX_RETRIES;
				include AI1WM_TEMPLATES_PATH . '/backups/s3-settings.php';
				?>

				<?php include AI1WM_TEMPLATES_PATH . '/common/report-problem.php'; ?>

				<form action="" method="post" id="ai1wm-backups-form" class="ai1wm-clear">

					<?php if ( is_readable( AI1WM_BACKUPS_PATH ) && is_writable( AI1WM_BACKUPS_PATH ) ) : ?>
						<h3><?php _e( 'Backups', AI1WM_PLUGIN_NAME ); ?></h3>
						<?php if ( $backups ) : ?>
							<table class="ai1wm-backups">
								<thead>
									<tr>
										<th class="ai1wm-column-name"><?php _e( 'Name', AI1WM_PLUGIN_NAME ); ?></th>
										<th class="ai1wm-column-date"><?php _e( 'Date', AI1WM_PLUGIN_NAME ); ?></th>
										<th class="ai1wm-column-size"><?php _e( 'Size', AI1WM_PLUGIN_NAME ); ?></th>
										<th class="ai1wm-column-actions"></th>
									</tr>
								</thead>
								<tbody>
					<?php foreach ( $backups as $backup ) : ?>
					<?php
					$archive_key     = ltrim( str_replace( '\\', '/', $backup['filename'] ), '/' );
					$archive_status  = isset( $s3_statuses[ $archive_key ] ) ? $s3_statuses[ $archive_key ] : array();
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
					) );
					?>
					<tr>
						<td class="ai1wm-column-name">
							<?php if ( $backup['path'] ) : ?>
								<i class="ai1wm-icon-folder"></i>
								<?php echo esc_html( $backup['path'] ); ?>
												<br />
											<?php endif; ?>
											<i class="ai1wm-icon-file-zip"></i>
											<?php echo esc_html( basename( $backup['filename'] ) ); ?>
										</td>
										<td class="ai1wm-column-date">
											<?php echo esc_html( sprintf( __( '%s ago', AI1WM_PLUGIN_NAME ), human_time_diff( $backup['mtime'] ) ) ); ?>
										</td>
										<td class="ai1wm-column-size">
											<?php if ( is_null( $backup['size'] ) ) : ?>
												<?php _e( '2GB+', AI1WM_PLUGIN_NAME ); ?>
											<?php else : ?>
												<?php echo size_format( $backup['size'], 2 ); ?>
											<?php endif; ?>
										</td>
						<td class="ai1wm-column-actions ai1wm-backup-actions">
							<a href="<?php echo ai1wm_backup_url( array( 'archive' => esc_attr( $backup['filename'] ) ) ); ?>" class="ai1wm-button-green ai1wm-backup-download" title="<?php esc_attr_e( 'Download', AI1WM_PLUGIN_NAME ); ?>">
								<i class="ai1wm-icon-arrow-down"></i>
								<span><?php _e( 'Download', AI1WM_PLUGIN_NAME ); ?></span>
							</a>
							<a href="#" data-archive="<?php echo esc_attr( $backup['filename'] ); ?>" class="ai1wm-button-gray ai1wm-backup-restore" title="<?php esc_attr_e( 'Restore', AI1WM_PLUGIN_NAME ); ?>">
								<i class="ai1wm-icon-cloud-upload"></i>
								<span><?php _e( 'Restore', AI1WM_PLUGIN_NAME ); ?></span>
							</a>
							<a href="#" class="ai1wm-button-blue ai1wm-button-icon ai1wm-backup-s3" data-toggle="modal" data-target="#ai1wmS3LogModal" data-type="upload" data-archive="<?php echo esc_attr( $backup['filename'] ); ?>" data-filename="<?php echo esc_attr( basename( $backup['filename'] ) ); ?>" data-state="<?php echo esc_attr( $archive_state ); ?>" data-log="<?php echo esc_attr( $archive_payload ); ?>" title="<?php echo esc_attr( $s3_configured ? __( 'Copy this backup to your S3 storage.', AI1WM_PLUGIN_NAME ) : __( 'Configure S3 storage to enable uploads.', AI1WM_PLUGIN_NAME ) ); ?>" <?php echo $s3_configured ? '' : ' disabled="disabled" aria-disabled="true"'; ?>>
								<i class="ai1wm-icon-export"></i>
								<span><?php _e( 'Copy to S3', AI1WM_PLUGIN_NAME ); ?></span>
							</a>
							<a href="#" class="ai1wm-button-gray ai1wm-button-icon ai1wm-backup-log-button" data-toggle="modal" data-target="#ai1wmS3LogModal" data-type="log" data-archive="<?php echo esc_attr( $backup['filename'] ); ?>" data-filename="<?php echo esc_attr( basename( $backup['filename'] ) ); ?>" data-log="<?php echo esc_attr( $archive_payload ); ?>" title="<?php esc_attr_e( 'View remote storage log', AI1WM_PLUGIN_NAME ); ?>">
								<i class="ai1wm-icon-notification"></i>
								<span><?php _e( 'View log', AI1WM_PLUGIN_NAME ); ?></span>
							</a>
							<a href="#" data-archive="<?php echo esc_attr( $backup['filename'] ); ?>" class="ai1wm-button-red ai1wm-backup-delete" title="<?php esc_attr_e( 'Delete', AI1WM_PLUGIN_NAME ); ?>">
								<i class="ai1wm-icon-close"></i>
								<span><?php _e( 'Delete', AI1WM_PLUGIN_NAME ); ?></span>
							</a>
							<div class="ai1wm-backup-status ai1wm-hide" data-archive="<?php echo esc_attr( $backup['filename'] ); ?>" data-filename="<?php echo esc_attr( basename( $backup['filename'] ) ); ?>" data-state="<?php echo esc_attr( $archive_state ); ?>" data-remote="<?php echo esc_attr( $archive_remote ); ?>" data-updated="<?php echo esc_attr( $archive_updated ); ?>" data-log="<?php echo esc_attr( $archive_payload ); ?>" data-message="<?php echo esc_attr( $archive_message ); ?>" aria-live="polite"></div>
						</td>
					</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						<?php endif; ?>
						<div class="ai1wm-backups-create">
							<p class="ai1wm-backups-empty <?php echo $backups ? 'ai1wm-hide' : null; ?>">
								<?php _e( 'There are no backups available at this time, why not create a new one?', AI1WM_PLUGIN_NAME ); ?>
							</p>
							<p>
								<a href="<?php echo esc_url( network_admin_url( 'admin.php?page=ai1wm_export' ) ); ?>" class="ai1wm-button-green">
									<i class="ai1wm-icon-export"></i>
									<?php _e( 'Create backup', AI1WM_PLUGIN_NAME ); ?>
								</a>
							</p>
						</div>
					<?php else : ?>
						<div class="ai1wm-clear ai1wm-message ai1wm-red-message">
							<?php
							printf(
								__(
									'<h3>Site could not create backups!</h3>' .
									'<p>Please make sure that storage directory <strong>%s</strong> has read and write permissions.</p>',
									AI1WM_PLUGIN_NAME
								),
								AI1WM_STORAGE_PATH
							);
							?>
						</div>
					<?php endif; ?>

					<?php do_action( 'ai1wm_backups_left_end' ); ?>

					<input type="hidden" name="ai1wm_manual_restore" value="1" />

				</form>

				<?php
				$ai1wm_s3_activity_rows = array();
				if ( ! empty( $s3_statuses ) ) {
					foreach ( $s3_statuses as $activity_archive => $activity_status ) {
						if ( empty( $activity_status['state'] ) && empty( $activity_status['message'] ) ) {
							continue;
						}
						$ai1wm_s3_activity_rows[ $activity_archive ] = array_merge(
							array(
								'archive'    => $activity_archive,
								'filename'   => basename( $activity_archive ),
								'updated_at' => isset( $activity_status['updated_at'] ) ? (int) $activity_status['updated_at'] : 0,
							),
							$activity_status
						);
					}
				}
				?>

				<div class="ai1wm-backups-s3-activity" id="ai1wm-backups-s3-activity">
					<h3><?php _e( 'Remote Storage Activity', AI1WM_PLUGIN_NAME ); ?></h3>
					<?php if ( ! empty( $ai1wm_s3_activity_rows ) ) : ?>
						<table class="ai1wm-backups ai1wm-backups-logs">
							<thead>
								<tr>
									<th><?php _e( 'Backup', AI1WM_PLUGIN_NAME ); ?></th>
									<th><?php _e( 'Destination', AI1WM_PLUGIN_NAME ); ?></th>
									<th><?php _e( 'Status', AI1WM_PLUGIN_NAME ); ?></th>
									<th><?php _e( 'Updated', AI1WM_PLUGIN_NAME ); ?></th>
									<th><?php _e( 'Logs', AI1WM_PLUGIN_NAME ); ?></th>
								</tr>
							</thead>
							<tbody>
							<?php foreach ( $ai1wm_s3_activity_rows as $activity_archive => $activity_status ) :
								$activity_payload = wp_json_encode( array(
									'archive'    => $activity_archive,
									'filename'   => basename( $activity_archive ),
									'state'      => isset( $activity_status['state'] ) ? $activity_status['state'] : '',
									'message'    => isset( $activity_status['message'] ) ? $activity_status['message'] : '',
									'remote_key' => isset( $activity_status['remote_key'] ) ? $activity_status['remote_key'] : '',
									'updated_at' => $activity_status['updated_at'],
								) );
								?>
								<tr data-archive="<?php echo esc_attr( $activity_archive ); ?>" data-log="<?php echo esc_attr( $activity_payload ); ?>">
									<td class="ai1wm-log-name"><?php echo esc_html( basename( $activity_archive ) ); ?></td>
									<td class="ai1wm-log-destination"><?php echo esc_html( isset( $activity_status['remote_key'] ) ? $activity_status['remote_key'] : '' ); ?></td>
									<td class="ai1wm-log-state"><?php echo esc_html( ucfirst( isset( $activity_status['state'] ) ? $activity_status['state'] : '' ) ); ?></td>
									<td class="ai1wm-log-updated"><?php echo $activity_status['updated_at'] ? esc_html( sprintf( __( '%s ago', AI1WM_PLUGIN_NAME ), human_time_diff( $activity_status['updated_at'] ) ) ) : '—'; ?></td>
									<td class="ai1wm-log-actions">
								<a href="#" class="ai1wm-button-gray ai1wm-button-icon ai1wm-backup-log-button" data-toggle="modal" data-target="#ai1wmS3LogModal" data-type="log" data-archive="<?php echo esc_attr( $activity_archive ); ?>" data-filename="<?php echo esc_attr( basename( $activity_archive ) ); ?>" data-log="<?php echo esc_attr( $activity_payload ); ?>" title="<?php esc_attr_e( 'View remote storage log', AI1WM_PLUGIN_NAME ); ?>">
											<i class="ai1wm-icon-notification"></i>
											<span><?php _e( 'View log', AI1WM_PLUGIN_NAME ); ?></span>
										</a>
									</td>
								</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
					<?php else : ?>
						<p class="ai1wm-backups-logs-empty"><?php _e( 'No remote storage activity yet.', AI1WM_PLUGIN_NAME ); ?></p>
					<?php endif; ?>
				</div>

				<!-- Bootstrap modal for Remote Storage Log -->
				<div class="modal fade" id="ai1wmS3LogModal" tabindex="-1" role="dialog" aria-labelledby="ai1wmS3LogModalTitle" aria-hidden="true">
					<div class="modal-dialog modal-dialog-centered modal-lg" role="document">
						<div class="modal-content">
							<div class="modal-header">
								<h5 class="modal-title" id="ai1wmS3LogModalTitle"><?php _e( 'Remote storage log', AI1WM_PLUGIN_NAME ); ?></h5>
								<button type="button" class="close" data-dismiss="modal" aria-label="<?php esc_attr_e( 'Close', AI1WM_PLUGIN_NAME ); ?>">
									<span aria-hidden="true">&times;</span>
								</button>
							</div>
							<div class="modal-body">
								<pre class="ai1wm-backup-log-content" style="white-space:pre-wrap"></pre>
							</div>
							<div class="modal-footer">
								<button type="button" class="btn btn-secondary" data-dismiss="modal"><?php _e( 'Close', AI1WM_PLUGIN_NAME ); ?></button>
							</div>
						</div>
					</div>
				</div>
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
