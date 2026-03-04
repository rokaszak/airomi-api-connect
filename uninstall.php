<?php
/**
 * Fired when the plugin is uninstalled (deleted).
 * Removes options and custom tables.
 *
 * @package Airomi_API_Connect
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

$constants_file = dirname( __FILE__ ) . '/includes/constants.php';
if ( is_readable( $constants_file ) ) {
	require_once $constants_file;
}

if ( defined( 'AIROMI_OPTION_DB_VERSION' ) ) {
	delete_option( AIROMI_OPTION_DB_VERSION );
} else {
	delete_option( 'airomi_db_version' );
}
if ( defined( 'AIROMI_OPTION_SETTINGS' ) ) {
	delete_option( AIROMI_OPTION_SETTINGS );
} else {
	delete_option( 'airomi_settings' );
}

if ( function_exists( 'wp_clear_scheduled_hook' ) ) {
	if ( defined( 'AIROMI_CRON_HOOK_RETRY' ) ) {
		wp_clear_scheduled_hook( AIROMI_CRON_HOOK_RETRY );
	} else {
		wp_clear_scheduled_hook( 'airomi_retry_failed_orders' );
	}
}

$schema_file = dirname( __FILE__ ) . '/includes/class-airomi-schema.php';
if ( is_readable( $schema_file ) ) {
	require_once $schema_file;
	$schema = airomi_get_schema();
	$prefix = $wpdb->prefix;
	foreach ( array_keys( $schema ) as $table_key ) {
		$table = $prefix . $table_key;
		$wpdb->query( 'DROP TABLE IF EXISTS `' . esc_sql( $table ) . '`' );
	}
}
