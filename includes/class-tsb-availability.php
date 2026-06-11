<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TSB_Availability {

	/** Full settings = stored option merged over defaults. */
	public static function settings() {
		$def = array(
			// Global scheduling rules (apply to every session type).
			'days_ahead'        => 30,  // booking window
			'lead_hours'        => 0,   // min hours from now before a slot is bookable
			'block_holidays'    => 1,
			'holiday_countries' => array( 'DK' ),
			// emails
			'ics_attach'       => 1,
			'ics_summary'      => 'Booking: {{name}}',
			'ics_location'     => '',
			'emails'           => class_exists( 'TSB_Emails' ) ? TSB_Emails::default_templates() : array(),
			'reminder_hours'   => 24,
			// google calendar / meet (global OAuth client; per-type meet toggle lives on the type)
			'google_client_id'     => '',
			'google_client_secret' => '',
			'google_calendar_id'   => 'primary',
			// form fields — ordered, user-defined (name + email are core, always shown).
			// Spam is a built-in honeypot (always on); there is nothing to configure.
			'fields'              => array(
				array( 'name' => 'phone', 'label' => __( 'Phone', 'tsb' ), 'type' => 'tel', 'enabled' => 1, 'required' => 0 ),
				array( 'name' => 'message', 'label' => __( 'Message', 'tsb' ), 'type' => 'textarea', 'enabled' => 1, 'required' => 0 ),
			),
		);
		$s = wp_parse_args( get_option( 'tsb_settings', array() ), $def );
		// Drop retired/relocated globals so they don't linger or leak to the client:
		//  - v0.2: captcha config, GDPR consent (removed)
		//  - v0.3: per-type schedule fields that used to be global (now live on the type)
		unset(
			$s['captcha_mode'], $s['captcha_site'], $s['captcha_secret'], $s['captcha_min_score'],
			$s['consent_enable'], $s['consent_text'], $s['consent_link_text'], $s['consent_url'],
			$s['slot_minutes'], $s['slot_offset'], $s['slot_gap'], $s['base_start'], $s['base_end'],
			$s['week'], $s['ics_attach'], $s['ics_summary'], $s['ics_location'], $s['reminder_hours']
		);
		if ( empty( $s['holiday_countries'] ) || ! is_array( $s['holiday_countries'] ) ) {
			$s['holiday_countries'] = array( 'DK' );
		}
		$s['fields'] = self::normalize_fields( $s['fields'] );
		if ( class_exists( 'TSB_Emails' ) ) {
			$def_em      = TSB_Emails::default_templates();
			$s['emails'] = is_array( $s['emails'] ?? null ) ? $s['emails'] : array();
			foreach ( $def_em as $k => $v ) {
				$s['emails'][ $k ] = isset( $s['emails'][ $k ] ) ? array_merge( $v, (array) $s['emails'][ $k ] ) : $v;
			}
		}
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

	/** Phone value from a stored meta map (the dynamic field named "phone"). */
	public static function phone_from_meta( $fieldvals ) {
		return is_array( $fieldvals ) && isset( $fieldvals['phone'] ) ? (string) $fieldvals['phone'] : '';
	}

	/**
	 * Rebuild the human-readable booking body from a stored meta map. This is the
	 * single source for what used to live in the `message` column: every non-core
	 * field rendered as "Label: value", with the message textarea appended last.
	 * Phone is excluded (it's surfaced on its own). Falls back to the raw field
	 * name when a field has since been removed from the form config.
	 */
	public static function summary_from_meta( $fieldvals ) {
		if ( ! is_array( $fieldvals ) ) {
			return '';
		}
		$known   = array();
		$extra   = array();
		$message = '';
		foreach ( self::form_fields() as $f ) {
			$known[ $f['name'] ] = true;
			$val = isset( $fieldvals[ $f['name'] ] ) ? trim( (string) $fieldvals[ $f['name'] ] ) : '';
			if ( '' === $val || 'phone' === $f['name'] ) {
				continue;
			}
			if ( 'message' === $f['name'] && 'textarea' === $f['type'] ) {
				$message = $val;
				continue;
			}
			$extra[] = $f['label'] . ': ' . $val;
		}
		// Values whose field was removed from the form since booking — keep them.
		foreach ( $fieldvals as $name => $val ) {
			if ( isset( $known[ $name ] ) || 'phone' === $name ) {
				continue;
			}
			$val = trim( (string) $val );
			if ( '' !== $val ) {
				$extra[] = $name . ': ' . $val;
			}
		}
		$msg = implode( "\n", $extra );
		if ( '' !== $message ) {
			$msg = ( '' !== $msg ? $msg . "\n\n" : '' ) . $message;
		}
		return $msg;
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
	protected static function day_hours( $wd, $cfg ) {
		if ( ! empty( $wd['use_base'] ) ) {
			return array( (int) $cfg['base_start'], (int) $cfg['base_end'] );
		}
		return array( (int) $wd['start'], (int) $wd['end'] );
	}

	/** Minutes-since-midnight from a 'HH:MM[:SS]' string. */
	protected static function hms_to_min( $hms ) {
		$p = explode( ':', (string) $hms );
		return (int) ( $p[0] ?? 0 ) * 60 + (int) ( $p[1] ?? 0 );
	}

	/** Turn booked-interval rows into [start_min, end_min] pairs. */
	protected static function busy_minutes( $rows ) {
		$out = array();
		foreach ( $rows as $r ) {
			$s = self::hms_to_min( $r->slot_time );
			$e = $r->slot_end ? self::hms_to_min( $r->slot_end ) : $s;
			if ( $e <= $s ) {
				$e = $s + 1; // legacy/zero-length row → treat as a 1-minute point
			}
			$out[] = array( $s, $e );
		}
		return $out;
	}

	/**
	 * Time-off for a date: whether the whole day is blocked, and the blocked
	 * [start_min, end_min) ranges otherwise. A whole-day block (block_time NULL)
	 * short-circuits — the day is closed regardless of any ranges.
	 *
	 * @return array{whole:bool,intervals:array<int,array{0:int,1:int}>}
	 */
	protected static function blocked_ranges( $date ) {
		$whole     = false;
		$intervals = array();
		foreach ( TSB_DB::blocked_for_date( $date ) as $b ) {
			if ( null === $b->block_time ) {
				$whole = true;
				break;
			}
			$s = self::hms_to_min( $b->block_time );
			$e = $b->block_end ? self::hms_to_min( $b->block_end ) : $s + 1;
			if ( $e <= $s ) {
				$e = $s + 1; // guard against a zero/negative range
			}
			$intervals[] = array( $s, $e );
		}
		return array( 'whole' => $whole, 'intervals' => $intervals );
	}

	/** Does candidate [cs, ce) overlap any busy interval? Half-open ranges. */
	protected static function overlaps( $busy, $cs, $ce ) {
		foreach ( $busy as $b ) {
			if ( $b[0] < $ce && $b[1] > $cs ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Is the range [time, time+len) on $date free of any active booking (any type)?
	 * Used by the admin reschedule to block moves that would overbook. Excludes the
	 * booking being moved so it doesn't collide with itself.
	 */
	public static function range_free( $date, $time, $len, $exclude_id = 0 ) {
		$busy = self::busy_minutes( TSB_DB::booked_intervals( $date, (int) $exclude_id ) );
		$cs   = self::hms_to_min( $time );
		return ! self::overlaps( $busy, $cs, $cs + max( 5, (int) $len ) );
	}

	/**
	 * Build bookable days + slots for one session type.
	 *
	 * Overbooking guard: a candidate slot is dropped if its time range overlaps any
	 * existing active booking of *any* type (variable-length slots compared as
	 * ranges, not exact start times), so a 60-min booking hides the 30-min slots
	 * that fall inside it.
	 *
	 * @param string $type_id Session type id.
	 * @return array[] each: ['date'=>'Y-m-d','label'=>'Mandag 9. jun','count'=>3,'slots'=>['09:00',...]]
	 */
	public static function build( $type_id = 'default' ) {
		$cfg = TSB_Types::get( $type_id );
		$s   = self::settings(); // holiday blocking is a global, site-wide rule
		$tz  = wp_timezone();
		$now = new DateTime( 'now', $tz );
		$out = array();

		$len    = max( 5, (int) $cfg['slot_minutes'] );
		$gap    = max( 0, (int) $cfg['slot_gap'] );
		$step   = $len + $gap;

		$cursor = new DateTime( 'today', $tz );
		for ( $i = 0; $i <= (int) $s['days_ahead']; $i++ ) {
			if ( $i > 0 ) {
				$cursor->modify( '+1 day' );
			}
			$ymd = $cursor->format( 'Y-m-d' );
			$dow = (int) $cursor->format( 'N' ); // 1=Mon .. 7=Sun

			$wd = isset( $cfg['week'][ $dow ] ) ? $cfg['week'][ $dow ] : null;
			if ( ! $wd || empty( $wd['open'] ) ) {
				continue;
			}
			if ( $s['block_holidays'] && TSB_Holidays::is_holiday( $ymd, $s['holiday_countries'] ) ) {
				continue;
			}

			// Time off (global): whole-day closes the day, ranges hide overlapping slots.
			$off = self::blocked_ranges( $ymd );
			if ( $off['whole'] ) {
				continue;
			}

			// Busy ranges from every type's active bookings, plus the blocked ranges.
			$busy = array_merge( self::busy_minutes( TSB_DB::booked_intervals( $ymd ) ), $off['intervals'] );

			list( $open_h, $close_h ) = self::day_hours( $wd, $cfg );
			$start = ( clone $cursor )->setTime( $open_h, 0 );
			$end   = ( clone $cursor )->setTime( $close_h, 0 );

			$slots = array();
			for ( $t = clone $start; ; $t->modify( "+$step minutes" ) ) {
				$slot_end = ( clone $t )->modify( "+$len minutes" );
				if ( $slot_end > $end ) {
					break; // slot would run past close
				}
				$hm = $t->format( 'H:i' );
				$cs = self::hms_to_min( $hm );
				if ( self::overlaps( $busy, $cs, $cs + $len ) ) {
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
	 * @param string $date       Y-m-d.
	 * @param int    $exclude_id Booking id to ignore (the one being moved).
	 * @param string $type_id    Session type to lay the grid out for.
	 * @return array[] each ['time'=>'HH:MM','available'=>bool,'reason'=>'']
	 */
	public static function day_grid( $date, $exclude_id = 0, $type_id = 'default' ) {
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $date ) ) {
			return array();
		}
		$cfg = TSB_Types::get( $type_id );
		$s   = self::settings(); // holiday blocking is a global, site-wide rule
		$tz  = wp_timezone();
		$day = DateTime::createFromFormat( 'Y-m-d', $date, $tz );
		if ( ! $day ) {
			return array();
		}
		$day->setTime( 0, 0 );
		$dow = (int) $day->format( 'N' );

		$wd = isset( $cfg['week'][ $dow ] ) ? $cfg['week'][ $dow ] : null;
		if ( ! $wd || empty( $wd['open'] ) ) {
			return array();
		}
		if ( $s['block_holidays'] && TSB_Holidays::is_holiday( $date, $s['holiday_countries'] ) ) {
			return array();
		}

		$off = self::blocked_ranges( $date );
		if ( $off['whole'] ) {
			return array(); // whole day blocked
		}
		$blocked = $off['intervals'];

		// Busy ranges across all types, excluding the booking being rescheduled.
		$busy = self::busy_minutes( TSB_DB::booked_intervals( $date, $exclude_id ) );

		$len    = max( 5, (int) $cfg['slot_minutes'] );
		$gap    = max( 0, (int) $cfg['slot_gap'] );
		$step   = $len + $gap;

		list( $open_h, $close_h ) = self::day_hours( $wd, $cfg );
		$start = ( clone $day )->setTime( $open_h, 0 );
		$end   = ( clone $day )->setTime( $close_h, 0 );

		$out = array();
		for ( $t = clone $start; ; $t->modify( "+$step minutes" ) ) {
			$slot_end = ( clone $t )->modify( "+$len minutes" );
			if ( $slot_end > $end ) {
				break;
			}
			$hm        = $t->format( 'H:i' );
			$cs        = self::hms_to_min( $hm );
			$booked_ov = self::overlaps( $busy, $cs, $cs + $len );
			$blocked_ov = self::overlaps( $blocked, $cs, $cs + $len );
			$free      = ! $booked_ov && ! $blocked_ov;
			$out[]     = array(
				'time'      => $hm,
				'available' => $free,
				'reason'    => $free ? '' : ( $booked_ov ? 'booked' : 'blocked' ),
			);
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
