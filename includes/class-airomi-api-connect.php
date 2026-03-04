<?php

defined( 'ABSPATH' ) || exit;

final class Airomi_API_Connect {

	private static $instance = null;

	public static function init() {
		if ( self::$instance !== null ) {
			return self::$instance;
		}
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', array( __CLASS__, 'admin_notice_woocommerce_required' ) );
			return null;
		}
		self::$instance = new self();
		return self::$instance;
	}

	public static function admin_notice_woocommerce_required() {
		echo '<div class="notice notice-error"><p>';
		esc_html_e( 'Airomi API Connect requires WooCommerce to be installed and active.', 'airomi-api-connect' );
		echo '</p></div>';
	}

	public static function activate() {
		require_once airomi_api_connect_path() . 'includes/class-airomi-schema.php';
		require_once airomi_api_connect_path() . 'includes/class-airomi-install.php';
		airomi_maybe_install_or_upgrade_schema();
	}

	private function __construct() {
		$this->maybe_upgrade_schema();
		require_once airomi_api_connect_path() . 'includes/class-airomi-settings.php';
		require_once airomi_api_connect_path() . 'includes/class-airomi-order-payload.php';
		require_once airomi_api_connect_path() . 'includes/class-airomi-sync.php';
		require_once airomi_api_connect_path() . 'includes/class-airomi-order-hooks.php';
		require_once airomi_api_connect_path() . 'includes/class-airomi-cron.php';
		require_once airomi_api_connect_path() . 'includes/class-airomi-ajax.php';
		Airomi_Order_Hooks::init();
		Airomi_Cron::init();
		Airomi_Ajax::init();
		$this->load_admin();
	}

	private function maybe_upgrade_schema() {
		require_once airomi_api_connect_path() . 'includes/class-airomi-schema.php';
		require_once airomi_api_connect_path() . 'includes/class-airomi-install.php';
		$stored = (int) get_option( AIROMI_OPTION_DB_VERSION, 0 );
		if ( $stored < AIROMI_DB_VERSION ) {
			airomi_maybe_install_or_upgrade_schema();
		}
	}

	private function load_admin() {
		if ( ! is_admin() ) {
			return;
		}
		require_once airomi_api_connect_path() . 'admin/class-airomi-admin-settings.php';
		Airomi_Admin_Settings::init();
	}
}
