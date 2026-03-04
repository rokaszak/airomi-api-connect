<?php

defined( 'ABSPATH' ) || exit;

class Airomi_Sync {

	public static function sync_order( $order_id ) {
		$order_id = (int) $order_id;
		$table    = airomi_table( AIROMI_TABLE_ORDER_SYNC );
		$url      = Airomi_Settings::get_webhook_url();
		$method   = Airomi_Settings::get_http_method();
		$timeout  = Airomi_Settings::get_timeout();
		$headers  = self::build_request_headers();

		if ( empty( $url ) ) {
			self::update_row_failed( $order_id, null, __( 'Webhook URL not configured.', 'airomi-api-connect' ), true );
			return false;
		}

		$payload = Airomi_Order_Payload::build( $order_id );
		if ( $payload === null ) {
			if ( Airomi_Order_Payload::is_order_not_found() ) {
				$payload = array( 'id' => $order_id, 'status' => 'deleted' );
			} else {
				$message = Airomi_Order_Payload::get_last_error();
				if ( $message === '' ) {
					$message = __( 'Order or payload unavailable.', 'airomi-api-connect' );
				}
				self::update_row_failed( $order_id, null, $message, true );
				return false;
			}
		}

		$payload_json = wp_json_encode( $payload );
		if ( $payload_json === false ) {
			self::update_row_failed( $order_id, null, 'JSON encode error', true );
			return false;
		}

		$GLOBALS['wpdb']->update(
			$table,
			array(
				'sync_status' => AIROMI_STATUS_PENDING,
				'payload'     => $payload_json,
				'updated_at'  => current_time( 'mysql', true ),
			),
			array( 'order_id' => $order_id ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);

		$request_args = array(
			'method'      => $method,
			'timeout'     => $timeout,
			'headers'     => $headers,
			'body'        => $method !== 'GET' ? $payload_json : null,
			'data_format' => 'body',
		);

		$response = wp_remote_request( $url, $request_args );
		$code     = wp_remote_retrieve_response_code( $response );
		$body     = wp_remote_retrieve_body( $response );

		if ( is_wp_error( $response ) ) {
			self::update_row_failed( $order_id, null, $response->get_error_message(), true );
			return false;
		}

		if ( (int) $code === 200 ) {
			self::update_row_success( $order_id, $code, $body );
			return true;
		}

		self::update_row_failed( $order_id, $code, $body, true );
		return false;
	}

	public static function sync_orders( array $order_ids ) {
		foreach ( $order_ids as $order_id ) {
			self::sync_order( (int) $order_id );
		}
	}

	public static function sync_batch( $status, $limit = 10 ) {
		$table = airomi_table( AIROMI_TABLE_ORDER_SYNC );
		$wpdb  = $GLOBALS['wpdb'];
		$ids   = $wpdb->get_col( $wpdb->prepare(
			"SELECT order_id FROM `" . esc_sql( $table ) . "` WHERE sync_status = %s ORDER BY order_id ASC LIMIT %d",
			$status,
			$limit
		) );
		$synced = 0;
		foreach ( $ids as $order_id ) {
			self::sync_order( (int) $order_id );
			$synced++;
		}
		$remaining = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM `" . esc_sql( $table ) . "` WHERE sync_status = %s",
			$status
		) );
		return array(
			'synced'    => $synced,
			'remaining' => $remaining,
			'done'      => $remaining === 0,
		);
	}

	public static function mark_failed_sync_disabled( $order_id ) {
		self::update_row_failed( (int) $order_id, null, __( 'Sync not enabled.', 'airomi-api-connect' ), false );
	}

	private static function build_request_headers() {
		$headers = array(
			'Content-Type' => 'application/json',
			'User-Agent'   => 'Airomi-API-Connect/' . AIROMI_API_CONNECT_VERSION,
		);
		foreach ( Airomi_Settings::get_custom_headers() as $row ) {
			if ( ! empty( $row['key'] ) ) {
				$headers[ $row['key'] ] = $row['value'];
			}
		}
		return $headers;
	}

	private static function update_row_success( $order_id, $response_code, $response_body ) {
		$table = airomi_table( AIROMI_TABLE_ORDER_SYNC );
		$now   = current_time( 'mysql', true );
		$GLOBALS['wpdb']->update(
			$table,
			array(
				'sync_status'    => AIROMI_STATUS_SUCCESS,
				'response_code'  => (int) $response_code,
				'response_body'  => $response_body,
				'last_synced_at' => $now,
				'fail_count'     => 0,
				'updated_at'     => $now,
			),
			array( 'order_id' => $order_id ),
			array( '%s', '%d', '%s', '%s', '%d', '%s' ),
			array( '%d' )
		);
	}

	private static function update_row_failed( $order_id, $response_code, $response_body, $increment_fail = true ) {
		$table = airomi_table( AIROMI_TABLE_ORDER_SYNC );
		$row   = $GLOBALS['wpdb']->get_row( $GLOBALS['wpdb']->prepare( "SELECT fail_count FROM `" . esc_sql( $table ) . "` WHERE order_id = %d", $order_id ), ARRAY_A );
		$fail  = ( $row && isset( $row['fail_count'] ) ) ? (int) $row['fail_count'] : 0;
		if ( $increment_fail ) {
			$fail++;
		}
		$now = current_time( 'mysql', true );
		$GLOBALS['wpdb']->update(
			$table,
			array(
				'sync_status'    => AIROMI_STATUS_FAILED,
				'response_code'  => $response_code !== null ? (int) $response_code : 0,
				'response_body'  => $response_body,
				'last_synced_at' => $now,
				'fail_count'     => $fail,
				'updated_at'     => $now,
			),
			array( 'order_id' => $order_id ),
			array( '%s', '%d', '%s', '%s', '%d', '%s' ),
			array( '%d' )
		);
	}
}
