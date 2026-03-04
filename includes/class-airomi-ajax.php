<?php

defined( 'ABSPATH' ) || exit;

class Airomi_Ajax {

	const BATCH_SIZE = 50;
	const ACTION_INIT_ORDERS   = 'airomi_init_orders';
	const ACTION_GET_DETAIL   = 'airomi_get_order_detail';
	const ACTION_SYNC_ORDER   = 'airomi_sync_order';

	public static function init() {
		add_action( 'wp_ajax_' . self::ACTION_INIT_ORDERS, array( __CLASS__, 'ajax_init_orders' ) );
		add_action( 'wp_ajax_' . self::ACTION_GET_DETAIL, array( __CLASS__, 'ajax_get_order_detail' ) );
		add_action( 'wp_ajax_' . self::ACTION_SYNC_ORDER, array( __CLASS__, 'ajax_sync_order' ) );
	}


	public static function ajax_init_orders() {
		check_ajax_referer( 'airomi_admin', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'airomi-api-connect' ) ) );
		}

		$page = isset( $_POST['page'] ) ? max( 1, (int) $_POST['page'] ) : 1;
		$per_page = self::BATCH_SIZE;
		$offset = ( $page - 1 ) * $per_page;

		$args = array(
			'limit'  => $per_page,
			'offset' => $offset,
			'return' => 'ids',
			'status' => 'any',
			'type'   => 'shop_order',
		);
		$order_ids     = wc_get_orders( $args );
		$total_orders  = self::get_total_orders_count();
		$processed     = 0;
		foreach ( $order_ids as $order_id ) {
			if ( Airomi_Order_Hooks::ensure_row_exists( $order_id ) ) {
				$processed++;
			}
		}
		if ( $page === 1 ) {
			$total_orders = self::get_total_orders_count();
		}
		$done = count( $order_ids ) === 0 || ( $offset + count( $order_ids ) ) >= $total_orders;
		wp_send_json_success( array(
			'processed' => $processed,
			'total'     => $total_orders,
			'done'      => $done,
			'next_page' => $done ? 0 : $page + 1,
		) );
	}

	private static function get_total_orders_count() {
		$total = 0;
		$per   = 500;
		$page  = 1;
		do {
			$ids = wc_get_orders( array(
				'limit'  => $per,
				'offset' => ( $page - 1 ) * $per,
				'return' => 'ids',
				'status' => 'any',
				'type'   => 'shop_order',
			) );
			$c    = is_array( $ids ) ? count( $ids ) : 0;
			$total += $c;
			$page++;
		} while ( $c === $per );
		return $total;
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
}
