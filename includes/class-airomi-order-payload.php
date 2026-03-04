<?php

defined( 'ABSPATH' ) || exit;

class Airomi_Order_Payload {

	public static function build( $order_id ) {
		$order = wc_get_order( (int) $order_id );
		if ( ! $order instanceof WC_Order ) {
			return null;
		}
		$controller = new WC_REST_Orders_Controller();
		$request  = new WP_REST_Request( 'GET' );
		$request->set_param( 'context', 'view' );
		$response = method_exists( $controller, 'prepare_item_for_response' )
			? $controller->prepare_item_for_response( $order, $request )
			: $controller->prepare_object_for_response( $order, $request );
		if ( is_wp_error( $response ) ) {
			return null;
		}
		$server = rest_get_server();
		$data   = $server->response_to_data( $response, false );
		return is_array( $data ) ? $data : null;
	}
}
