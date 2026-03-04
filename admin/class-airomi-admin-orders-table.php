<?php

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Airomi_Admin_Orders_Table extends WP_List_Table {

	private static $hpos_enabled = null;

	public function __construct() {
		parent::__construct( array(
			'singular' => 'order',
			'plural'   => 'orders',
			'ajax'     => false,
		) );
	}

	public function get_columns() {
		return array(
			'cb'             => '<input type="checkbox" />',
			'order_id'       => __( 'Order ID', 'airomi-api-connect' ),
			'sync_status'    => __( 'Sync Status', 'airomi-api-connect' ),
			'response_code'  => __( 'Response Code', 'airomi-api-connect' ),
			'last_synced_at' => __( 'Last Synced', 'airomi-api-connect' ),
			'fail_count'     => __( 'Fail Count', 'airomi-api-connect' ),
			'actions'        => __( 'Actions', 'airomi-api-connect' ),
		);
	}

	protected function get_sortable_columns() {
		return array(
			'order_id'       => array( 'order_id', false ),
			'sync_status'    => array( 'sync_status', false ),
			'last_synced_at' => array( 'last_synced_at', true ),
			'fail_count'     => array( 'fail_count', false ),
		);
	}

	protected function get_bulk_actions() {
		return array(
			'airomi_sync' => __( 'Sync Selected', 'airomi-api-connect' ),
		);
	}

	public function prepare_items() {
		$table        = airomi_table( AIROMI_TABLE_ORDER_SYNC );
		$per_page     = 200;
		$status_filter = isset( $_GET['airomi_status'] ) ? sanitize_key( $_GET['airomi_status'] ) : '';
		$orderby      = isset( $_GET['orderby'] ) ? sanitize_key( $_GET['orderby'] ) : 'order_id';
		$order        = isset( $_GET['order'] ) && strtoupper( $_GET['order'] ) === 'ASC' ? 'ASC' : 'DESC';

		$allowed_orderby = array( 'order_id', 'sync_status', 'last_synced_at', 'fail_count' );
		if ( ! in_array( $orderby, $allowed_orderby, true ) ) {
			$orderby = 'order_id';
		}

		$where        = '1=1';
		$where_values = array();
		$valid_statuses = array( AIROMI_STATUS_INIT, AIROMI_STATUS_PENDING, AIROMI_STATUS_SUCCESS, AIROMI_STATUS_FAILED );
		if ( $status_filter && in_array( $status_filter, $valid_statuses, true ) ) {
			$where .= ' AND sync_status = %s';
			$where_values[] = $status_filter;
		}

		$wpdb         = $GLOBALS['wpdb'];
		$count_sql    = "SELECT COUNT(*) FROM `" . esc_sql( $table ) . "` WHERE " . $where;
		$total_items  = $where_values ? (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $where_values ) ) : (int) $wpdb->get_var( $count_sql );

		$paged  = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
		$offset = ( $paged - 1 ) * $per_page;

		$sql   = "SELECT id, order_id, sync_status, payload, response_code, response_body, last_synced_at, fail_count, created_at, updated_at FROM `" . esc_sql( $table ) . "` WHERE " . $where;
		$sql  .= " ORDER BY `" . esc_sql( $orderby ) . "` " . ( $order === 'ASC' ? 'ASC' : 'DESC' );
		$sql  .= " LIMIT " . absint( $per_page ) . " OFFSET " . absint( $offset );

		$items = $where_values
			? $wpdb->get_results( $wpdb->prepare( $sql, $where_values ), ARRAY_A )
			: $wpdb->get_results( $sql, ARRAY_A );

		if ( ! is_array( $items ) ) {
			$items = array();
		}

		$this->items = $items;
		$this->_column_headers = array(
			$this->get_columns(),
			array(),
			$this->get_sortable_columns(),
			'order_id',
		);
		$this->set_pagination_args( array(
			'total_items' => $total_items,
			'per_page'   => $per_page,
			'total_pages' => ceil( $total_items / $per_page ),
		) );
	}

	protected function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="order_ids[]" value="%d" />', (int) $item['order_id'] );
	}

	protected function column_order_id( $item ) {
		$id = (int) $item['order_id'];
		if ( self::$hpos_enabled === null && class_exists( 'Automattic\WooCommerce\Utilities\OrderUtil' ) && method_exists( 'Automattic\WooCommerce\Utilities\OrderUtil', 'custom_orders_table_usage_is_enabled' ) ) {
			self::$hpos_enabled = \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
		}
		$url = self::$hpos_enabled ? admin_url( 'admin.php?page=wc-orders&action=edit&id=' . $id ) : admin_url( 'post.php?post=' . $id . '&action=edit' );
		return '<a href="' . esc_url( $url ) . '">#' . esc_html( $id ) . '</a>';
	}

	protected function column_sync_status( $item ) {
		$status = isset( $item['sync_status'] ) ? $item['sync_status'] : AIROMI_STATUS_INIT;
		$class  = 'airomi-status airomi-status-' . esc_attr( $status );
		$label  = ucfirst( $status );
		return '<span class="' . esc_attr( $class ) . '">' . esc_html( $label ) . '</span>';
	}

	protected function column_response_code( $item ) {
		$code = isset( $item['response_code'] ) ? $item['response_code'] : '';
		if ( $code === '' || $code === null ) {
			return '—';
		}
		return (int) $code;
	}

	protected function column_last_synced_at( $item ) {
		$date = isset( $item['last_synced_at'] ) ? $item['last_synced_at'] : '';
		if ( $date === '' || $date === null ) {
			return '—';
		}
		return esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $date ) ) );
	}

	protected function column_fail_count( $item ) {
		$n = isset( $item['fail_count'] ) ? (int) $item['fail_count'] : 0;
		return (string) $n;
	}

	protected function column_actions( $item ) {
		$order_id = (int) $item['order_id'];
		$actions = array(
			'sync'  => '<button type="button" class="button button-small airomi-sync-one" data-order-id="' . esc_attr( $order_id ) . '">' . esc_html__( 'Sync Now', 'airomi-api-connect' ) . '</button>',
			'view'  => '<button type="button" class="button button-small airomi-view-details" data-order-id="' . esc_attr( $order_id ) . '">' . esc_html__( 'View Details', 'airomi-api-connect' ) . '</button>',
		);
		return implode( ' ', $actions);
	}

	protected function column_default( $item, $column_name ) {
		return isset( $item[ $column_name ] ) ? esc_html( (string) $item[ $column_name ] ) : '—';
	}

	protected function extra_tablenav( $which ) {
		if ( $which !== 'top' ) {
			return;
		}
		$current = isset( $_GET['airomi_status'] ) ? sanitize_key( $_GET['airomi_status'] ) : '';
		?>
		<div class="alignleft actions">
			<label for="airomi-status-filter" class="screen-reader-text"><?php esc_html_e( 'Filter by status', 'airomi-api-connect' ); ?></label>
			<select name="airomi_status" id="airomi-status-filter">
				<option value=""><?php esc_html_e( 'All statuses', 'airomi-api-connect' ); ?></option>
				<option value="<?php echo esc_attr( AIROMI_STATUS_INIT ); ?>" <?php selected( $current, AIROMI_STATUS_INIT ); ?>><?php esc_html_e( 'Init', 'airomi-api-connect' ); ?></option>
				<option value="<?php echo esc_attr( AIROMI_STATUS_PENDING ); ?>" <?php selected( $current, AIROMI_STATUS_PENDING ); ?>><?php esc_html_e( 'Pending', 'airomi-api-connect' ); ?></option>
				<option value="<?php echo esc_attr( AIROMI_STATUS_SUCCESS ); ?>" <?php selected( $current, AIROMI_STATUS_SUCCESS ); ?>><?php esc_html_e( 'Success', 'airomi-api-connect' ); ?></option>
				<option value="<?php echo esc_attr( AIROMI_STATUS_FAILED ); ?>" <?php selected( $current, AIROMI_STATUS_FAILED ); ?>><?php esc_html_e( 'Failed', 'airomi-api-connect' ); ?></option>
			</select>
			<button type="submit" class="button"><?php esc_html_e( 'Filter', 'airomi-api-connect' ); ?></button>
		</div>
		<?php
	}
}
