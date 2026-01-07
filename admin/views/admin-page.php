<div class="wrap wpifc-admin">
	<h1 class="wpifc-title"><?php _e( 'WP Image Format Converter', 'wp-image-converter' ); ?></h1>

	<div class="wpifc-stats-banner">
		<div class="wpifc-stat-card">
			<span class="wpifc-stat-val"><?php echo esc_html( $total_images ); ?></span>
			<span class="wpifc-stat-label"><?php _e( 'Library Images', 'wp-image-converter' ); ?></span>
		</div>
		<div class="wpifc-stat-card">
			<span class="wpifc-stat-val"><?php echo esc_html( $converted_images ); ?></span>
			<span class="wpifc-stat-label"><?php _e( 'Already Optimized', 'wp-image-converter' ); ?></span>
		</div>
		<div class="wpifc-stat-card wpifc-stat-percent">
			<span class="wpifc-stat-val"><?php echo $total_images > 0 ? round( ( $converted_images / $total_images ) * 100 ) : 0; ?>%</span>
			<span class="wpifc-stat-label"><?php _e( 'Coverage', 'wp-image-converter' ); ?></span>
		</div>
	</div>

	<div class="wpifc-container">
		<!-- Diagnostic Section -->
		<div class="wpifc-card wpifc-diagnostics">
			<h2><span class="dashicons dashicons-shield"></span> <?php _e( 'Server Health & Capabilities', 'wp-image-converter' ); ?></h2>
			<div class="wpifc-grid">
				<?php foreach ( $report as $key => $item ) : ?>
					<div class="wpifc-stat-item wpifc-status-<?php echo esc_attr( $item['status'] ); ?>">
						<div class="wpifc-stat-label"><?php echo esc_html( $item['label'] ); ?></div>
						<div class="wpifc-stat-status">
							<?php if ( 'success' === $item['status'] ) : ?>
								<span class="dashicons dashicons-yes-alt"></span>
							<?php elseif ( 'warning' === $item['status'] ) : ?>
								<span class="dashicons dashicons-warning"></span>
							<?php else : ?>
								<span class="dashicons dashicons-dismiss"></span>
							<?php endif; ?>
							<?php echo esc_html( $item['message'] ); ?>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>

		<!-- Action Section (Placeholder for Phase 2/3) -->
		<div class="wpifc-card wpifc-bulk-actions">
			<h2><span class="dashicons dashicons-images-alt2"></span> <?php _e( 'Bulk Conversion', 'wp-image-converter' ); ?></h2>
			<p><?php _e( 'Ready to optimize your media library? Start the conversion process below.', 'wp-image-converter' ); ?></p>
			
			<div class="wpifc-action-buttons">
				<button id="start-conversion" class="button button-primary button-hero">
					<?php _e( 'Start Mass Conversion', 'wp-image-converter' ); ?>
				</button>
			</div>
			
			<div id="conversion-progress" class="wpifc-progress-container" style="display:none;">
				<div class="wpifc-progress-bar">
					<div class="wpifc-progress-fill" style="width: 0%"></div>
				</div>
				<p class="wpifc-progress-text">0% - <?php _e( 'Processing...', 'wp-image-converter' ); ?></p>
			</div>
		</div>

		<!-- Settings Section -->
		<form method="post" action="options.php" class="wpifc-card wpifc-settings-card">
			<?php settings_fields( 'wpifc_settings_group' ); ?>
			<h2><span class="dashicons dashicons-admin-settings"></span> <?php _e( 'General Settings', 'wp-image-converter' ); ?></h2>
			
			<div class="wpifc-field">
				<label>
					<input type="checkbox" name="wpifc_settings[auto_convert]" value="1" <?php checked( $settings['auto_convert'], 1 ); ?>>
					<strong><?php _e( 'Auto-convert on upload', 'wp-image-converter' ); ?></strong>
					<p class="description"><?php _e( 'Automatically generate WebP/AVIF versions when you upload new images to the media library.', 'wp-image-converter' ); ?></p>
				</label>
			</div>

			<div class="wpifc-field">
				<label><strong><?php _e( 'Target Formats', 'wp-image-converter' ); ?></strong></label>
				<div class="wpifc-checkbox-group">
					<?php 
					$auto_formats = isset($settings['auto_formats']) && is_array($settings['auto_formats']) ? $settings['auto_formats'] : []; 
					$avif_enabled = isset($report['avif']['status']) && $report['avif']['status'] === 'success';
					?>
					<label><input type="checkbox" name="wpifc_settings[auto_formats][]" value="webp" <?php echo in_array( 'webp', $auto_formats ) ? 'checked' : ''; ?>> WebP</label>
					<label><input type="checkbox" name="wpifc_settings[auto_formats][]" value="avif" <?php echo in_array( 'avif', $auto_formats ) ? 'checked' : ''; ?> <?php echo ! $avif_enabled ? 'disabled' : ''; ?>> AVIF</label>
				</div>
				<p class="description"><?php _e( 'Select which formats you want to generate. WebP is widely supported, AVIF offers better compression but requires newer server libraries.', 'wp-image-converter' ); ?></p>
			</div>

			<div class="wpifc-field">
				<label><strong><?php _e( 'Compression Quality', 'wp-image-converter' ); ?></strong> (<span id="quality-val"><?php echo esc_html( $settings['auto_quality'] ); ?></span>%)</label>
				<input type="range" name="wpifc_settings[auto_quality]" min="1" max="100" value="<?php echo esc_attr( $settings['auto_quality'] ); ?>" class="wpifc-slider" oninput="document.getElementById('quality-val').innerText = this.value">
			</div>

			<div class="wpifc-field">
				<label>
					<input type="checkbox" name="wpifc_settings[keep_original]" value="1" <?php checked( $settings['keep_original'], 1 ); ?>>
					<strong><?php _e( 'Keep original images', 'wp-image-converter' ); ?></strong>
				</label>
			</div>

			<?php submit_button( __( 'Save Premium Settings', 'wp-image-converter' ), 'primary' ); ?>
		</form>
	</div>
</div>
