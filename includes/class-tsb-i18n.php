<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WPML / Polylang (ICL) bridge.
 *
 * Two kinds of strings need different handling:
 *  - The plugin's own UI/source strings use __()/_e() and are translated by the
 *    normal gettext catalog; WPML switches the locale per language, so those are
 *    already covered with no extra work.
 *  - The admin-configured strings (email subjects/bodies, .ics title) live in an
 *    option, so gettext can't reach them. We register them with WPML String
 *    Translation and translate them on output through the documented WPML hooks.
 *
 * Every call degrades to a no-op when WPML/Polylang is absent: the wpml_*
 * actions simply have no listeners, and apply_filters() returns the original
 * value unchanged. Nothing here hard-depends on WPML.
 */
class TSB_I18N {

	const DOMAIN = 'Timeslot Booking';

	/** Option key => human label shown in WPML → String Translation. */
	public static function strings() {
		return array(
			'admin_subject'    => 'Admin email subject',
			'admin_body'       => 'Admin email body',
			'customer_subject' => 'Customer email subject',
			'customer_body'    => 'Customer email body',
			'ics_summary'      => 'Calendar event title',
		);
	}

	/** Register the configurable strings so they appear in WPML String Translation. */
	public static function register() {
		$s = TSB_Availability::settings();
		foreach ( self::strings() as $key => $label ) {
			if ( isset( $s[ $key ] ) && '' !== $s[ $key ] ) {
				do_action( 'wpml_register_single_string', self::DOMAIN, $label, (string) $s[ $key ] );
			}
		}
	}

	/** Translate a configured option value for the current language. */
	public static function translate( $key, $value ) {
		$labels = self::strings();
		$name   = isset( $labels[ $key ] ) ? $labels[ $key ] : $key;
		return apply_filters( 'wpml_translate_single_string', $value, self::DOMAIN, $name );
	}

	/** Switch the active language (e.g. to the visitor's language during AJAX). */
	public static function switch_language( $lang ) {
		$lang = preg_replace( '/[^a-zA-Z_-]/', '', (string) $lang );
		if ( $lang ) {
			do_action( 'wpml_switch_language', $lang );
		}
	}

	/** Current language code, or null when no multilingual plugin is active. */
	public static function current_language() {
		return apply_filters( 'wpml_current_language', null );
	}
}
