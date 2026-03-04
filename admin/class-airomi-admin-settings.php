<?php

defined( 'ABSPATH' ) || exit;

class Airomi_Admin_Settings {

	const PAGE_SLUG   = AIROMI_API_CONNECT_SLUG;
	const TAB_PARAM   = 'tab';
	const SAVE_ACTION = 'airomi_save_settings';
	const NONCE_FIELD = 'airomi_settings_nonce';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ), 20 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'admin_post_' . self::SAVE_ACTION, array( __CLASS__, 'handle_save_settings' ) );
	}

	public static function get_tabs() {
		return array(
			'settings' => __( 'Settings', 'airomi-api-connect' ),
			'orders'   => __( 'Orders', 'airomi-api-connect' ),
		);
	}

	private static function get_tab_classes() {
		return array(
			'settings' => 'Airomi_Admin_Tab_Settings',
			'orders'   => 'Airomi_Admin_Tab_Orders',
		);
	}

	public static function enqueue_assets( $hook_suffix ) {
		if ( $hook_suffix !== 'woocommerce_page_' . self::PAGE_SLUG ) {
			return;
		}
		wp_enqueue_script(
			'airomi-admin',
			plugins_url( 'admin/js/airomi-admin.js', AIROMI_API_CONNECT_FILE ),
			array(),
			AIROMI_API_CONNECT_VERSION,
			true
		);
		wp_localize_script( 'airomi-admin', 'airomiAdmin', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'airomi_admin' ),
		) );
		wp_enqueue_style(
			'airomi-admin',
			plugins_url( 'admin/css/airomi-admin.css', AIROMI_API_CONNECT_FILE ),
			array(),
			AIROMI_API_CONNECT_VERSION
		);
	}

	public static function register_menu() {
		add_submenu_page(
			'woocommerce',
			__( 'Airomi API Connect', 'airomi-api-connect' ),
			__( 'Airomi API Connect', 'airomi-api-connect' ),
			'manage_woocommerce',
			self::PAGE_SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	public static function handle_save_settings() {
		require_once airomi_api_connect_path() . 'admin/class-airomi-admin-tab-settings.php';
		Airomi_Admin_Tab_Settings::handle_save();
	}

	public static function render_page() {
		$current_tab = self::get_current_tab();
		$base_url    = admin_url( 'admin.php?page=' . self::PAGE_SLUG );
		$tabs        = self::get_tabs();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Airomi API Connect', 'airomi-api-connect' ); ?></h1>
			<nav class="nav-tab-wrapper wp-clearfix" aria-label="<?php esc_attr_e( 'Secondary navigation', 'airomi-api-connect' ); ?>">
				<?php
				foreach ( $tabs as $tab_id => $label ) {
					$url   = add_query_arg( self::TAB_PARAM, $tab_id, $base_url );
					$class = 'nav-tab' . ( $current_tab === $tab_id ? ' nav-tab-active' : '' );
					printf(
						'<a href="%s" class="%s">%s</a>',
						esc_url( $url ),
						esc_attr( $class ),
						esc_html( $label )
					);
				}
				?>
			</nav>
			<div class="airomi-settings-tab-content" style="margin-top: 1em;">
				<?php
				$classes = self::get_tab_classes();
				$tab_class = $classes[ $current_tab ];
				$tab_file = airomi_api_connect_path() . 'admin/class-airomi-admin-tab-' . $current_tab . '.php';
				if ( is_readable( $tab_file ) ) {
					require_once $tab_file;
					if ( method_exists( $tab_class, 'render' ) ) {
						$tab_class::render();
					}
				}
				?>
			</div>
		</div>
		<?php
	}

	private static function get_current_tab() {
		$tabs = self::get_tabs();
		$tab  = isset( $_GET[ self::TAB_PARAM ] ) ? sanitize_key( $_GET[ self::TAB_PARAM ] ) : 'settings';
		return array_key_exists( $tab, $tabs ) ? $tab : 'settings';
	}
}
