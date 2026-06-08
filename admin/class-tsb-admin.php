<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin shell. Renders a single React app (WordPress' bundled wp.element +
 * @wordpress/components) that talks to the TSB_REST API. The only server-rendered
 * piece left is the CSV export (a file download).
 */
class TSB_Admin {

	protected $hooks = array();

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_post_tsb_export_csv', array( $this, 'export_csv' ) );
	}

	public function menu() {
		$main = add_menu_page( __( 'Bookings', 'tsb' ), __( 'Bookings', 'tsb' ), 'manage_options', 'tsb_bookings', array( $this, 'render_bookings' ), 'dashicons-calendar-alt', 26 );
		add_submenu_page( 'tsb_bookings', __( 'All bookings', 'tsb' ), __( 'All bookings', 'tsb' ), 'manage_options', 'tsb_bookings', array( $this, 'render_bookings' ) );
		$set = add_submenu_page( 'tsb_bookings', __( 'Settings', 'tsb' ), __( 'Settings', 'tsb' ), 'manage_options', 'tsb_settings', array( $this, 'render_settings' ) );
		$this->hooks = array( $main, $set );
	}

	public function assets( $hook ) {
		if ( ! in_array( $hook, $this->hooks, true ) ) {
			return;
		}
		$asset = require TSB_PATH . 'build/index.asset.php'; // deps + content hash from wp-scripts

		wp_enqueue_style( 'wp-components' );
		wp_enqueue_style( 'tsb-admin', TSB_URL . 'build/index.css', array( 'wp-components' ), $asset['version'] );

		wp_enqueue_script( 'tsb-admin', TSB_URL . 'build/index.js', $asset['dependencies'], $asset['version'], true );
		wp_localize_script( 'tsb-admin', 'tsbAdmin', array(
			'rest'      => esc_url_raw( rest_url( TSB_REST::NS . '/' ) ),
			'nonce'     => wp_create_nonce( 'wp_rest' ),
			'exportUrl' => wp_nonce_url( admin_url( 'admin-post.php?action=tsb_export_csv' ), 'tsb_export_csv' ),
		) );
		wp_set_script_translations( 'tsb-admin', 'tsb', TSB_PATH . 'languages' );
	}

	public function render_bookings() {
		echo '<div class="wrap"><div id="tsb-admin" data-view="bookings"></div></div>';
	}

	public function render_settings() {
		echo '<div class="wrap"><div id="tsb-admin" data-view="settings"></div></div>';
	}

	/* ---------- CSV export (file download stays server-side) ---------- */

	public function export_csv() {
		check_admin_referer( 'tsb_export_csv' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die();
		}
		global $wpdb;
		$rows = $wpdb->get_results( 'SELECT slot_date, slot_time, name, email, phone, message, status, created_at FROM ' . TSB_DB::bookings_table() . ' ORDER BY slot_date DESC, slot_time DESC', ARRAY_A );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=bookings-' . current_time( 'Y-m-d' ) . '.csv' );

		$out = fopen( 'php://output', 'w' );
		fprintf( $out, "\xEF\xBB\xBF" ); // UTF-8 BOM so Excel reads non-ASCII
		fputcsv( $out, array(
			__( 'Date', 'tsb' ), __( 'Time', 'tsb' ), __( 'Name', 'tsb' ), __( 'Email', 'tsb' ),
			__( 'Phone', 'tsb' ), __( 'Message', 'tsb' ), __( 'Status', 'tsb' ), __( 'Created', 'tsb' ),
		) );
		foreach ( $rows as $r ) {
			$r['slot_time'] = substr( $r['slot_time'], 0, 5 );
			fputcsv( $out, $r );
		}
		fclose( $out );
		exit;
	}
}
