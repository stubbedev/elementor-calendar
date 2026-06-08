<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Native WP list table for bookings — sortable, searchable, paginated, with
 * bulk + row actions. Backed by the wp_tsb_bookings table.
 */
class TSB_Bookings_Table extends WP_List_Table {

	public function __construct() {
		parent::__construct( array(
			'singular' => 'booking',
			'plural'   => 'bookings',
			'ajax'     => false,
		) );
	}

	public function get_columns() {
		return array(
			'cb'        => '<input type="checkbox" />',
			'slot_date' => 'Dato',
			'slot_time' => 'Tid',
			'name'      => 'Navn',
			'email'     => 'E-mail',
			'phone'     => 'Telefon',
			'message'   => 'Besked',
			'status'    => 'Status',
		);
	}

	protected function get_sortable_columns() {
		return array(
			'slot_date' => array( 'slot_date', true ),
			'slot_time' => array( 'slot_time', false ),
			'name'      => array( 'name', false ),
			'status'    => array( 'status', false ),
		);
	}

	protected function get_bulk_actions() {
		return array(
			'cancel' => 'Aflys',
			'delete' => 'Slet permanent',
		);
	}

	protected function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="ids[]" value="%d" />', (int) $item->id );
	}

	protected function column_default( $item, $col ) {
		switch ( $col ) {
			case 'slot_time':
				return esc_html( substr( $item->slot_time, 0, 5 ) );
			case 'email':
				return '<a href="mailto:' . esc_attr( $item->email ) . '">' . esc_html( $item->email ) . '</a>';
			case 'message':
				return esc_html( wp_trim_words( (string) $item->message, 12 ) );
			default:
				return isset( $item->$col ) ? esc_html( $item->$col ) : '';
		}
	}

	protected function column_name( $item ) {
		$base    = admin_url( 'admin-post.php' );
		$today   = current_time( 'Y-m-d' );
		$actions = array();

		if ( 'cancelled' !== $item->status && $item->slot_date >= $today ) {
			$edit = add_query_arg(
				array( 'page' => 'tsb_bookings', 'action' => 'edit', 'id' => (int) $item->id ),
				admin_url( 'admin.php' )
			);
			$actions['edit']   = '<a href="' . esc_url( $edit ) . '">Flyt</a>';
			$actions['cancel'] = '<a href="' . esc_url( wp_nonce_url( $base . '?action=tsb_booking_cancel&id=' . (int) $item->id, 'tsb_booking_cancel' ) ) . '" onclick="return confirm(\'Aflys booking? Tiden bliver ledig igen.\')">Aflys</a>';
		}
		$actions['delete'] = '<a href="' . esc_url( wp_nonce_url( $base . '?action=tsb_booking_delete&id=' . (int) $item->id, 'tsb_booking_delete' ) ) . '" onclick="return confirm(\'Slet permanent?\')">Slet</a>';

		return '<strong>' . esc_html( $item->name ) . '</strong>' . $this->row_actions( $actions );
	}

	public function single_row( $item ) {
		$style = ( 'cancelled' === $item->status ) ? ' style="opacity:.55"' : '';
		echo '<tr' . $style . '>'; // phpcs:ignore WordPress.Security.EscapeOutput
		$this->single_row_columns( $item );
		echo '</tr>';
	}

	public function no_items() {
		esc_html_e( 'Ingen bookinger.', 'tsb' );
	}

	public function prepare_items() {
		global $wpdb;
		$t   = TSB_DB::bookings_table();
		$per = 20;

		$where = '1=1';
		$args  = array();
		$search = isset( $_REQUEST['s'] ) ? trim( sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( '' !== $search ) {
			$like   = '%' . $wpdb->esc_like( $search ) . '%';
			$where .= ' AND (name LIKE %s OR email LIKE %s OR phone LIKE %s)';
			$args   = array( $like, $like, $like );
		}

		$allowed = array( 'slot_date', 'slot_time', 'name', 'status' );
		$orderby = ( isset( $_REQUEST['orderby'] ) && in_array( $_REQUEST['orderby'], $allowed, true ) ) ? $_REQUEST['orderby'] : 'slot_date'; // phpcs:ignore WordPress.Security.NonceVerification
		$order   = ( isset( $_REQUEST['order'] ) && 'asc' === strtolower( (string) $_REQUEST['order'] ) ) ? 'ASC' : 'DESC'; // phpcs:ignore WordPress.Security.NonceVerification
		$order_sql = "$orderby $order, slot_time $order"; // both whitelisted

		$count_sql = "SELECT COUNT(*) FROM $t WHERE $where";
		$total     = (int) ( $args ? $wpdb->get_var( $wpdb->prepare( $count_sql, $args ) ) : $wpdb->get_var( $count_sql ) );

		$page   = $this->get_pagenum();
		$offset = ( $page - 1 ) * $per;
		$sql    = "SELECT * FROM $t WHERE $where ORDER BY $order_sql LIMIT %d OFFSET %d";
		$this->items = $wpdb->get_results( $wpdb->prepare( $sql, array_merge( $args, array( $per, $offset ) ) ) );

		$this->set_pagination_args( array(
			'total_items' => $total,
			'per_page'    => $per,
			'total_pages' => (int) ceil( $total / $per ),
		) );
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );
	}
}
