<?php

defined( 'ABSPATH' ) || exit;

class Airomi_Admin_Tab_Settings {

	public static function handle_save() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage these settings.', 'airomi-api-connect' ) );
		}
		if ( ! isset( $_POST[ Airomi_Admin_Settings::NONCE_FIELD ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ Airomi_Admin_Settings::NONCE_FIELD ] ) ), Airomi_Admin_Settings::SAVE_ACTION ) ) {
			wp_die( esc_html__( 'Security check failed.', 'airomi-api-connect' ) );
		}

		$raw = array(
			'webhook_url'    => isset( $_POST['airomi_webhook_url'] ) ? wp_unslash( $_POST['airomi_webhook_url'] ) : '',
			'http_method'    => isset( $_POST['airomi_http_method'] ) ? wp_unslash( $_POST['airomi_http_method'] ) : 'POST',
			'timeout'        => isset( $_POST['airomi_timeout'] ) ? $_POST['airomi_timeout'] : 5,
			'sync_enabled'   => isset( $_POST['airomi_sync_enabled'] ) && $_POST['airomi_sync_enabled'] === '1',
			'cron_interval'  => isset( $_POST['airomi_cron_interval'] ) ? wp_unslash( $_POST['airomi_cron_interval'] ) : 'every_5_minutes',
			'custom_headers' => array(),
		);
		if ( ! empty( $_POST['airomi_header_key'] ) && is_array( $_POST['airomi_header_key'] ) && ! empty( $_POST['airomi_header_value'] ) && is_array( $_POST['airomi_header_value'] ) ) {
			$keys   = array_map( 'wp_unslash', (array) $_POST['airomi_header_key'] );
			$values = array_map( 'wp_unslash', (array) $_POST['airomi_header_value'] );
			foreach ( $keys as $i => $key ) {
				$raw['custom_headers'][] = array(
					'key'   => $key,
					'value' => isset( $values[ $i ] ) ? $values[ $i ] : '',
				);
			}
		}

		Airomi_Settings::save( $raw );

		if ( class_exists( 'Airomi_Cron' ) && method_exists( 'Airomi_Cron', 'reschedule' ) ) {
			Airomi_Cron::reschedule();
		}

		$redirect = add_query_arg(
			array(
				'page'  => Airomi_Admin_Settings::PAGE_SLUG,
				'tab'   => 'settings',
				'saved' => '1',
			),
			admin_url( 'admin.php' )
		);
		wp_safe_redirect( $redirect );
		exit;
	}

	public static function render() {
		$webhook_url    = Airomi_Settings::get_webhook_url();
		$http_method    = Airomi_Settings::get_http_method();
		$timeout        = Airomi_Settings::get_timeout();
		$sync_enabled   = Airomi_Settings::is_sync_enabled();
		$cron_interval  = Airomi_Settings::get_cron_interval();
		$custom_headers = Airomi_Settings::get_custom_headers();
		if ( empty( $custom_headers ) ) {
			$custom_headers = array( array( 'key' => '', 'value' => '' ) );
		}

		if ( isset( $_GET['saved'] ) && $_GET['saved'] === '1' ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'airomi-api-connect' ) . '</p></div>';
		}
		?>
		<div class="airomi-settings-tab">
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="airomi-settings-form">
				<input type="hidden" name="action" value="<?php echo esc_attr( Airomi_Admin_Settings::SAVE_ACTION ); ?>" />
				<?php wp_nonce_field( Airomi_Admin_Settings::SAVE_ACTION, Airomi_Admin_Settings::NONCE_FIELD ); ?>

				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><label for="airomi_webhook_url"><?php esc_html_e( 'Webhook URL', 'airomi-api-connect' ); ?></label></th>
							<td>
								<input type="url" name="airomi_webhook_url" id="airomi_webhook_url" value="<?php echo esc_attr( $webhook_url ); ?>" class="regular-text" placeholder="https://" />
								<p class="description"><?php esc_html_e( 'URL to send order data to.', 'airomi-api-connect' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="airomi_http_method"><?php esc_html_e( 'HTTP Method', 'airomi-api-connect' ); ?></label></th>
							<td>
								<select name="airomi_http_method" id="airomi_http_method">
									<option value="GET" <?php selected( $http_method, 'GET' ); ?>><?php esc_html_e( 'GET', 'airomi-api-connect' ); ?></option>
									<option value="POST" <?php selected( $http_method, 'POST' ); ?>><?php esc_html_e( 'POST', 'airomi-api-connect' ); ?></option>
									<option value="PUT" <?php selected( $http_method, 'PUT' ); ?>><?php esc_html_e( 'PUT', 'airomi-api-connect' ); ?></option>
									<option value="PATCH" <?php selected( $http_method, 'PATCH' ); ?>><?php esc_html_e( 'PATCH', 'airomi-api-connect' ); ?></option>
								</select>
								<p class="description"><?php esc_html_e( 'Method used when sending requests.', 'airomi-api-connect' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="airomi_timeout"><?php esc_html_e( 'Request Timeout (seconds)', 'airomi-api-connect' ); ?></label></th>
							<td>
								<input type="number" name="airomi_timeout" id="airomi_timeout" value="<?php echo esc_attr( (string) $timeout ); ?>" min="1" max="300" step="1" />
								<p class="description"><?php esc_html_e( 'Seconds to wait for the external API response.', 'airomi-api-connect' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Enable Sync', 'airomi-api-connect' ); ?></th>
							<td>
								<label for="airomi_sync_enabled">
									<input type="checkbox" name="airomi_sync_enabled" id="airomi_sync_enabled" value="1" <?php checked( $sync_enabled ); ?> />
									<?php esc_html_e( 'Enable order syncing to the webhook', 'airomi-api-connect' ); ?>
								</label>
								<p class="description"><?php esc_html_e( 'When disabled, no requests are sent. Cron retries are also disabled.', 'airomi-api-connect' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="airomi_cron_interval"><?php esc_html_e( 'Retry Failed Orders (Cron Interval)', 'airomi-api-connect' ); ?></label></th>
							<td>
								<select name="airomi_cron_interval" id="airomi_cron_interval">
									<option value="every_1_minute" <?php selected( $cron_interval, 'every_1_minute' ); ?>><?php esc_html_e( 'Every 1 minute', 'airomi-api-connect' ); ?></option>
									<option value="every_5_minutes" <?php selected( $cron_interval, 'every_5_minutes' ); ?>><?php esc_html_e( 'Every 5 minutes', 'airomi-api-connect' ); ?></option>
									<option value="every_15_minutes" <?php selected( $cron_interval, 'every_15_minutes' ); ?>><?php esc_html_e( 'Every 15 minutes', 'airomi-api-connect' ); ?></option>
									<option value="every_30_minutes" <?php selected( $cron_interval, 'every_30_minutes' ); ?>><?php esc_html_e( 'Every 30 minutes', 'airomi-api-connect' ); ?></option>
									<option value="every_hour" <?php selected( $cron_interval, 'every_hour' ); ?>><?php esc_html_e( 'Every hour', 'airomi-api-connect' ); ?></option>
								</select>
								<p class="description"><?php esc_html_e( 'How often to retry orders that failed to sync.', 'airomi-api-connect' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Custom Headers', 'airomi-api-connect' ); ?></th>
							<td>
								<div id="airomi-headers-wrap">
									<?php foreach ( $custom_headers as $idx => $header ) : ?>
									<p class="airomi-header-row">
										<input type="text" name="airomi_header_key[]" value="<?php echo esc_attr( $header['key'] ); ?>" placeholder="<?php esc_attr_e( 'Header name', 'airomi-api-connect' ); ?>" class="regular-text" />
										<input type="text" name="airomi_header_value[]" value="<?php echo esc_attr( $header['value'] ); ?>" placeholder="<?php esc_attr_e( 'Value', 'airomi-api-connect' ); ?>" class="regular-text" />
										<button type="button" class="button airomi-remove-header"><?php esc_html_e( 'Remove', 'airomi-api-connect' ); ?></button>
									</p>
									<?php endforeach; ?>
								</div>
								<button type="button" class="button" id="airomi-add-header"><?php esc_html_e( 'Add header', 'airomi-api-connect' ); ?></button>
								<p class="description"><?php esc_html_e( 'e.g. Authorization: Bearer xxx, X-API-Key: your-key', 'airomi-api-connect' ); ?></p>
							</td>
						</tr>
					</tbody>
				</table>

				<p class="submit">
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Save settings', 'airomi-api-connect' ); ?></button>
				</p>
			</form>
		</div>
		<script type="text/template" id="airomi-header-row-tpl">
			<p class="airomi-header-row">
				<input type="text" name="airomi_header_key[]" value="" placeholder="<?php esc_attr_e( 'Header name', 'airomi-api-connect' ); ?>" class="regular-text" />
				<input type="text" name="airomi_header_value[]" value="" placeholder="<?php esc_attr_e( 'Value', 'airomi-api-connect' ); ?>" class="regular-text" />
				<button type="button" class="button airomi-remove-header"><?php esc_html_e( 'Remove', 'airomi-api-connect' ); ?></button>
			</p>
		</script>
		<script>
		(function(){
			var wrap = document.getElementById('airomi-headers-wrap');
			var tpl = document.getElementById('airomi-header-row-tpl');
			if (wrap && tpl) {
				document.getElementById('airomi-add-header').addEventListener('click', function() {
					var p = document.createElement('p');
					p.className = 'airomi-header-row';
					p.innerHTML = tpl.innerHTML;
					wrap.appendChild(p);
					p.querySelector('.airomi-remove-header').addEventListener('click', function() { p.remove(); });
				});
				wrap.querySelectorAll('.airomi-remove-header').forEach(function(btn) {
					btn.addEventListener('click', function() { btn.closest('.airomi-header-row').remove(); });
				});
			}
		})();
		</script>
		<?php
	}
}
