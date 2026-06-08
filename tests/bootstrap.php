<?php
/**
 * Minimal WP shim so TSB_Holidays + TSB_Availability run under plain PHPUnit
 * with no WordPress install. Only the functions these two classes touch.
 */

define( 'ABSPATH', __DIR__ . '/' ); // satisfies the `if ( ! defined ABSPATH ) exit;` guards

// Test-controlled state.
$GLOBALS['tsb_test_option'] = array(); // value returned by get_option('tsb_settings')
$GLOBALS['tsb_test_blocked'] = array(); // 'Y-m-d' => array of row objects ( ->block_time )
$GLOBALS['tsb_test_booked']  = array(); // 'Y-m-d' => array of 'HH:MM:SS'

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) { return $text; }
}
if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $text, $domain = 'default' ) { return $text; }
}

if ( ! function_exists( 'wp_parse_args' ) ) {
	function wp_parse_args( $args, $defaults = array() ) {
		return array_merge( $defaults, (array) $args );
	}
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $name, $default = false ) {
		if ( 'tsb_settings' === $name ) {
			return $GLOBALS['tsb_test_option'];
		}
		return $default;
	}
}

if ( ! function_exists( 'wp_timezone' ) ) {
	function wp_timezone() {
		return new DateTimeZone( 'Europe/Copenhagen' );
	}
}

/** Fake DB layer — TSB_Availability::build() only calls these two. */
class TSB_DB {
	public static function blocked_for_date( $date ) {
		return isset( $GLOBALS['tsb_test_blocked'][ $date ] ) ? $GLOBALS['tsb_test_blocked'][ $date ] : array();
	}
	public static function booked_times( $date ) {
		return isset( $GLOBALS['tsb_test_booked'][ $date ] ) ? $GLOBALS['tsb_test_booked'][ $date ] : array();
	}
}

require_once dirname( __DIR__ ) . '/includes/class-tsb-holidays.php';
require_once dirname( __DIR__ ) . '/includes/class-tsb-availability.php';

/** Helper: build a blocked-time row object like $wpdb returns. */
function tsb_block_row( $time ) {
	$o = new stdClass();
	$o->block_time = $time; // null = whole day
	return $o;
}
