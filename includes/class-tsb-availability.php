<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TSB_Availability {

	/** Full settings = stored option merged over defaults. */
	public static function settings() {
		$def = array(
			// availability
			'slot_minutes'      => 30,  // slot length
			'slot_offset'       => 0,   // minutes after open before the first slot
			'slot_gap'          => 0,   // minutes between consecutive slots
			'base_start'        => 9,   // base business hours, applied to days set to "use base"
			'base_end'          => 17,
			'days_ahead'        => 30,
			'lead_hours'        => 0,    // min hours from now before a slot is bookable
			'block_holidays'    => 1,
			'holiday_countries' => array( 'DK' ),
			'week'              => self::default_week(),
			// emails
			'admin_notify'     => 1,
			'admin_to'         => '',   // blank => site admin_email
			'admin_subject'    => __( 'New booking: {date} {time}', 'tsb' ),
			'admin_body'       => __( "Name: {name}\nEmail: {email}\nPhone: {phone}\nTime: {date} at {time}\n\nMessage:\n{message}", 'tsb' ),
			'customer_confirm' => 1,
			'customer_subject' => __( 'Confirmation of your booking {date} at {time}', 'tsb' ),
			'customer_body'    => __( "Hi {name}\n\nThank you for your booking.\n\nDate: {date}\nTime: {time}\n\nWe look forward to seeing you. Reply to this email if you need to change the time.", 'tsb' ),
			'from_name'        => '', // blank => WordPress default
			'from_email'       => '',
			'ics_attach'       => 1,
			'ics_summary'      => __( 'Booking: {name}', 'tsb' ),
			'ics_location'     => '',
			// spam
			'captcha_mode'      => 'honeypot', // none | honeypot | recaptcha | recaptcha_v3 | hcaptcha
			'captcha_site'      => '',
			'captcha_secret'    => '',
			'captcha_min_score' => 0.5, // reCAPTCHA v3 only
			// form fields
			'field_phone'         => 1,
			'field_phone_req'     => 0,
			'field_message'       => 1,
			'field_message_req'   => 0,
			'field_custom'        => 0,
			'field_custom_label'  => __( 'Company', 'tsb' ),
			'field_custom_req'    => 0,
			'consent_enable'      => 0,
			'consent_text'        => __( 'I accept that my information is processed in order to handle my booking.', 'tsb' ),
			'consent_link_text'   => __( 'Privacy policy', 'tsb' ),
			'consent_url'         => '',
		);
		$s = wp_parse_args( get_option( 'tsb_settings', array() ), $def );
		if ( empty( $s['week'] ) || ! is_array( $s['week'] ) ) {
			$s['week'] = self::default_week();
		}
		if ( empty( $s['holiday_countries'] ) || ! is_array( $s['holiday_countries'] ) ) {
			$s['holiday_countries'] = array( 'DK' );
		}
		return $s;
	}

	/** Optional contact fields: key => [show, req, label, type, autocomplete]. */
	public static function fields() {
		$s = self::settings();
		return array(
			'phone'   => array( 'show' => ! empty( $s['field_phone'] ),   'req' => ! empty( $s['field_phone_req'] ),   'label' => __( 'Phone', 'tsb' ),   'type' => 'tel',      'autocomplete' => 'tel' ),
			'message' => array( 'show' => ! empty( $s['field_message'] ), 'req' => ! empty( $s['field_message_req'] ), 'label' => __( 'Message', 'tsb' ), 'type' => 'textarea', 'autocomplete' => '' ),
			'custom'  => array( 'show' => ! empty( $s['field_custom'] ),  'req' => ! empty( $s['field_custom_req'] ),  'label' => $s['field_custom_label'], 'type' => 'text', 'autocomplete' => 'organization' ),
		);
	}

	/** Mon–Fri open, weekend closed, all following the base business hours. */
	public static function default_week() {
		$w = array();
		for ( $d = 1; $d <= 7; $d++ ) {
			$w[ $d ] = array(
				'open'     => ( $d <= 5 ) ? 1 : 0,
				'use_base' => 1,     // follow base_start/base_end
				'start'    => 9,     // only used when use_base = 0
				'end'      => 17,
			);
		}
		return $w;
	}

	/** Weekday full names (1=Mon..7=Sun), localized to the active WP locale. */
	public static function weekday_names() {
		// 2024-01-01 is a Monday.
		$names = array();
		for ( $i = 0; $i < 7; $i++ ) {
			$ts = mktime( 0, 0, 0, 1, 1 + $i, 2024 );
			$names[ $i + 1 ] = function_exists( 'wp_date' ) ? wp_date( 'l', $ts ) : gmdate( 'l', $ts );
		}
		return $names;
	}

	/** Effective open/close hours for a weekday config, honouring the base toggle. */
	protected static function day_hours( $wd, $s ) {
		if ( ! empty( $wd['use_base'] ) ) {
			return array( (int) $s['base_start'], (int) $s['base_end'] );
		}
		return array( (int) $wd['start'], (int) $wd['end'] );
	}

	/**
	 * Build bookable days + slots from global settings.
	 * @return array[] each: ['date'=>'Y-m-d','label'=>'Mandag 9. jun','count'=>3,'slots'=>['09:00',...]]
	 */
	public static function build() {
		$s   = self::settings();
		$tz  = wp_timezone();
		$now = new DateTime( 'now', $tz );
		$out = array();

		$len    = max( 5, (int) $s['slot_minutes'] );
		$offset = max( 0, (int) $s['slot_offset'] );
		$gap    = max( 0, (int) $s['slot_gap'] );
		$step   = $len + $gap;

		$cursor = new DateTime( 'today', $tz );
		for ( $i = 0; $i <= (int) $s['days_ahead']; $i++ ) {
			if ( $i > 0 ) {
				$cursor->modify( '+1 day' );
			}
			$ymd = $cursor->format( 'Y-m-d' );
			$dow = (int) $cursor->format( 'N' ); // 1=Mon .. 7=Sun

			$wd = isset( $s['week'][ $dow ] ) ? $s['week'][ $dow ] : null;
			if ( ! $wd || empty( $wd['open'] ) ) {
				continue;
			}
			if ( $s['block_holidays'] && TSB_Holidays::is_holiday( $ymd, $s['holiday_countries'] ) ) {
				continue;
			}

			// Individually blocked times / whole day.
			$blocked_times = array();
			$whole_day     = false;
			foreach ( TSB_DB::blocked_for_date( $ymd ) as $b ) {
				if ( null === $b->block_time ) {
					$whole_day = true;
					break;
				}
				$blocked_times[ substr( $b->block_time, 0, 5 ) ] = true;
			}
			if ( $whole_day ) {
				continue;
			}

			$booked = array();
			foreach ( TSB_DB::booked_times( $ymd ) as $t ) {
				$booked[ substr( $t, 0, 5 ) ] = true;
			}

			list( $open_h, $close_h ) = self::day_hours( $wd, $s );
			$start = ( clone $cursor )->setTime( $open_h, 0 )->modify( "+$offset minutes" );
			$end   = ( clone $cursor )->setTime( $close_h, 0 );

			$slots = array();
			for ( $t = clone $start; ; $t->modify( "+$step minutes" ) ) {
				$slot_end = ( clone $t )->modify( "+$len minutes" );
				if ( $slot_end > $end ) {
					break; // slot would run past close
				}
				$hm = $t->format( 'H:i' );
				if ( isset( $blocked_times[ $hm ] ) || isset( $booked[ $hm ] ) ) {
					continue;
				}
				$earliest = clone $now;
				if ( (int) $s['lead_hours'] > 0 ) {
					$earliest->modify( '+' . (int) $s['lead_hours'] . ' hours' );
				}
				if ( $t <= $earliest ) {
					continue;
				}
				$slots[] = $hm;
			}

			if ( $slots ) {
				$out[] = array(
					'date'  => $ymd,
					'label' => self::da_label( $cursor ),
					'count' => count( $slots ),
					'slots' => $slots,
				);
			}
		}
		return $out;
	}

	/** Human day label (e.g. "Monday 9 Jun" / "Mandag 9. jun"), locale-aware. */
	protected static function da_label( DateTime $d ) {
		if ( function_exists( 'wp_date' ) ) {
			return wp_date( 'l j. M', $d->getTimestamp() );
		}
		$days   = array( 1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday' );
		$months = array( 1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Aug', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec' );
		return $days[ (int) $d->format( 'N' ) ] . ' ' . (int) $d->format( 'j' ) . ' ' . $months[ (int) $d->format( 'n' ) ];
	}
}
