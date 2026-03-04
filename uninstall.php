<?php
/**
 * Fired when the plugin is uninstalled (deleted).
 * Removes options and custom tables.
 *
 * @package Airomi_API_Connect
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

delete_option( 'airomi_db_version' );

$schema_file = dirname( __FILE__ ) . '/includes/class-airomi-schema.php';
if ( is_readable( $schema_file ) ) {
	require_once $schema_file;
	$schema  = airomi_get_schema();
	$prefix  = $wpdb->prefix;
	foreach ( array_keys( $schema ) as $table_key ) {
		$table = $prefix . $table_key;
		$wpdb->query( 'DROP TABLE IF EXISTS `' . esc_sql( $table ) . '`' );
	}
}
