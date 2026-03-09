<?php
/**
 * Plugin Name: Airomi API Connect
 * Plugin URI: https://proven.lt
 * Description: Connect your store to Airomi services.
 * Version: 1.4.3
 * Author: Rokas Zakarauskas
 * Author URI: https://proven.lt
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: airomi-api-connect
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * WC requires at least: 8.0
 * WC tested up to: 9.0
 */

defined( 'ABSPATH' ) || exit;

const AIROMI_API_CONNECT_VERSION = '1.4.3';
const AIROMI_API_CONNECT_FILE    = __FILE__;
const AIROMI_API_CONNECT_SLUG    = 'airomi-api-connect';

require_once __DIR__ . '/includes/constants.php';

function airomi_table( $key ) {
	global $wpdb;
	return $wpdb->prefix . $key;
}

add_action( 'before_woocommerce_init', function () {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
} );

function airomi_api_connect_path() {
	return plugin_dir_path( AIROMI_API_CONNECT_FILE );
}

require_once airomi_api_connect_path() . 'includes/class-airomi-api-connect.php';

register_activation_hook( __FILE__, array( 'Airomi_API_Connect', 'activate' ) );

register_deactivation_hook( __FILE__, function () {
	if ( function_exists( 'wp_clear_scheduled_hook' ) && defined( 'AIROMI_CRON_HOOK_RETRY' ) ) {
		wp_clear_scheduled_hook( AIROMI_CRON_HOOK_RETRY );
	}
} );

add_action( 'plugins_loaded', array( 'Airomi_API_Connect', 'init' ), 20 );
