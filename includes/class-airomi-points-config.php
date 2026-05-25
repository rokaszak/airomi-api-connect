<?php

defined( 'ABSPATH' ) || exit;

class Airomi_Points_Config {

	const HOOK          = AIROMI_CRON_HOOK_POINTS_CONFIG;
	const OPTION        = AIROMI_OPTION_POINTS_CONFIG;
	const ENDPOINT_PATH = AIROMI_POINTS_CONFIG_ENDPOINT_PATH;

	public static function init() {
		add_action( self::HOOK, array( __CLASS__, 'fetch' ) );
		self::schedule_if_needed();
	}

	public static function schedule_if_needed() {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, 'hourly', self::HOOK );
		}
	}

	public static function clear_schedule() {
		wp_clear_scheduled_hook( self::HOOK );
	}

	public static function get_stored() {
		$stored = get_option( self::OPTION, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		return array(
			'enabled'         => ! empty( $stored['enabled'] ),
			'points_per_euro' => isset( $stored['points_per_euro'] ) && is_numeric( $stored['points_per_euro'] ) ? (float) $stored['points_per_euro'] : 0.0,
		);
	}

	public static function get_base_url() {
		$webhook = Airomi_Settings::get_webhook_url();
		if ( empty( $webhook ) ) {
			return '';
		}
		$parts = wp_parse_url( $webhook );
		if ( empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return '';
		}
		$base = $parts['scheme'] . '://' . $parts['host'];
		if ( ! empty( $parts['port'] ) ) {
			$base .= ':' . (int) $parts['port'];
		}
		return $base;
	}

	private static function build_headers() {
		$headers = array(
			'Accept'     => 'application/json',
			'User-Agent' => 'Airomi-API-Connect/' . AIROMI_API_CONNECT_VERSION,
		);
		foreach ( Airomi_Settings::get_custom_headers() as $row ) {
			if ( ! empty( $row['key'] ) ) {
				$headers[ $row['key'] ] = $row['value'];
			}
		}
		return $headers;
	}

	public static function fetch() {
		$base = self::get_base_url();
		if ( $base === '' ) {
			return self::store( false, 0.0 );
		}
		$response = wp_remote_get( $base . self::ENDPOINT_PATH, array(
			'timeout' => Airomi_Settings::get_timeout(),
			'headers' => self::build_headers(),
		) );
		if ( is_wp_error( $response ) || (int) wp_remote_retrieve_response_code( $response ) !== 200 ) {
			return self::store( false, 0.0 );
		}
		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) || ! array_key_exists( 'enabled', $data ) || ! array_key_exists( 'points_per_euro', $data ) ) {
			return self::store( false, 0.0 );
		}
		$enabled = ! empty( $data['enabled'] );
		$rate    = is_numeric( $data['points_per_euro'] ) ? (float) $data['points_per_euro'] : 0.0;
		return self::store( $enabled, $rate );
	}

	private static function store( $enabled, $rate ) {
		$stored = array(
			'enabled'         => (bool) $enabled,
			'points_per_euro' => (float) $rate,
		);
		update_option( self::OPTION, $stored, false );
		return $stored;
	}
}
