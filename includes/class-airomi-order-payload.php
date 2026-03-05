<?php

defined( 'ABSPATH' ) || exit;

class Airomi_Order_Payload {

	private static $last_error = '';
	private static $order_not_found = false;

	public static function get_last_error() {
		return self::$last_error;
	}

	public static function is_order_not_found() {
		return self::$order_not_found;
	}

	public static function build( $order_id, $order = null ) {
		self::$last_error = '';
		self::$order_not_found = false;
		$order_id = (int) $order_id;
		if ( ! $order instanceof WC_Order ) {
			$order = wc_get_order( $order_id );
		}
		if ( ! $order instanceof WC_Order ) {
			self::$order_not_found = true;
			self::$last_error = __( 'Order not found.', 'airomi-api-connect' );
			return null;
		}
		if ( $order->get_status() === 'trash' ) {
			return array( 'id' => $order_id, 'status' => 'trashed' );
		}
		$request = new WP_REST_Request( 'GET' );
		$request->set_param( 'context', 'view' );
		$request->set_param( 'dp', null );
		if ( class_exists( 'WC_REST_Orders_V2_Controller' ) ) {
			$controller = new WC_REST_Orders_V2_Controller();
			$ref = new ReflectionClass( $controller );
			$prop = $ref->getProperty( 'request' );
			$prop->setAccessible( true );
			$prop->setValue( $controller, $request );
		} else {
			$controller = new WC_REST_Orders_Controller();
		}
		$response = $controller->prepare_object_for_response( $order, $request );
		if ( is_wp_error( $response ) ) {
			self::$last_error = $response->get_error_message();
			return null;
		}
		$server = rest_get_server();
		$data   = $server->response_to_data( $response, false );
		if ( ! is_array( $data ) ) {
			self::$last_error = __( 'Invalid REST response.', 'airomi-api-connect' );
			return null;
		}
		return $data;
	}
}
