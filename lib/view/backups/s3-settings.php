<?php
$ai1wm_s3_labels   = Ai1wm_S3_Settings::field_labels();
$ai1wm_s3_configured_attr = $s3_configured ? '1' : '0';
$ai1wm_s3_missing = Ai1wm_S3_Settings::missing_required_fields();
?>
<div class="ai1wm-field-set ai1wm-backups-s3" id="ai1wm-s3-settings" data-configured="<?php echo esc_attr( $ai1wm_s3_configured_attr ); ?>">
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
			<input type="password" id="ai1wm-s3-secret-key" name="ai1wm_s3_secret_key" value="<?php echo esc_attr( $ai1wm_s3_settings['secret_key'] ); ?>" autocomplete="off" required data-s3-required="1" data-label="<?php echo esc_attr( $ai1wm_s3_labels['secret_key'] ); ?>" />
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
		<?php printf(
			__( 'Uploads use %1$s chunks with up to %2$d retries per part.', AI1WM_PLUGIN_NAME ),
			size_format( $ai1wm_s3_chunk_size ),
			(int) $ai1wm_s3_max_retries
		); ?>
	</p>
</div>
