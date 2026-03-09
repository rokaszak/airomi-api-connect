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

	private static function is_hpos_enabled() {
		if ( ! class_exists( 'Automattic\WooCommerce\Utilities\OrderUtil' ) || ! method_exists( 'Automattic\WooCommerce\Utilities\OrderUtil', 'custom_orders_table_usage_is_enabled' ) ) {
			return false;
		}
		return \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
	}

	public static function get_missing_orders_count() {
		$wpdb  = $GLOBALS['wpdb'];
		$table = airomi_table( AIROMI_TABLE_ORDER_SYNC );
		if ( self::is_hpos_enabled() ) {
			$orders_table = $wpdb->prefix . 'wc_orders';
			$count = (int) $wpdb->get_var(
				"SELECT COUNT(*) FROM `" . esc_sql( $orders_table ) . "` o " .
				"LEFT JOIN `" . esc_sql( $table ) . "` s ON o.id = s.order_id " .
				"WHERE o.type = 'shop_order' AND s.order_id IS NULL"
			);
		} else {
			$count = (int) $wpdb->get_var(
				"SELECT COUNT(*) FROM `" . $wpdb->posts . "` p " .
				"LEFT JOIN `" . esc_sql( $table ) . "` s ON p.ID = s.order_id " .
				"WHERE p.post_type = 'shop_order' AND s.order_id IS NULL"
			);
		}
		return $count;
	}

	public static function get_missing_order_ids( $limit ) {
		$limit = max( 1, min( 500, (int) $limit ) );
		$wpdb  = $GLOBALS['wpdb'];
		$table = airomi_table( AIROMI_TABLE_ORDER_SYNC );
		if ( self::is_hpos_enabled() ) {
			$orders_table = $wpdb->prefix . 'wc_orders';
			$ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT o.id FROM `" . esc_sql( $orders_table ) . "` o " .
					"LEFT JOIN `" . esc_sql( $table ) . "` s ON o.id = s.order_id " .
					"WHERE o.type = 'shop_order' AND s.order_id IS NULL ORDER BY o.id ASC LIMIT %d",
					$limit
				)
			);
		} else {
			$ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT p.ID FROM `" . $wpdb->posts . "` p " .
					"LEFT JOIN `" . esc_sql( $table ) . "` s ON p.ID = s.order_id " .
					"WHERE p.post_type = 'shop_order' AND s.order_id IS NULL ORDER BY p.ID ASC LIMIT %d",
					$limit
				)
			);
		}
		return is_array( $ids ) ? array_map( 'intval', $ids ) : array();
	}

	public static function bulk_insert_rows( array $order_ids ) {
		$order_ids = array_unique( array_map( 'intval', array_filter( $order_ids ) ) );
		if ( empty( $order_ids ) ) {
			return 0;
		}
		$table = airomi_table( AIROMI_TABLE_ORDER_SYNC );
		$values = array();
		foreach ( $order_ids as $id ) {
			$values[] = $GLOBALS['wpdb']->prepare( '(%d, %s)', $id, AIROMI_STATUS_INIT );
		}
		$sql = "INSERT IGNORE INTO `" . esc_sql( $table ) . "` (order_id, sync_status) VALUES " . implode( ', ', $values );
		$GLOBALS['wpdb']->query( $sql );
		return (int) $GLOBALS['wpdb']->rows_affected;
	}
}
