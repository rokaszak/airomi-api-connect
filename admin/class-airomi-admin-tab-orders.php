<?php

defined( 'ABSPATH' ) || exit;

class Airomi_Admin_Tab_Orders {

	public static function render() {
		$bulk_action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : ( isset( $_GET['action2'] ) ? sanitize_text_field( wp_unslash( $_GET['action2'] ) ) : '' );
		if ( $bulk_action === 'airomi_sync' && ! empty( $_GET['order_ids'] ) && isset( $_GET['_wpnonce'] ) ) {
			if ( current_user_can( 'manage_woocommerce' ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'airomi_bulk_sync' ) ) {
				$ids = array_map( 'absint', (array) $_GET['order_ids'] );
				$ids = array_filter( $ids );
				if ( $ids && class_exists( 'Airomi_Sync' ) ) {
					Airomi_Sync::sync_orders( $ids );
				}
				wp_safe_redirect( remove_query_arg( array( 'action', 'action2', 'order_ids', '_wpnonce' ) ) );
				exit;
			}
		}

		if ( ! class_exists( 'Airomi_Admin_Orders_Table' ) ) {
			require_once airomi_api_connect_path() . 'admin/class-airomi-admin-orders-table.php';
		}
		$table = new Airomi_Admin_Orders_Table();
		$table->prepare_items();

		$table_name = airomi_table( AIROMI_TABLE_ORDER_SYNC );
		$count      = (int) $GLOBALS['wpdb']->get_var( "SELECT COUNT(*) FROM `" . esc_sql( $table_name ) . "`" );
		?>
		<div class="airomi-orders-tab">
			<?php if ( $count === 0 ) : ?>
				<div class="airomi-init-orders-wrap" style="margin-bottom: 1em;">
					<p><?php esc_html_e( 'No orders in sync table yet. Initialize to add all existing WooCommerce orders as rows (status: init). You can then sync them individually or in bulk.', 'airomi-api-connect' ); ?></p>
					<button type="button" class="button button-primary" id="airomi-init-orders-btn"><?php esc_html_e( 'Initialize orders', 'airomi-api-connect' ); ?></button>
					<div id="airomi-init-progress" style="display: none; margin-top: 0.5em; max-width: 400px;">
						<div class="airomi-progress-bar" style="height: 24px; border: 1px solid #ccc; background: #f0f0f0;">
							<div class="airomi-progress-fill" style="height: 100%; width: 0%; background: #2271b1;"></div>
						</div>
						<p class="airomi-progress-text" style="margin-top: 0.25em;"></p>
					</div>
				</div>
			<?php endif; ?>
			<div id="airomi-sync-progress" class="airomi-sync-progress" style="display: none; margin-bottom: 1em; max-width: 400px;">
				<div class="airomi-progress-bar" style="height: 24px; border: 1px solid #ccc; background: #f0f0f0;">
					<div class="airomi-sync-progress-fill" style="height: 100%; width: 0%; background: #2271b1;"></div>
				</div>
				<p class="airomi-sync-progress-text" style="margin-top: 0.25em;"></p>
			</div>
			<form method="get" action="" id="airomi-orders-form">
				<input type="hidden" name="page" value="<?php echo esc_attr( Airomi_Admin_Settings::PAGE_SLUG ); ?>" />
				<input type="hidden" name="tab" value="orders" />
				<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( wp_create_nonce( 'airomi_bulk_sync' ) ); ?>" />
				<?php $table->display(); ?>
			</form>
		</div>
		<?php
	}
}
