<?php

defined( 'ABSPATH' ) || exit;

function airomi_maybe_install_or_upgrade_schema() {
	$stored_version = (int) get_option( AIROMI_OPTION_DB_VERSION, 0 );
	if ( $stored_version >= AIROMI_DB_VERSION ) {
		return;
	}
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$schema = airomi_get_schema();
	foreach ( $schema as $table_key => $definition ) {
		$sql = airomi_get_schema_sql( $table_key, $definition );
		dbDelta( $sql );
	}
	update_option( AIROMI_OPTION_DB_VERSION, AIROMI_DB_VERSION );
}
