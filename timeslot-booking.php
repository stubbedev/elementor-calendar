<?php
/**
 * Plugin Name: Timeslot Booking
 * Description: Custom Elementor widget. Predefined timeslots from an hour range, individual blocks removable, auto-blocks weekends + Danish bank holidays, contact form after slot pick, double-booking prevented.
 * Version: 1.0.0
 * Author: you
 * Text Domain: tsb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'TSB_VER', '1.0.0' );
define( 'TSB_PATH', plugin_dir_path( __FILE__ ) );
define( 'TSB_URL', plugin_dir_url( __FILE__ ) );

require_once TSB_PATH . 'includes/class-tsb-db.php';
require_once TSB_PATH . 'includes/class-tsb-holidays.php';
require_once TSB_PATH . 'includes/class-tsb-availability.php';
require_once TSB_PATH . 'includes/class-tsb-ics.php';
require_once TSB_PATH . 'includes/class-tsb-ajax.php';
require_once TSB_PATH . 'admin/class-tsb-admin.php';

register_activation_hook( __FILE__, array( 'TSB_DB', 'create_tables' ) );

// Self-heal: create tables on first load if activation hook was skipped
// (e.g. `wp plugin activate`, which suppresses activation hooks).
add_action( 'plugins_loaded', array( 'TSB_DB', 'ensure_schema' ) );

// Bail with an admin notice if Elementor is not active — the widget needs it.
add_action( 'plugins_loaded', function () {
	if ( did_action( 'elementor/loaded' ) || class_exists( '\Elementor\Plugin' ) ) {
		return;
	}
	add_action( 'admin_notices', function () {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		echo '<div class="notice notice-warning"><p>';
		echo esc_html__( 'Timeslot Booking kræver, at Elementor er installeret og aktiveret. Booking-widgeten vises ikke før Elementor er aktivt.', 'tsb' );
		echo '</p></div>';
	} );
} );

// Register the Elementor widget.
add_action( 'elementor/widgets/register', function ( $widgets_manager ) {
	require_once TSB_PATH . 'widgets/class-tsb-widget.php';
	$widgets_manager->register( new TSB_Widget() );
} );

// Frontend assets.
add_action( 'wp_enqueue_scripts', 'tsb_register_assets' );
add_action( 'elementor/editor/after_enqueue_scripts', 'tsb_register_assets' );

function tsb_register_assets() {
	if ( wp_script_is( 'tsb', 'registered' ) ) {
		return;
	}
	$s    = TSB_Availability::settings();
	$mode = $s['captcha_mode'];

	wp_register_style( 'tsb', TSB_URL . 'assets/booking.css', array(), TSB_VER );

	$deps = array();
	if ( 'recaptcha' === $mode ) {
		wp_register_script( 'tsb-recaptcha', 'https://www.google.com/recaptcha/api.js', array(), null, true );
		$deps[] = 'tsb-recaptcha';
	} elseif ( 'recaptcha_v3' === $mode && $s['captcha_site'] ) {
		wp_register_script( 'tsb-recaptcha', 'https://www.google.com/recaptcha/api.js?render=' . rawurlencode( $s['captcha_site'] ), array(), null, true );
		$deps[] = 'tsb-recaptcha';
	} elseif ( 'hcaptcha' === $mode ) {
		wp_register_script( 'tsb-hcaptcha', 'https://js.hcaptcha.com/1/api.js', array(), null, true );
		$deps[] = 'tsb-hcaptcha';
	}

	wp_register_script( 'tsb', TSB_URL . 'assets/booking.js', $deps, TSB_VER, true );
	wp_localize_script( 'tsb', 'TSB', array(
		'ajax'    => admin_url( 'admin-ajax.php' ),
		'nonce'   => wp_create_nonce( 'tsb_nonce' ),
		'captcha' => array(
			'mode' => $mode,
			'site' => $s['captcha_site'],
		),
	) );
}

// Force-enqueue inside the Elementor editor so the preview renders.
add_action( 'elementor/editor/after_enqueue_scripts', function () {
	wp_enqueue_script( 'tsb' );
	wp_enqueue_style( 'tsb' );
}, 20 );

// AJAX endpoints (logged-in + public).
add_action( 'wp_ajax_tsb_slots', array( 'TSB_Ajax', 'get_slots' ) );
add_action( 'wp_ajax_nopriv_tsb_slots', array( 'TSB_Ajax', 'get_slots' ) );
add_action( 'wp_ajax_tsb_book', array( 'TSB_Ajax', 'book' ) );
add_action( 'wp_ajax_nopriv_tsb_book', array( 'TSB_Ajax', 'book' ) );

new TSB_Admin();
