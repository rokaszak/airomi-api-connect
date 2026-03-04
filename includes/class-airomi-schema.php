<?php
defined( 'ABSPATH' ) || exit;

const AIROMI_DB_VERSION = 2;

function airomi_get_schema() {
	return array(
		'airomi_order_sync' => array(
			'columns' => array(
				'id'             => array(
					'type'    => 'bigint(20) unsigned',
					'null'    => 'NOT NULL',
					'default' => null,
					'extra'   => 'AUTO_INCREMENT',
				),
				'order_id'       => array(
					'type'    => 'bigint(20) unsigned',
					'null'    => 'NOT NULL',
					'default' => null,
					'extra'   => '',
				),
				'sync_status'    => array(
					'type'    => "enum('init','pending','success','failed')",
					'null'    => 'NOT NULL',
					'default' => 'init',
					'extra'   => '',
				),
				'payload'        => array(
					'type'    => 'longtext',
					'null'    => 'NULL',
					'default' => null,
					'extra'   => '',
				),
				'response_code'  => array(
					'type'    => 'smallint(5) unsigned',
					'null'    => 'NULL',
					'default' => null,
					'extra'   => '',
				),
				'response_body'  => array(
					'type'    => 'longtext',
					'null'    => 'NULL',
					'default' => null,
					'extra'   => '',
				),
				'last_synced_at' => array(
					'type'    => 'datetime',
					'null'    => 'NULL',
					'default' => null,
					'extra'   => '',
				),
				'fail_count'     => array(
					'type'    => 'int(10) unsigned',
					'null'    => 'NOT NULL',
					'default' => '0',
					'extra'   => '',
				),
				'created_at'     => array(
					'type'    => 'datetime',
					'null'    => 'NOT NULL',
					'default' => 'CURRENT_TIMESTAMP',
					'extra'   => '',
				),
				'updated_at'     => array(
					'type'    => 'datetime',
					'null'    => 'NOT NULL',
					'default' => 'CURRENT_TIMESTAMP',
					'extra'   => 'ON UPDATE CURRENT_TIMESTAMP',
				),
			),
			'keys' => array(
				'PRIMARY KEY  (id)',
				'UNIQUE KEY order_id (order_id)',
				'KEY sync_status (sync_status)',
			),
		),
	);
}

function airomi_get_schema_sql( $table_key, $definition ) {
	global $wpdb;
	$lines = array();
	foreach ( $definition['columns'] as $col => $col_def ) {
		$line = '  ' . $col . ' ' . $col_def['type'] . ' ' . $col_def['null'];
		if ( isset( $col_def['default'] ) && $col_def['default'] !== null ) {
			$line .= $col_def['default'] === 'CURRENT_TIMESTAMP' ? ' DEFAULT CURRENT_TIMESTAMP' : " DEFAULT '" . esc_sql( $col_def['default'] ) . "'";
		}
		if ( ! empty( $col_def['extra'] ) ) {
			$line .= ' ' . $col_def['extra'];
		}
		$lines[] = $line;
	}
	foreach ( $definition['keys'] as $key_def ) {
		$lines[] = '  ' . $key_def;
	}
	$table_name = $wpdb->prefix . $table_key;
	return 'CREATE TABLE ' . $table_name . " (\n" . implode( ",\n", $lines ) . "\n) " . $wpdb->get_charset_collate();
}
