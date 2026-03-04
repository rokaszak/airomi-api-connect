<?php
/**
 * Single source of truth for plugin database schema.
 * Edit this when adding tables or columns; bump AIROMI_DB_VERSION to trigger sync on upgrade.
 *
 * @package Airomi_API_Connect
 */

defined( 'ABSPATH' ) || exit;

const AIROMI_DB_VERSION = 1;

/**
 * Returns the full database schema definition.
 * Keys are table names (without prefix); values are column/index definitions.
 * Add tables here when needed; bump AIROMI_DB_VERSION to run sync on upgrade.
 *
 * @return array<string, array>
 */
function airomi_get_schema() {
	return array();
}
