<?php

defined( 'ABSPATH' ) || exit;

class Airomi_Order_Payload {

	private static $last_error = '';

	public static function get_last_error() {
		return self::$last_error;
	}

	public static function build( $order_id ) {
		self::$last_error = '';
		$order_id = (int) $order_id;
		$order = wc_get_order( $order_id );
		if ( ! $order instanceof WC_Order ) {
			self::$last_error = __( 'Order not found.', 'airomi-api-connect' );
			return null;
		}
		$controller = new WC_REST_Orders_Controller();
		$request  = new WP_REST_Request( 'GET' );
		$request->set_param( 'context', 'view' );
		$response = method_exists( $controller, 'prepare_item_for_response' )
			? $controller->prepare_item_for_response( $order, $request )
			: $controller->prepare_object_for_response( $order, $request );
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
