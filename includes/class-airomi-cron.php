<?php

defined( 'ABSPATH' ) || exit;

class Airomi_Cron {

	const HOOK_RETRY = AIROMI_CRON_HOOK_RETRY;

	public static function init() {
		add_filter( 'cron_schedules', array( __CLASS__, 'add_schedules' ) );
		add_action( self::HOOK_RETRY, array( __CLASS__, 'run_retry' ) );
		self::schedule_if_needed();
	}

	public static function add_schedules( $schedules ) {
		$schedules['every_5_minutes'] = array(
			'interval' => 5 * 60,
			'display'  => __( 'Every 5 minutes', 'airomi-api-connect' ),
		);
		$schedules['every_15_minutes'] = array(
			'interval' => 15 * 60,
			'display'  => __( 'Every 15 minutes', 'airomi-api-connect' ),
		);
		$schedules['every_30_minutes'] = array(
			'interval' => 30 * 60,
			'display'  => __( 'Every 30 minutes', 'airomi-api-connect' ),
		);
		$schedules['every_hour'] = array(
			'interval' => HOUR_IN_SECONDS,
			'display'  => __( 'Every hour', 'airomi-api-connect' ),
		);
		return $schedules;
	}

	public static function schedule_if_needed() {
		if ( ! Airomi_Settings::is_sync_enabled() ) {
			return;
		}
		$interval = Airomi_Settings::get_cron_interval();
		if ( ! wp_next_scheduled( self::HOOK_RETRY ) ) {
			wp_schedule_event( time(), $interval, self::HOOK_RETRY );
		}
	}

	public static function reschedule() {
		wp_clear_scheduled_hook( self::HOOK_RETRY );
		if ( Airomi_Settings::is_sync_enabled() ) {
			$interval = Airomi_Settings::get_cron_interval();
			wp_schedule_event( time(), $interval, self::HOOK_RETRY );
		}
	}

	public static function run_retry() {
		if ( ! Airomi_Settings::is_sync_enabled() ) {
			return;
		}
		Airomi_Sync::sync_batch( AIROMI_STATUS_FAILED, 50 );
	}
}
