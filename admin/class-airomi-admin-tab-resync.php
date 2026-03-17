<?php

defined( 'ABSPATH' ) || exit;

class Airomi_Admin_Tab_Resync {

	public static function render( $current_tab = '' ) {
		if ( $current_tab !== 'resync' ) {
			return;
		}
		?>
		<div class="airomi-resync-tab">
			<p><?php esc_html_e( 'Paste a JSON array of order IDs from your external system (e.g. failed orders report) and click Start Sync to resync them.', 'airomi-api-connect' ); ?></p>
			<textarea id="airomi-resync-json" class="airomi-resync-textarea" rows="12" placeholder='[{"id":7268},{"id":7867},{"id":9890}]'></textarea>
			<p style="margin-top: 0.5em;">
				<button type="button" class="button button-primary" id="airomi-resync-btn"><?php esc_html_e( 'Start Sync', 'airomi-api-connect' ); ?></button>
			</p>
			<div id="airomi-sync-progress" class="airomi-sync-progress" style="display: none; margin-top: 1em; max-width: 400px;">
				<div class="airomi-progress-bar" style="height: 24px; border: 1px solid #ccc; background: #f0f0f0;">
					<div class="airomi-sync-progress-fill" style="height: 100%; width: 0%; background: #2271b1;"></div>
				</div>
				<p class="airomi-sync-progress-text" style="margin-top: 0.25em;"></p>
			</div>
		</div>
		<?php
	}
}
