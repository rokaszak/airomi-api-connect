<?php
/**
 * Install and upgrade database schema from the single schema definition.
 *
 * @package Airomi_API_Connect
 */

defined( 'ABSPATH' ) || exit;

/**
 * Creates or updates the database to match the current schema.
 * Call on activation and when airomi_db_version option is less than AIROMI_DB_VERSION.
 */
function airomi_maybe_install_or_upgrade_schema() {
	global $wpdb;

	$stored_version = (int) get_option( 'airomi_db_version', 0 );
	if ( $stored_version >= AIROMI_DB_VERSION ) {
		return;
	}

	$schema = airomi_get_schema();
	$prefix = $wpdb->prefix;

	foreach ( $schema as $table_key => $definition ) {
		$table_name = $prefix . $table_key;
		$exists     = $wpdb->get_var( "SHOW TABLES LIKE '" . esc_sql( $table_name ) . "'" ) === $table_name;

		if ( ! $exists ) {
			airomi_create_table( $table_name, $definition );
		} else {
			airomi_add_missing_columns( $table_name, $definition );
		}
	}

	update_option( 'airomi_db_version', AIROMI_DB_VERSION );
}

/**
 * Creates a table from a schema definition.
 *
 * @param string $table_name Full table name (with prefix).
 * @param array  $definition Schema definition with 'columns' and 'keys'.
 */
function airomi_create_table( $table_name, $definition ) {
	global $wpdb;

	$parts = array();
	foreach ( $definition['columns'] as $col => $col_def ) {
		$part = '`' . esc_sql( $col ) . '` ' . $col_def['type'] . ' ' . $col_def['null'];
		if ( ! empty( $col_def['default'] ) && $col_def['default'] !== null ) {
			$part .= ' DEFAULT ' . ( $col_def['default'] === 'CURRENT_TIMESTAMP' ? 'CURRENT_TIMESTAMP' : "'" . esc_sql( $col_def['default'] ) . "'" );
		}
		if ( ! empty( $col_def['extra'] ) ) {
			$part .= ' ' . $col_def['extra'];
		}
		$parts[] = $part;
	}
	if ( ! empty( $definition['keys'] ) ) {
		$parts = array_merge( $parts, $definition['keys'] );
	}
	$sql = 'CREATE TABLE `' . esc_sql( $table_name ) . '` (' . implode( ', ', $parts ) . ') ' . $wpdb->get_charset_collate();
	$wpdb->query( $sql );
}

/**
 * Adds any missing columns to an existing table.
 *
 * @param string $table_name Full table name (with prefix).
 * @param array  $definition Schema definition with 'columns'.
 */
function airomi_add_missing_columns( $table_name, $definition ) {
	global $wpdb;

	$existing = $wpdb->get_results( 'SHOW COLUMNS FROM `' . esc_sql( $table_name ) . '`', ARRAY_A );
	$existing = $existing ? array_column( $existing, 'Field' ) : array();

	foreach ( $definition['columns'] as $col => $col_def ) {
		if ( in_array( $col, $existing, true ) ) {
			continue;
		}
		$part = '`' . esc_sql( $col ) . '` ' . $col_def['type'] . ' ' . $col_def['null'];
		if ( ! empty( $col_def['default'] ) && $col_def['default'] !== null ) {
			$part .= ' DEFAULT ' . ( $col_def['default'] === 'CURRENT_TIMESTAMP' ? 'CURRENT_TIMESTAMP' : "'" . esc_sql( $col_def['default'] ) . "'" );
		}
		if ( ! empty( $col_def['extra'] ) ) {
			$part .= ' ' . $col_def['extra'];
		}
		$wpdb->query( 'ALTER TABLE `' . esc_sql( $table_name ) . '` ADD COLUMN ' . $part );
	}
}
