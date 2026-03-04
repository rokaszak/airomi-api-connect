<?php

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'AIROMI_TABLE_ORDER_SYNC' ) ) {
	define( 'AIROMI_TABLE_ORDER_SYNC', 'airomi_order_sync' );
}
if ( ! defined( 'AIROMI_OPTION_SETTINGS' ) ) {
	define( 'AIROMI_OPTION_SETTINGS', 'airomi_settings' );
}
if ( ! defined( 'AIROMI_OPTION_DB_VERSION' ) ) {
	define( 'AIROMI_OPTION_DB_VERSION', 'airomi_db_version' );
}
if ( ! defined( 'AIROMI_STATUS_INIT' ) ) {
	define( 'AIROMI_STATUS_INIT', 'init' );
}
if ( ! defined( 'AIROMI_STATUS_PENDING' ) ) {
	define( 'AIROMI_STATUS_PENDING', 'pending' );
}
if ( ! defined( 'AIROMI_STATUS_SUCCESS' ) ) {
	define( 'AIROMI_STATUS_SUCCESS', 'success' );
}
if ( ! defined( 'AIROMI_STATUS_FAILED' ) ) {
	define( 'AIROMI_STATUS_FAILED', 'failed' );
}
if ( ! defined( 'AIROMI_CRON_HOOK_RETRY' ) ) {
	define( 'AIROMI_CRON_HOOK_RETRY', 'airomi_retry_failed_orders' );
}
