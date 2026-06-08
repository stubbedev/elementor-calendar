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
			// form fields — ordered, user-defined (name + email are core, always shown)
			'fields'              => array(
				array( 'name' => 'phone', 'label' => __( 'Phone', 'tsb' ), 'type' => 'tel', 'enabled' => 1, 'required' => 0 ),
				array( 'name' => 'message', 'label' => __( 'Message', 'tsb' ), 'type' => 'textarea', 'enabled' => 1, 'required' => 0 ),
			),
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
		$s['fields'] = self::normalize_fields( $s['fields'] );
		return $s;
	}

	const FIELD_TYPES   = array( 'text', 'email', 'tel', 'textarea', 'number' );
	const RESERVED_NAMES = array( 'name', 'email', 'consent', 'date', 'time', 'stamp', 'captcha_token', 'tsb_hp', 'action', 'nonce', 'lang' );

	/** Coerce a stored fields value into a clean ordered list. */
	public static function normalize_fields( $fields ) {
		if ( ! is_array( $fields ) ) {
			return array();
		}
		$out  = array();
		$seen = array();
		foreach ( $fields as $f ) {
			if ( ! is_array( $f ) ) {
				continue;
			}
			$name = sanitize_key( $f['name'] ?? '' );
			if ( '' === $name || in_array( $name, self::RESERVED_NAMES, true ) || isset( $seen[ $name ] ) ) {
				continue;
			}
			$seen[ $name ] = true;
			$type = in_array( $f['type'] ?? 'text', self::FIELD_TYPES, true ) ? $f['type'] : 'text';
			$out[] = array(
				'name'     => $name,
				'label'    => sanitize_text_field( $f['label'] ?? $name ),
				'type'     => $type,
				'enabled'  => empty( $f['enabled'] ) ? 0 : 1,
				'required' => empty( $f['required'] ) ? 0 : 1,
			);
		}
		return $out;
	}

	/** Enabled fields, in order. */
	public static function form_fields() {
		$out = array();
		foreach ( self::settings()['fields'] as $f ) {
			if ( ! empty( $f['enabled'] ) ) {
				$out[] = $f;
			}
		}
		return $out;
	}

	/** Autocomplete hint for a field type/name. */
	public static function field_autocomplete( $field ) {
		if ( 'phone' === $field['name'] || 'tel' === $field['type'] ) {
			return 'tel';
		}
		if ( 'email' === $field['type'] ) {
			return 'email';
		}
		return '';
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

	/**
	 * All open slots for a single date with a free/taken flag — for the admin
	 * reschedule picker. Returns [] when the day is closed/holiday/whole-day
	 * blocked. Ignores lead time (admin can book anytime); a booking being moved
	 * can be excluded so its own current slot reads as free.
	 *
	 * @return array[] each ['time'=>'HH:MM','available'=>bool,'reason'=>'']
	 */
	public static function day_grid( $date, $exclude_id = 0 ) {
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $date ) ) {
			return array();
		}
		$s   = self::settings();
		$tz  = wp_timezone();
		$day = DateTime::createFromFormat( 'Y-m-d', $date, $tz );
		if ( ! $day ) {
			return array();
		}
		$day->setTime( 0, 0 );
		$dow = (int) $day->format( 'N' );

		$wd = isset( $s['week'][ $dow ] ) ? $s['week'][ $dow ] : null;
		if ( ! $wd || empty( $wd['open'] ) ) {
			return array();
		}
		if ( $s['block_holidays'] && TSB_Holidays::is_holiday( $date, $s['holiday_countries'] ) ) {
			return array();
		}

		$blocked = array();
		foreach ( TSB_DB::blocked_for_date( $date ) as $b ) {
			if ( null === $b->block_time ) {
				return array(); // whole day blocked
			}
			$blocked[ substr( $b->block_time, 0, 5 ) ] = true;
		}

		$booked = array();
		foreach ( self::booked_times_excluding( $date, $exclude_id ) as $tval ) {
			$booked[ substr( $tval, 0, 5 ) ] = true;
		}

		$len    = max( 5, (int) $s['slot_minutes'] );
		$offset = max( 0, (int) $s['slot_offset'] );
		$gap    = max( 0, (int) $s['slot_gap'] );
		$step   = $len + $gap;

		list( $open_h, $close_h ) = self::day_hours( $wd, $s );
		$start = ( clone $day )->setTime( $open_h, 0 )->modify( "+$offset minutes" );
		$end   = ( clone $day )->setTime( $close_h, 0 );

		$out = array();
		for ( $t = clone $start; ; $t->modify( "+$step minutes" ) ) {
			$slot_end = ( clone $t )->modify( "+$len minutes" );
			if ( $slot_end > $end ) {
				break;
			}
			$hm   = $t->format( 'H:i' );
			$free = ! isset( $booked[ $hm ] ) && ! isset( $blocked[ $hm ] );
			$out[] = array(
				'time'      => $hm,
				'available' => $free,
				'reason'    => $free ? '' : ( isset( $booked[ $hm ] ) ? 'booked' : 'blocked' ),
			);
		}
		return $out;
	}

	/** Booked HH:MM:SS for a date, optionally excluding one booking id. */
	protected static function booked_times_excluding( $date, $exclude_id ) {
		global $wpdb;
		$t  = TSB_DB::bookings_table();
		$id = (int) $exclude_id;
		if ( $id > 0 ) {
			return $wpdb->get_col( $wpdb->prepare(
				"SELECT slot_time FROM $t WHERE slot_date = %s AND status != 'cancelled' AND id != %d", $date, $id
			) );
		}
		return TSB_DB::booked_times( $date );
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
