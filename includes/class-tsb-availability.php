<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TSB_Availability {

	/** Full settings = stored option merged over defaults. */
	public static function settings() {
		$def = array(
			// availability
			'slot_minutes'     => 30,
			'days_ahead'       => 30,
			'lead_hours'       => 0,    // min hours from now before a slot is bookable
			'block_holidays'   => 1,
			'week'             => self::default_week(),
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
		return $s;
	}

	/** Mon–Fri open 9–17, weekend closed. */
	public static function default_week() {
		$w = array();
		for ( $d = 1; $d <= 7; $d++ ) {
			$w[ $d ] = array(
				'open'  => ( $d <= 5 ) ? 1 : 0,
				'start' => 9,
				'end'   => 17,
			);
		}
		return $w;
	}

	public static function weekday_names() {
		return array( 1 => 'Mandag', 2 => 'Tirsdag', 3 => 'Onsdag', 4 => 'Torsdag', 5 => 'Fredag', 6 => 'Lørdag', 7 => 'Søndag' );
	}

	/**
	 * Build bookable days + slots from global settings.
	 * @return array[] each: ['date'=>'Y-m-d','label'=>'Mandag 9. jun','slots'=>['09:00',...]]
	 */
	public static function build() {
		$s   = self::settings();
		$tz  = wp_timezone();
		$now = new DateTime( 'now', $tz );
		$out = array();

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
			if ( $s['block_holidays'] && TSB_Holidays::is_holiday( $ymd ) ) {
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

			$step  = max( 5, (int) $s['slot_minutes'] );
			$start = ( clone $cursor )->setTime( (int) $wd['start'], 0 );
			$end   = ( clone $cursor )->setTime( (int) $wd['end'], 0 );

			$slots = array();
			for ( $t = clone $start; $t < $end; $t->modify( "+$step minutes" ) ) {
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
