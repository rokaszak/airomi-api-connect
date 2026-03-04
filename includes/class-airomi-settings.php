<?php

defined( 'ABSPATH' ) || exit;

class Airomi_Settings {

	const OPTION_KEY = AIROMI_OPTION_SETTINGS;

	public static function get_all() {
		$saved = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		return wp_parse_args( $saved, self::defaults() );
	}

	public static function defaults() {
		return array(
			'webhook_url'    => '',
			'http_method'    => 'POST',
			'timeout'        => 5,
			'sync_enabled'   => false,
			'cron_interval'  => 'every_5_minutes',
			'custom_headers' => array(),
		);
	}

	public static function save( $settings ) {
		$current   = self::get_all();
		$sanitized = array();
		if ( array_key_exists( 'webhook_url', $settings ) ) {
			$sanitized['webhook_url'] = esc_url_raw( trim( (string) $settings['webhook_url'] ) );
		}
		if ( array_key_exists( 'http_method', $settings ) ) {
			$v = sanitize_text_field( (string) $settings['http_method'] );
			$sanitized['http_method'] = in_array( $v, array( 'GET', 'POST', 'PUT', 'PATCH' ), true ) ? $v : 'POST';
		}
		if ( array_key_exists( 'timeout', $settings ) ) {
			$v = (int) $settings['timeout'];
			$sanitized['timeout'] = $v > 0 ? $v : 5;
		}
		if ( array_key_exists( 'sync_enabled', $settings ) ) {
			$sanitized['sync_enabled'] = ! empty( $settings['sync_enabled'] );
		}
		if ( array_key_exists( 'cron_interval', $settings ) ) {
			$v = sanitize_text_field( (string) $settings['cron_interval'] );
			$valid = array( 'every_1_minute', 'every_5_minutes', 'every_15_minutes', 'every_30_minutes', 'every_hour' );
			$sanitized['cron_interval'] = in_array( $v, $valid, true ) ? $v : 'every_5_minutes';
		}
		if ( array_key_exists( 'custom_headers', $settings ) && is_array( $settings['custom_headers'] ) ) {
			$out = array();
			foreach ( $settings['custom_headers'] as $row ) {
				if ( ! is_array( $row ) || ! isset( $row['key'], $row['value'] ) ) {
					continue;
				}
				$key = sanitize_text_field( (string) $row['key'] );
				if ( $key !== '' ) {
					$out[] = array(
						'key'   => $key,
						'value' => sanitize_text_field( (string) $row['value'] ),
					);
				}
			}
			$sanitized['custom_headers'] = $out;
		}
		$merged = wp_parse_args( $sanitized, $current );
		return update_option( self::OPTION_KEY, $merged );
	}

	public static function get_webhook_url() {
		$all = self::get_all();
		return is_string( $all['webhook_url'] ) ? $all['webhook_url'] : '';
	}

	public static function get_http_method() {
		$all = self::get_all();
		$v   = is_string( $all['http_method'] ) ? $all['http_method'] : 'POST';
		return in_array( $v, array( 'GET', 'POST', 'PUT', 'PATCH' ), true ) ? $v : 'POST';
	}

	public static function get_timeout() {
		$all = self::get_all();
		$v   = (int) $all['timeout'];
		return $v > 0 ? $v : 5;
	}

	public static function is_sync_enabled() {
		$all = self::get_all();
		return ! empty( $all['sync_enabled'] );
	}

	public static function get_cron_interval() {
		$all = self::get_all();
		$v   = is_string( $all['cron_interval'] ) ? $all['cron_interval'] : 'every_5_minutes';
		$valid = array( 'every_1_minute', 'every_5_minutes', 'every_15_minutes', 'every_30_minutes', 'every_hour' );
		return in_array( $v, $valid, true ) ? $v : 'every_5_minutes';
	}

	public static function get_custom_headers() {
		$all = self::get_all();
		$h   = $all['custom_headers'];
		if ( ! is_array( $h ) ) {
			return array();
		}
		$out = array();
		foreach ( $h as $row ) {
			if ( ! is_array( $row ) || ! isset( $row['key'], $row['value'] ) ) {
				continue;
			}
			$out[] = array(
				'key'   => is_string( $row['key'] ) ? $row['key'] : '',
				'value' => is_string( $row['value'] ) ? $row['value'] : '',
			);
		}
		return $out;
	}

	public static function get_db_version() {
		return (int) get_option( AIROMI_OPTION_DB_VERSION, 0 );
	}

	public static function set_db_version( $version ) {
		return update_option( AIROMI_OPTION_DB_VERSION, (int) $version );
	}
}
