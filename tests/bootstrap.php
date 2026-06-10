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
if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) ); }
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $str ) { return trim( preg_replace( '/\s+/', ' ', (string) $str ) ); }
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
		if ( 'tsb_types' === $name ) {
			// No stored types in tests → TSB_Types synthesizes a 'default' type
			// from tsb_test_option, so build() stays driven by the test settings.
			return isset( $GLOBALS['tsb_test_types'] ) ? $GLOBALS['tsb_test_types'] : $default;
		}
		return $default;
	}
}
if ( ! function_exists( 'get_bloginfo' ) ) {
	function get_bloginfo( $show = '' ) { return 'Test Site'; }
}

if ( ! function_exists( 'wp_timezone' ) ) {
	function wp_timezone() {
		return new DateTimeZone( 'Europe/Copenhagen' );
	}
}

/** Fake DB layer — TSB_Availability::build() calls these. */
class TSB_DB {
	public static function blocked_for_date( $date ) {
		return isset( $GLOBALS['tsb_test_blocked'][ $date ] ) ? $GLOBALS['tsb_test_blocked'][ $date ] : array();
	}
	public static function booked_times( $date ) {
		return isset( $GLOBALS['tsb_test_booked'][ $date ] ) ? $GLOBALS['tsb_test_booked'][ $date ] : array();
	}
	/**
	 * Busy intervals across all types. Tests express bookings as 'HH:MM:SS' start
	 * times in tsb_test_booked; give each one a slot_end one slot-length later so
	 * the overlap engine removes the exact booked slot (matching legacy behaviour).
	 */
	public static function booked_intervals( $date, $exclude_id = 0 ) {
		// Explicit [start,end) ranges (for cross-length overlap tests) take priority.
		if ( isset( $GLOBALS['tsb_test_intervals'][ $date ] ) ) {
			$out = array();
			foreach ( $GLOBALS['tsb_test_intervals'][ $date ] as $iv ) {
				$o            = new stdClass();
				$o->slot_time = $iv[0];
				$o->slot_end  = $iv[1];
				$out[]        = $o;
			}
			return $out;
		}
		$opt = $GLOBALS['tsb_test_option'];
		$len = max( 5, (int) ( $opt['slot_minutes'] ?? 30 ) );
		$out = array();
		foreach ( ( $GLOBALS['tsb_test_booked'][ $date ] ?? array() ) as $start ) {
			$p   = array_map( 'intval', explode( ':', $start ) );
			$min = $p[0] * 60 + ( $p[1] ?? 0 ) + $len;
			$o            = new stdClass();
			$o->slot_time = $start;
			$o->slot_end  = sprintf( '%02d:%02d:00', intdiv( $min, 60 ), $min % 60 );
			$out[]        = $o;
		}
		return $out;
	}
}

require_once dirname( __DIR__ ) . '/includes/class-tsb-holidays.php';
require_once dirname( __DIR__ ) . '/includes/class-tsb-emails.php';
require_once dirname( __DIR__ ) . '/includes/class-tsb-availability.php';
require_once dirname( __DIR__ ) . '/includes/class-tsb-types.php';

/** Helper: build a blocked-time row object like $wpdb returns. */
function tsb_block_row( $time ) {
	$o = new stdClass();
	$o->block_time = $time; // null = whole day
	return $o;
}
