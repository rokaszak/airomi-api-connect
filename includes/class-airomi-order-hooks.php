<?php

defined( 'ABSPATH' ) || exit;

class Airomi_Order_Hooks {

	private static $synced_this_request = array();

	public static function init() {
		add_action( 'woocommerce_new_order', array( __CLASS__, 'handle_order_event' ), 100, 2 );
		add_action( 'woocommerce_update_order', array( __CLASS__, 'handle_order_event' ), 100, 2 );
		add_action( 'woocommerce_delete_order', array( __CLASS__, 'handle_order_event' ), 100, 1 );
		add_action( 'woocommerce_trash_order', array( __CLASS__, 'handle_order_event' ), 100, 1 );
		add_action( 'woocommerce_untrash_order', array( __CLASS__, 'handle_order_event' ), 100, 2 );
	}

	public static function handle_order_event( $order_id, $order = null ) {
		if ( $order instanceof WC_Order ) {
			$order_id = (int) $order->get_id();
		} else {
			$order_id = (int) $order_id;
		}
		self::ensure_row_exists( $order_id );
		if ( isset( self::$synced_this_request[ $order_id ] ) ) {
			return;
		}
		self::$synced_this_request[ $order_id ] = true;
		if ( Airomi_Settings::is_sync_enabled() ) {
			Airomi_Sync::sync_order( $order_id, $order );
		} else {
			Airomi_Sync::mark_failed_sync_disabled( $order_id );
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
