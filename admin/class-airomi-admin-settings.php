<?php
/**
 * Plugin main settings page under WooCommerce sidebar with tabs.
 *
 * @package Airomi_API_Connect
 */

defined( 'ABSPATH' ) || exit;

class Airomi_Admin_Settings {

	const PAGE_SLUG = 'airomi-api-connect';
	const TAB_PARAM = 'tab';

	/**
	 * Tab definitions: id => label.
	 *
	 * @var array<string, string>
	 */
	private static $tabs = array(
		'main'  => 'Main',
		'other' => 'Other',
	);

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ), 20 );
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

	public static function render_page() {
		$current_tab = self::get_current_tab();
		$base_url    = admin_url( 'admin.php?page=' . self::PAGE_SLUG );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Airomi API Connect', 'airomi-api-connect' ); ?></h1>
			<nav class="nav-tab-wrapper wp-clearfix" aria-label="<?php esc_attr_e( 'Secondary navigation', 'airomi-api-connect' ); ?>">
				<?php
				foreach ( self::$tabs as $tab_id => $label ) {
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
				if ( $current_tab === 'main' ) {
					self::render_main_tab();
				} else {
					self::render_other_tab();
				}
				?>
			</div>
		</div>
		<?php
	}

	private static function get_current_tab() {
		$tab = isset( $_GET[ self::TAB_PARAM ] ) ? sanitize_key( $_GET[ self::TAB_PARAM ] ) : 'main';
		return array_key_exists( $tab, self::$tabs ) ? $tab : 'main';
	}

	private static function render_main_tab() {
		$hpos_enabled = self::is_hpos_enabled();
		?>
		<div class="airomi-main-tab">
			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'HPOS (High-Performance Order Storage)', 'airomi-api-connect' ); ?></th>
						<td>
							<?php if ( $hpos_enabled ) : ?>
								<p><?php esc_html_e( 'HPOS is enabled.', 'airomi-api-connect' ); ?></p>
							<?php else : ?>
								<p><?php esc_html_e( 'HPOS is not enabled.', 'airomi-api-connect' ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
		<?php
	}

	private static function render_other_tab() {
		?>
		<div class="airomi-other-tab">
			<p><?php esc_html_e( 'This page is reserved for future use.', 'airomi-api-connect' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Whether WooCommerce HPOS (custom order tables) is enabled.
	 *
	 * @return bool
	 */
	private static function is_hpos_enabled() {
		if ( ! class_exists( 'Automattic\WooCommerce\Utilities\OrderUtil' ) ) {
			return false;
		}
		if ( ! method_exists( 'Automattic\WooCommerce\Utilities\OrderUtil', 'custom_orders_table_usage_is_enabled' ) ) {
			return false;
		}
		return \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
	}
}
