<?php

defined( 'ABSPATH' ) || exit;

class Airomi_Order_Hooks {

	private static $pending_sync = array();
	private static $shutdown_registered = false;

	public static function init() {
		add_action( 'woocommerce_new_order', array( __CLASS__, 'handle_order_event' ), 100, 1 );
		add_action( 'woocommerce_update_order', array( __CLASS__, 'handle_order_event' ), 100, 1 );
		add_action( 'woocommerce_delete_order', array( __CLASS__, 'handle_order_event' ), 100, 1 );
		add_action( 'woocommerce_trash_order', array( __CLASS__, 'handle_order_event' ), 100, 1 );
		add_action( 'woocommerce_untrash_order', array( __CLASS__, 'handle_order_event' ), 100, 1 );
		add_filter('woocommerce_rest_prepare_shop_order_object', function($response, $order) {
			$line_items = $response->data['line_items'];
		
			foreach ($order->get_items() as $item_id => $item) {
				foreach ($line_items as &$line_item) {
					if ($line_item['id'] !== $item_id) continue;
		
					$product_id = $item->get_product_id();
					if ($product_id) {
						$terms = get_the_terms($product_id, 'product_cat');
						$line_item['categories'] = (!empty($terms) && !is_wp_error($terms))
							? array_values(array_map(fn($t) => ['name' => $t->name, 'slug' => $t->slug], $terms))
							: [];
					} else {
						$line_item['categories'] = [];
					}
				}
			}
		
			$response->data['line_items'] = $line_items;
			return $response;
		}, 10, 2);
	}

	public static function handle_order_event( $order_id ) {
		$order_id = (int) $order_id;
		self::ensure_row_exists( $order_id );
		self::$pending_sync[ $order_id ] = true;
		if ( ! self::$shutdown_registered ) {
			self::$shutdown_registered = true;
			add_action( 'shutdown', array( __CLASS__, 'process_pending_syncs' ), 10, 0 );
		}
	}

	public static function process_pending_syncs() {
		foreach ( array_keys( self::$pending_sync ) as $order_id ) {
			if ( Airomi_Settings::is_sync_enabled() ) {
				Airomi_Sync::sync_order( (int) $order_id );
			} else {
				Airomi_Sync::mark_failed_sync_disabled( (int) $order_id );
			}
		}
	}

	public static function ensure_row_exists( $order_id ) {
		$table = airomi_table( AIROMI_TABLE_ORDER_SYNC );
		$exists = $GLOBALS['wpdb']->get_var( $GLOBALS['wpdb']->prepare( "SELECT order_id FROM `" . esc_sql( $table ) . "` WHERE order_id = %d", $order_id ) );
		if ( $exists !== null ) {
			return false;
		}
		$GLOBALS['wpdb']->insert(
			$table,
			array(
				'order_id'    => $order_id,
				'sync_status' => AIROMI_STATUS_INIT,
			),
			array( '%d', '%s' )
		);
		return true;
	}

	public static function remove_row( $order_id ) {
		$GLOBALS['wpdb']->delete( airomi_table( AIROMI_TABLE_ORDER_SYNC ), array( 'order_id' => $order_id ), array( '%d' ) );
	}
}
