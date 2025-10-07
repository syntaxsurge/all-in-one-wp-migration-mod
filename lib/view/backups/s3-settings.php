<?php
$ai1wm_s3_labels          = Ai1wm_S3_Settings::field_labels();
$ai1wm_s3_configured_attr = $s3_configured ? '1' : '0';
$ai1wm_s3_missing         = Ai1wm_S3_Settings::missing_required_fields();
$ai1wm_s3_chunk_size      = isset( $s3_chunk_size ) ? (int) $s3_chunk_size : AI1WM_S3_MULTIPART_CHUNK_SIZE;
$ai1wm_s3_max_retries     = isset( $s3_max_retries ) ? (int) $s3_max_retries : AI1WM_S3_MAX_RETRIES;
$ai1wm_s3_concurrency     = isset( $s3_concurrency ) ? (int) $s3_concurrency : AI1WM_S3_CONCURRENCY;
?>


<div class="ai1wm-field-set ai1wm-backups-s3" id="ai1wm-s3-settings" data-configured="<?php echo esc_attr( $ai1wm_s3_configured_attr ); ?>">
	<div class="ai1wm-backups-s3__heading ai1wm-s3-config-heading">
		<h2 id="ai1wm-s3-config-heading">
			<i class="ai1wm-icon-export"></i>
			<?php _e( 'Import / Export Configuration', AI1WM_PLUGIN_NAME ); ?>
		</h2>
		<p class="ai1wm-backups-s3__description">
			<?php _e( 'Quickly copy your remote storage settings or apply a configuration shared from another site.', AI1WM_PLUGIN_NAME ); ?>
		</p>
	</div>

	<div class="ai1wm-field ai1wm-s3-config-tools">
		<div class="ai1wm-s3-config-actions">
			<button type="button" class="ai1wm-button-gray ai1wm-s3-config-export" id="ai1wm-s3-config-export">
				<i class="ai1wm-icon-export"></i>
				<?php _e( 'Copy configuration', AI1WM_PLUGIN_NAME ); ?>
			</button>
			<button type="button" class="ai1wm-button-gray ai1wm-s3-config-import" id="ai1wm-s3-config-import-btn">
				<i class="ai1wm-icon-import"></i>
				<?php _e( 'Apply configuration', AI1WM_PLUGIN_NAME ); ?>
			</button>
		</div>
		<textarea id="ai1wm-s3-config-import-input" class="ai1wm-s3-config-input" rows="3" aria-labelledby="ai1wm-s3-config-heading" placeholder="<?php esc_attr_e( 'Paste configuration JSON here and click “Apply configuration”.', AI1WM_PLUGIN_NAME ); ?>"></textarea>
		<p class="ai1wm-s3-config-feedback" aria-live="polite"></p>
	</div>


	<div class="ai1wm-backups-s3__heading">
		<h2>
			<i class="ai1wm-icon-cloud-upload"></i>
			<?php _e( 'Remote Storage (S3 Compatible)', AI1WM_PLUGIN_NAME ); ?>
		</h2>
		<p class="ai1wm-backups-s3__description">
			<?php _e( 'Copy finished backups to AWS S3, Wasabi, or Backblaze B2 without leaving this screen.', AI1WM_PLUGIN_NAME ); ?>
		</p>
	</div>

	<?php if ( isset( $_GET['ai1wm_s3_settings'] ) && $_GET['ai1wm_s3_settings'] === 'saved' ) : ?>
		<div class="ai1wm-message ai1wm-green-message">
			<p><?php _e( 'S3 settings saved.', AI1WM_PLUGIN_NAME ); ?></p>
		</div>
	<?php elseif ( ! $s3_configured ) : ?>
		<div class="ai1wm-message ai1wm-blue-message">
			<p><?php _e( 'Add your storage credentials and press “Save Storage Settings” to enable Copy to S3.', AI1WM_PLUGIN_NAME ); ?></p>
			<?php if ( ! empty( $ai1wm_s3_missing ) ) : ?>
				<p><?php printf( __( 'Missing details: %s.', AI1WM_PLUGIN_NAME ), esc_html( implode( ', ', $ai1wm_s3_missing ) ) ); ?></p>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ai1wm-clear ai1wm-s3-form">
		<?php wp_nonce_field( 'ai1wm_s3_settings' ); ?>
		<input type="hidden" name="action" value="ai1wm_save_s3_settings" />

		<div class="ai1wm-field">
			<label for="ai1wm-s3-endpoint"><?php echo esc_html( $ai1wm_s3_labels['endpoint'] ); ?></label>
			<input type="text" id="ai1wm-s3-endpoint" name="ai1wm_s3_endpoint" value="<?php echo esc_attr( $ai1wm_s3_settings['endpoint'] ); ?>" placeholder="https://s3.us-east-1.amazonaws.com" required data-s3-required="1" data-label="<?php echo esc_attr( $ai1wm_s3_labels['endpoint'] ); ?>" />
			<p class="description"><?php _e( 'Must start with https:// (secure endpoints only).', AI1WM_PLUGIN_NAME ); ?></p>
		</div>

		<div class="ai1wm-field">
			<label for="ai1wm-s3-region"><?php echo esc_html( $ai1wm_s3_labels['region'] ); ?></label>
			<input type="text" id="ai1wm-s3-region" name="ai1wm_s3_region" value="<?php echo esc_attr( $ai1wm_s3_settings['region'] ); ?>" placeholder="us-east-1" required data-s3-required="1" data-label="<?php echo esc_attr( $ai1wm_s3_labels['region'] ); ?>" />
		</div>

		<div class="ai1wm-field">
			<label for="ai1wm-s3-bucket"><?php echo esc_html( $ai1wm_s3_labels['bucket'] ); ?></label>
			<input type="text" id="ai1wm-s3-bucket" name="ai1wm_s3_bucket" value="<?php echo esc_attr( $ai1wm_s3_settings['bucket'] ); ?>" placeholder="my-wordpress-backups" required data-s3-required="1" data-label="<?php echo esc_attr( $ai1wm_s3_labels['bucket'] ); ?>" />
		</div>

		<div class="ai1wm-field">
			<label for="ai1wm-s3-prefix"><?php _e( 'Bucket Prefix (optional)', AI1WM_PLUGIN_NAME ); ?></label>
			<input type="text" id="ai1wm-s3-prefix" name="ai1wm_s3_prefix" value="<?php echo esc_attr( $ai1wm_s3_settings['prefix'] ); ?>" placeholder="backups/site/" data-label="<?php esc_attr_e( 'Bucket Prefix', AI1WM_PLUGIN_NAME ); ?>" />
		</div>

		<div class="ai1wm-field">
			<label for="ai1wm-s3-access-key"><?php echo esc_html( $ai1wm_s3_labels['access_key'] ); ?></label>
			<input type="text" id="ai1wm-s3-access-key" name="ai1wm_s3_access_key" value="<?php echo esc_attr( $ai1wm_s3_settings['access_key'] ); ?>" autocomplete="off" required data-s3-required="1" data-label="<?php echo esc_attr( $ai1wm_s3_labels['access_key'] ); ?>" />
		</div>

		<div class="ai1wm-field">
			<label for="ai1wm-s3-secret-key"><?php echo esc_html( $ai1wm_s3_labels['secret_key'] ); ?></label>
			<div class="ai1wm-secret-input">
				<input type="password" id="ai1wm-s3-secret-key" name="ai1wm_s3_secret_key" value="<?php echo esc_attr( $ai1wm_s3_settings['secret_key'] ); ?>" autocomplete="off" required data-s3-required="1" data-label="<?php echo esc_attr( $ai1wm_s3_labels['secret_key'] ); ?>" />
				<button type="button" class="ai1wm-secret-toggle" aria-label="<?php esc_attr_e( 'Show secret access key', AI1WM_PLUGIN_NAME ); ?>" data-toggle="visibility">
					<span class="dashicons dashicons-visibility"></span>
				</button>
			</div>
		</div>

		<div class="ai1wm-field ai1wm-s3-checkbox">
			<label for="ai1wm-s3-use-path-style">
				<input type="checkbox" id="ai1wm-s3-use-path-style" name="ai1wm_s3_use_path_style" value="1" <?php checked( true, $ai1wm_s3_settings['use_path_style'] ); ?> />
				<?php _e( 'Use path-style endpoint (recommended for Backblaze B2 and Wasabi)', AI1WM_PLUGIN_NAME ); ?>
			</label>
		</div>

		<div class="ai1wm-field ai1wm-field-actions">
			<div class="ai1wm-buttons">
				<button type="submit" class="ai1wm-button-green">
					<i class="ai1wm-icon-save"></i>
					<?php _e( 'Save Storage Settings', AI1WM_PLUGIN_NAME ); ?>
				</button>
			</div>
		</div>
	</form>

	<p class="ai1wm-s3-hint">
		<?php
		printf(
			__( 'Uploads use %1$s chunks with up to %2$d retries per part and %3$d concurrent transfers.', AI1WM_PLUGIN_NAME ),
			size_format( $ai1wm_s3_chunk_size ),
			(int) $ai1wm_s3_max_retries,
			(int) $ai1wm_s3_concurrency
		);
		?>
	</p>

	<div class="ai1wm-field-set ai1wm-s3-browser" id="ai1wm-s3-browser" data-configured="<?php echo esc_attr( $ai1wm_s3_configured_attr ); ?>">
		<div class="ai1wm-backups-s3__heading">
			<h2>
				<i class="ai1wm-icon-cloud"></i>
				<?php _e( 'Browse S3 Backups', AI1WM_PLUGIN_NAME ); ?>
			</h2>
			<p class="ai1wm-backups-s3__description">
				<?php _e( 'Select a backup from your S3 bucket to copy into this site\'s backups folder for import.', AI1WM_PLUGIN_NAME ); ?>
			</p>
		</div>

		<?php if ( ! $s3_configured ) : ?>
			<div class="ai1wm-message ai1wm-blue-message">
				<p><?php _e( 'Save your S3 settings above to enable browsing.', AI1WM_PLUGIN_NAME ); ?></p>
			</div>
		<?php endif; ?>

		<div class="ai1wm-s3-browser-controls" aria-live="polite">
			<strong><?php _e( 'Current path:', AI1WM_PLUGIN_NAME ); ?></strong>
			<code id="ai1wm-s3-current-path">/</code>
			<div class="ai1wm-buttons" style="margin-top:6px;">
				<button type="button" class="ai1wm-button-gray" id="ai1wm-s3-up" aria-label="<?php esc_attr_e( 'Up', AI1WM_PLUGIN_NAME ); ?>">
					<i class="ai1wm-icon-up"></i> <?php _e( 'Up', AI1WM_PLUGIN_NAME ); ?>
				</button>
				<button type="button" class="ai1wm-button-gray" id="ai1wm-s3-refresh" aria-label="<?php esc_attr_e( 'Refresh', AI1WM_PLUGIN_NAME ); ?>">
					<i class="ai1wm-icon-refresh"></i> <?php _e( 'Refresh', AI1WM_PLUGIN_NAME ); ?>
				</button>
				<input type="text" id="ai1wm-s3-prefix-input" placeholder="/<?php esc_attr_e( 'Filter prefix (e.g. backups/siteA)', AI1WM_PLUGIN_NAME ); ?>" style="margin-left:10px;min-width:240px;" />
				<button type="button" class="ai1wm-button-gray" id="ai1wm-s3-go"><?php _e( 'Go', AI1WM_PLUGIN_NAME ); ?></button>
			</div>
		</div>

		<table class="ai1wm-backups ai1wm-s3-list" id="ai1wm-s3-list" aria-describedby="ai1wm-s3-current-path">
			<thead>
				<tr>
					<th><?php _e( 'Name', AI1WM_PLUGIN_NAME ); ?></th>
					<th><?php _e( 'Size', AI1WM_PLUGIN_NAME ); ?></th>
					<th><?php _e( 'Last Modified', AI1WM_PLUGIN_NAME ); ?></th>
					<th><?php _e( 'Action', AI1WM_PLUGIN_NAME ); ?></th>
				</tr>
			</thead>
			<tbody>
				<tr class="ai1wm-s3-empty"><td colspan="4"><?php _e( 'This folder is empty.', AI1WM_PLUGIN_NAME ); ?></td></tr>
			</tbody>
		</table>

		<div class="ai1wm-buttons" id="ai1wm-s3-load-more-wrap" style="display:none;">
			<button type="button" class="ai1wm-button-gray" id="ai1wm-s3-load-more"><?php _e( 'Load more…', AI1WM_PLUGIN_NAME ); ?></button>
		</div>

		<p class="ai1wm-s3-browser-feedback" id="ai1wm-s3-feedback" aria-live="polite"></p>
	</div>
</div>
