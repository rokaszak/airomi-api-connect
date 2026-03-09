<?php

defined( 'ABSPATH' ) || exit;

class Airomi_Admin_Tab_Missing_Orders {

	public static function render( $current_tab = '' ) {
		if ( $current_tab !== 'missing-orders' ) {
			return;
		}
		$missing_count = Airomi_Order_Hooks::get_missing_orders_count();
		?>
		<div class="airomi-missing-orders-tab">
			<?php if ( $missing_count > 0 ) : ?>
				<div class="airomi-init-orders-wrap" style="margin-bottom: 1em;">
					<p><?php echo esc_html( sprintf( _n( '%1$s WooCommerce order is not in the sync table.', '%1$s WooCommerce orders are not in the sync table.', $missing_count, 'airomi-api-connect' ), number_format_i18n( $missing_count ) ) ); ?> <?php esc_html_e( 'Initialize to add them as rows (status: init). You can then sync them from the Orders tab.', 'airomi-api-connect' ); ?></p>
					<button type="button" class="button button-primary" id="airomi-init-orders-btn"><?php esc_html_e( 'Initialize missing orders', 'airomi-api-connect' ); ?></button>
					<div id="airomi-init-progress" style="display: none; margin-top: 0.5em; max-width: 400px;">
						<div class="airomi-progress-bar" style="height: 24px; border: 1px solid #ccc; background: #f0f0f0;">
							<div class="airomi-progress-fill" style="height: 100%; width: 0%; background: #2271b1;"></div>
						</div>
						<p class="airomi-progress-text" style="margin-top: 0.25em;"></p>
					</div>
				</div>
			<?php else : ?>
				<p><?php esc_html_e( 'All WooCommerce orders are in the sync table. There are no missing orders to initialize.', 'airomi-api-connect' ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}
}
