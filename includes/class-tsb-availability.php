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
			'admin_subject'    => 'Ny booking: {date} {time}',
			'admin_body'       => "Navn: {name}\nE-mail: {email}\nTelefon: {phone}\nTid: {date} kl. {time}\n\nBesked:\n{message}",
			'customer_confirm' => 1,
			'customer_subject' => 'Bekræftelse på din booking {date} kl. {time}',
			'customer_body'    => "Hej {name}\n\nTak for din booking.\n\nDato: {date}\nTid: {time}\n\nVi glæder os til at se dig. Svar på denne mail hvis du skal ændre tiden.",
			'from_name'        => '', // blank => WordPress default
			'from_email'       => '',
			'ics_attach'       => 1,
			'ics_summary'      => 'Booking: {name}',
			'ics_location'     => '',
			// spam
			'captcha_mode'      => 'honeypot', // none | honeypot | recaptcha | recaptcha_v3 | hcaptcha
			'captcha_site'      => '',
			'captcha_secret'    => '',
			'captcha_min_score' => 0.5, // reCAPTCHA v3 only
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

	public static function weekday_names() {
		return array( 1 => 'Mandag', 2 => 'Tirsdag', 3 => 'Onsdag', 4 => 'Torsdag', 5 => 'Fredag', 6 => 'Lørdag', 7 => 'Søndag' );
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

	/** Danish date label, locale-independent. */
	protected static function da_label( DateTime $d ) {
		$days   = self::weekday_names();
		$months = array( 1 => 'jan', 2 => 'feb', 3 => 'mar', 4 => 'apr', 5 => 'maj', 6 => 'jun', 7 => 'jul', 8 => 'aug', 9 => 'sep', 10 => 'okt', 11 => 'nov', 12 => 'dec' );
		return $days[ (int) $d->format( 'N' ) ] . ' ' . (int) $d->format( 'j' ) . '. ' . $months[ (int) $d->format( 'n' ) ];
	}
}
