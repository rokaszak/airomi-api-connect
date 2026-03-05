<?php

defined( 'ABSPATH' ) || exit;

class Airomi_Sync {

	const BATCH_MAX_ORDERS   = 100;
	const FIRST_ATTEMPT_TIMEOUT = 0.5;

	private static function is_timeout_error( $response ) {
		if ( ! is_wp_error( $response ) ) {
			return false;
		}
		$msg = $response->get_error_message();
		return strpos( $msg, 'timed out' ) !== false || strpos( $msg, 'timeout' ) !== false;
	}

	private static function do_remote_request( $url, $request_args ) {
		$user_timeout = isset( $request_args['timeout'] ) ? (int) $request_args['timeout'] : 5;
		$request_args['timeout'] = self::FIRST_ATTEMPT_TIMEOUT;
		$response = wp_remote_request( $url, $request_args );
		if ( self::is_timeout_error( $response ) ) {
			$request_args['timeout'] = $user_timeout > 0 ? $user_timeout : 5;
			$response = wp_remote_request( $url, $request_args );
		}
		return $response;
	}

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

		$response = self::do_remote_request( $url, $request_args );
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

	public static function sync_orders_batch( array $order_ids ) {
		$order_ids = array_map( 'intval', $order_ids );
		$order_ids = array_unique( array_filter( $order_ids ) );
		if ( empty( $order_ids ) ) {
			return array( 'synced' => 0, 'failed' => 0, 'order_ids' => array() );
		}
		if ( count( $order_ids ) > self::BATCH_MAX_ORDERS ) {
			$order_ids = array_slice( $order_ids, 0, self::BATCH_MAX_ORDERS );
		}
		$table   = airomi_table( AIROMI_TABLE_ORDER_SYNC );
		$url     = Airomi_Settings::get_webhook_url();
		$method  = Airomi_Settings::get_http_method();
		$timeout = Airomi_Settings::get_timeout();
		$headers = self::build_request_headers();

		if ( empty( $url ) ) {
			foreach ( $order_ids as $id ) {
				self::update_row_failed( $id, null, __( 'Webhook URL not configured.', 'airomi-api-connect' ), true );
			}
			return array( 'synced' => 0, 'failed' => count( $order_ids ), 'order_ids' => $order_ids );
		}

		$payloads     = array();
		$ids_in_batch = array();
		foreach ( $order_ids as $order_id ) {
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
					continue;
				}
			}
			$payload_json = wp_json_encode( $payload );
			if ( $payload_json === false ) {
				self::update_row_failed( $order_id, null, 'JSON encode error', true );
				continue;
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
			$payloads[]     = $payload;
			$ids_in_batch[] = $order_id;
		}

		if ( empty( $ids_in_batch ) ) {
			$failed = count( $order_ids );
			return array( 'synced' => 0, 'failed' => $failed, 'order_ids' => $order_ids );
		}

		$body_json = wp_json_encode( $payloads );
		if ( $body_json === false ) {
			foreach ( $ids_in_batch as $id ) {
				self::update_row_failed( $id, null, 'JSON encode error', true );
			}
			return array( 'synced' => 0, 'failed' => count( $order_ids ), 'order_ids' => $order_ids );
		}

		$request_args = array(
			'method'      => $method,
			'timeout'     => $timeout,
			'headers'     => $headers,
			'body'        => $method !== 'GET' ? $body_json : null,
			'data_format' => 'body',
		);

		$response = self::do_remote_request( $url, $request_args );
		$code     = wp_remote_retrieve_response_code( $response );
		$body     = wp_remote_retrieve_body( $response );

		if ( is_wp_error( $response ) ) {
			$err_msg = $response->get_error_message();
			foreach ( $ids_in_batch as $id ) {
				self::update_row_failed( $id, null, $err_msg, true );
			}
			return array( 'synced' => 0, 'failed' => count( $order_ids ), 'order_ids' => $order_ids );
		}

		if ( (int) $code === 200 ) {
			foreach ( $ids_in_batch as $id ) {
				self::update_row_success( $id, $code, $body );
			}
			return array( 'synced' => count( $ids_in_batch ), 'failed' => count( $order_ids ) - count( $ids_in_batch ), 'order_ids' => $order_ids );
		}

		foreach ( $ids_in_batch as $id ) {
			self::update_row_failed( $id, $code, $body, true );
		}
		return array( 'synced' => 0, 'failed' => count( $order_ids ), 'order_ids' => $order_ids );
	}

	public static function sync_batch( $status, $limit = 10 ) {
		$table = airomi_table( AIROMI_TABLE_ORDER_SYNC );
		$wpdb  = $GLOBALS['wpdb'];
		$ids   = $wpdb->get_col( $wpdb->prepare(
			"SELECT order_id FROM `" . esc_sql( $table ) . "` WHERE sync_status = %s ORDER BY order_id ASC LIMIT %d",
			$status,
			$limit
		) );
		$ids = is_array( $ids ) ? array_map( 'intval', $ids ) : array();
		$result = array( 'synced' => 0, 'remaining' => 0, 'done' => false );
		if ( ! empty( $ids ) ) {
			$batch_result = self::sync_orders_batch( $ids );
			$result['synced'] = $batch_result['synced'];
		}
		$result['remaining'] = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM `" . esc_sql( $table ) . "` WHERE sync_status = %s",
			$status
		) );
		$result['done'] = $result['remaining'] === 0;
		return $result;
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
