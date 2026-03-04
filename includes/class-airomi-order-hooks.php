<?php

defined( 'ABSPATH' ) || exit;

class Airomi_Order_Hooks {

	private static $synced_this_request = array();

	public static function init() {
		add_action( 'woocommerce_new_order', array( __CLASS__, 'on_new_order' ), 10, 2 );
		add_action( 'woocommerce_update_order', array( __CLASS__, 'on_update_order' ), 10, 2 );
		add_action( 'woocommerce_before_delete_order', array( __CLASS__, 'on_delete_order' ), 10, 2 );
		add_action( 'woocommerce_trash_order', array( __CLASS__, 'on_trash_order' ), 10, 2 );
	}

	public static function on_new_order( $order_id, $order = null ) {
		if ( ! Airomi_Settings::is_sync_enabled() ) {
			return;
		}
		$order_id = (int) $order_id;
		self::ensure_row_exists( $order_id );
		if ( isset( self::$synced_this_request[ $order_id ] ) ) {
			return;
		}
		self::$synced_this_request[ $order_id ] = true;
		Airomi_Sync::sync_order( $order_id );
	}

	public static function on_update_order( $order_id, $order = null ) {
		if ( ! Airomi_Settings::is_sync_enabled() ) {
			return;
		}
		$order_id = (int) $order_id;
		self::ensure_row_exists( $order_id );
		if ( isset( self::$synced_this_request[ $order_id ] ) ) {
			return;
		}
		self::$synced_this_request[ $order_id ] = true;
		Airomi_Sync::sync_order( $order_id );
	}

	public static function on_delete_order( $order_id, $order = null ) {
		self::remove_row( (int) $order_id );
	}

	public static function on_trash_order( $order_id, $order = null ) {
		self::remove_row( (int) $order_id );
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
