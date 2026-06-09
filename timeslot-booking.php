<?php
/**
 * Plugin Name: Timeslot Booking
 * Description: Custom Elementor widget. Predefined timeslots from an hour range, individual blocks removable, auto-blocks weekends + Danish bank holidays, contact form after slot pick, double-booking prevented.
 * Version: 0.1.0
 * Author: you
 * Text Domain: tsb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'TSB_VER', '0.1.0' );
define( 'TSB_PATH', plugin_dir_path( __FILE__ ) );
define( 'TSB_URL', plugin_dir_url( __FILE__ ) );

require_once TSB_PATH . 'includes/class-tsb-db.php';
require_once TSB_PATH . 'includes/class-tsb-holidays.php';
require_once TSB_PATH . 'includes/class-tsb-availability.php';
require_once TSB_PATH . 'includes/class-tsb-i18n.php';
require_once TSB_PATH . 'includes/class-tsb-ics.php';
require_once TSB_PATH . 'includes/class-tsb-emails.php';
require_once TSB_PATH . 'includes/class-tsb-ajax.php';
require_once TSB_PATH . 'includes/class-tsb-rest.php';
require_once TSB_PATH . 'admin/class-tsb-admin.php';

TSB_REST::init();

// Reminder cron.
add_action( TSB_Emails::CRON_HOOK, array( 'TSB_Emails', 'run_reminders' ) );
add_action( 'plugins_loaded', array( 'TSB_Emails', 'schedule_cron' ) );
register_deactivation_hook( __FILE__, array( 'TSB_Emails', 'clear_cron' ) );

register_activation_hook( __FILE__, array( 'TSB_DB', 'create_tables' ) );

// Translations. Source strings are English; ships with a Danish (da_DK) catalog.
add_action( 'init', function () {
	load_plugin_textdomain( 'tsb', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
} );

// Expose the admin-configured email/.ics strings to WPML String Translation.
add_action( 'admin_init', array( 'TSB_I18N', 'register' ) );
add_action( 'wpml_st_loaded', array( 'TSB_I18N', 'register' ) );

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
		echo esc_html__( 'Timeslot Booking requires Elementor to be installed and active. The booking widget will not appear until Elementor is active.', 'tsb' );
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
		'lang'    => TSB_I18N::current_language(),
		'i18n'    => tsb_js_i18n(),
	) );
}

/**
 * Strings + locale-aware month/weekday names handed to the front-end script.
 * Month/weekday names come from wp_date() so they match the active WP locale
 * with no separate translation entries.
 */
function tsb_js_i18n() {
	$months   = array();
	$weekdays = array();
	for ( $m = 1; $m <= 12; $m++ ) {
		$ts         = mktime( 0, 0, 0, $m, 1, 2025 );
		$months[]   = function_exists( 'wp_date' ) ? wp_date( 'F', $ts ) : gmdate( 'F', $ts );
	}
	// 2024-01-01 is a Monday — build a Monday-first abbreviated header.
	for ( $i = 0; $i < 7; $i++ ) {
		$ts         = mktime( 0, 0, 0, 1, 1 + $i, 2024 );
		$weekdays[] = function_exists( 'wp_date' ) ? wp_date( 'D', $ts ) : gmdate( 'D', $ts );
	}
	return array(
		'months'    => $months,
		'weekdays'  => $weekdays,
		'loading'   => __( 'Loading available times…', 'tsb' ),
		'loadError' => __( 'Could not load times. Please try again.', 'tsb' ),
		'netError'  => __( 'Network error. Please try again.', 'tsb' ),
		'noTimes'   => __( 'No available times right now.', 'tsb' ),
		'at'        => __( 'at', 'tsb' ),
		'free'      => __( 'free', 'tsb' ),
		'ok'        => __( 'OK', 'tsb' ),
		'error'     => __( 'Error', 'tsb' ),
		'required'  => __( 'This field is required.', 'tsb' ),
		'email'     => __( 'Please enter a valid email.', 'tsb' ),
		'consent'   => __( 'Please accept to continue.', 'tsb' ),
		'sending'   => __( 'Sending…', 'tsb' ),
		'another'   => __( 'Book another', 'tsb' ),
		'ref'       => __( 'Reference', 'tsb' ),
	);
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
