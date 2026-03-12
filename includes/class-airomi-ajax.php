<?php

defined( 'ABSPATH' ) || exit;

class Airomi_Ajax {

	const BATCH_SIZE = 50;
	const ACTION_INIT_ORDERS       = 'airomi_init_orders';
	const ACTION_GET_DETAIL       = 'airomi_get_order_detail';
	const ACTION_SYNC_ORDER       = 'airomi_sync_order';
	const ACTION_SYNC_BULK        = 'airomi_sync_bulk';
	const ACTION_SYNC_INIT_BATCH  = 'airomi_sync_init_batch';

	public static function init() {
		add_action( 'wp_ajax_' . self::ACTION_INIT_ORDERS, array( __CLASS__, 'ajax_init_orders' ) );
		add_action( 'wp_ajax_' . self::ACTION_GET_DETAIL, array( __CLASS__, 'ajax_get_order_detail' ) );
		add_action( 'wp_ajax_' . self::ACTION_SYNC_ORDER, array( __CLASS__, 'ajax_sync_order' ) );
		add_action( 'wp_ajax_' . self::ACTION_SYNC_BULK, array( __CLASS__, 'ajax_sync_bulk' ) );
		add_action( 'wp_ajax_' . self::ACTION_SYNC_INIT_BATCH, array( __CLASS__, 'ajax_sync_init_batch' ) );
	}


	public static function ajax_init_orders() {
		check_ajax_referer( 'airomi_admin', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'airomi-api-connect' ) ) );
		}

		$missing_ids = Airomi_Order_Hooks::get_missing_order_ids( self::BATCH_SIZE );
		$inserted    = Airomi_Order_Hooks::bulk_insert_rows( $missing_ids );
		$remaining   = Airomi_Order_Hooks::get_missing_orders_count();
		$done        = $remaining === 0;

		wp_send_json_success( array(
			'inserted'  => $inserted,
			'remaining' => $remaining,
			'done'      => $done,
		) );
	}

	public static function ajax_get_order_detail() {
		check_ajax_referer( 'airomi_admin', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'airomi-api-connect' ) ) );
		}

		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		if ( ! $order_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid order ID.', 'airomi-api-connect' ) ) );
		}

		$table = airomi_table( AIROMI_TABLE_ORDER_SYNC );
		$row   = $GLOBALS['wpdb']->get_row( $GLOBALS['wpdb']->prepare( "SELECT order_id, sync_status, payload, response_code, response_body, last_synced_at, fail_count FROM `" . esc_sql( $table ) . "` WHERE order_id = %d", $order_id ), ARRAY_A );
		if ( ! $row ) {
			wp_send_json_error( array( 'message' => __( 'Order not found in sync table.', 'airomi-api-connect' ) ) );
		}

		wp_send_json_success( array(
			'order_id'       => (int) $row['order_id'],
			'sync_status'    => $row['sync_status'],
			'payload'        => $row['payload'],
			'response_code'  => $row['response_code'],
			'response_body'  => $row['response_body'],
			'last_synced_at' => $row['last_synced_at'],
			'fail_count'     => (int) $row['fail_count'],
		) );
	}

	public static function ajax_sync_order() {
		check_ajax_referer( 'airomi_admin', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'airomi-api-connect' ) ) );
		}

		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		if ( ! $order_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid order ID.', 'airomi-api-connect' ) ) );
		}

		Airomi_Sync::sync_order( $order_id );

		$table = airomi_table( AIROMI_TABLE_ORDER_SYNC );
		$row   = $GLOBALS['wpdb']->get_row( $GLOBALS['wpdb']->prepare( "SELECT order_id, sync_status, response_code, last_synced_at, fail_count FROM `" . esc_sql( $table ) . "` WHERE order_id = %d", $order_id ), ARRAY_A );
		if ( ! $row ) {
			wp_send_json_error( array( 'message' => __( 'Order not found after sync.', 'airomi-api-connect' ) ) );
		}

		wp_send_json_success( array(
			'order_id'       => (int) $row['order_id'],
			'sync_status'    => $row['sync_status'],
			'response_code'  => $row['response_code'],
			'last_synced_at' => $row['last_synced_at'],
			'fail_count'     => (int) $row['fail_count'],
		) );
	}

	public static function ajax_sync_bulk() {
		check_ajax_referer( 'airomi_admin', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'airomi-api-connect' ) ) );
		}

		$raw = isset( $_POST['order_ids'] ) && is_array( $_POST['order_ids'] ) ? $_POST['order_ids'] : array();
		$order_ids = array_filter( array_map( 'absint', $raw ) );
		if ( empty( $order_ids ) ) {
			wp_send_json_error( array( 'message' => __( 'No valid order IDs.', 'airomi-api-connect' ) ) );
		}

		$batch_result = Airomi_Sync::sync_orders_batch( $order_ids );
		$table   = airomi_table( AIROMI_TABLE_ORDER_SYNC );
		$results = array();
		foreach ( $order_ids as $order_id ) {
			$row = $GLOBALS['wpdb']->get_row( $GLOBALS['wpdb']->prepare( "SELECT order_id, sync_status, response_code, last_synced_at, fail_count FROM `" . esc_sql( $table ) . "` WHERE order_id = %d", $order_id ), ARRAY_A );
			if ( $row ) {
				$results[] = array(
					'order_id'       => (int) $row['order_id'],
					'sync_status'    => $row['sync_status'],
					'response_code'  => (int) $row['response_code'],
					'last_synced_at' => $row['last_synced_at'],
					'fail_count'     => (int) $row['fail_count'],
				);
			}
		}

		wp_send_json_success( array(
			'results' => $results,
			'synced'  => $batch_result['synced'],
			'failed'  => $batch_result['failed'],
		) );
	}

	public static function ajax_sync_init_batch() {
		check_ajax_referer( 'airomi_admin', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'airomi-api-connect' ) ) );
		}
		$result = Airomi_Sync::sync_batch( AIROMI_STATUS_INIT, 100 );
		wp_send_json_success( $result );
	}
}
