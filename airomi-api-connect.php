<?php
/**
 * Plugin Name: Airomi API Connect
 * Plugin URI: https://proven.lt
 * Description: Connect your store to Airomi services.
 * Version: 1.0.2
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

const AIROMI_API_CONNECT_VERSION = '1.0.2';
const AIROMI_API_CONNECT_FILE    = __FILE__;
const AIROMI_API_CONNECT_SLUG    = 'airomi-api-connect';

function airomi_api_connect_path() {
	return plugin_dir_path( AIROMI_API_CONNECT_FILE );
}

require_once airomi_api_connect_path() . 'includes/class-airomi-api-connect.php';

register_activation_hook( __FILE__, array( 'Airomi_API_Connect', 'activate' ) );

add_action( 'plugins_loaded', array( 'Airomi_API_Connect', 'init' ), 20 );
